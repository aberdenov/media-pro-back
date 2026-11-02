<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\ArteClient;
use App\Services\Catalog\OasisClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SyncCatalog extends Command
{
    protected $signature = 'catalog:sync {provider? : oasis or arte; omit for both} {--limit=0 : Import only N products for a connection check; no deactivation}';

    protected $description = 'Import provider catalogs, current KZT prices and stock';

    public function handle(OasisClient $oasis, ArteClient $arte): int
    {
        $providers = $this->argument('provider') ? [$this->argument('provider')] : ['oasis', 'arte'];
        if (array_diff($providers, ['oasis', 'arte'])) {
            $this->error('Допустимые поставщики: oasis, arte.');

            return self::FAILURE;
        }
        $limit = (int) $this->option('limit');
        if ($limit < 0) {
            $this->error('limit должен быть неотрицательным.');

            return self::FAILURE;
        }
        $failed = false;
        foreach ($providers as $provider) {
            $lock = Cache::lock('catalog-sync-'.$provider, 7200);
            if (! $lock->get()) {
                $this->warn($provider.': синхронизация уже выполняется.');
                $failed = true;

                continue;
            }
            $run = DB::table('sync_runs')->insertGetId(['provider' => $provider, 'started_at' => now(), 'status' => 'running']);
            $token = (string) Str::uuid();
            $count = 0;
            try {
                $this->info($provider.': загрузка каталога…');
                foreach (($provider === 'oasis' ? $oasis : $arte)->products() as $product) {
                    if (! $product['external_id'] || ! $product['name']) {
                        throw new \RuntimeException('Отсутствует идентификатор или название товара.');
                    }
                    if ($product['price'] !== null) {
                        $product['price'] = round($product['price'] * (1 + config('catalog.markup_percent') / 100), 2);
                    }
                    Product::updateOrCreate(['provider' => $provider, 'external_id' => $product['external_id']], [
                        ...$product, 'search_text' => mb_strtolower($product['name'].' '.$product['article'].' '.$product['brand']),
                        'sync_token' => $token, 'synced_at' => now(),
                    ]);
                    $count++;
                    if ($count % 500 === 0) {
                        $this->line($provider.': '.$count);
                    }
                    if ($limit && $count >= $limit) {
                        break;
                    }
                }
                if ($count === 0) {
                    throw new \RuntimeException('Поставщик вернул пустой каталог.');
                }
                if (! $limit) {
                    Product::where('provider', $provider)->where(fn ($q) => $q->where('sync_token', '!=', $token)->orWhereNull('sync_token'))->update(['active' => false]);
                }
                DB::table('sync_runs')->where('id', $run)->update(['status' => $limit ? 'partial' : 'success', 'processed' => $count, 'finished_at' => now()]);
                Cache::forget('catalog-meta');
                Cache::forget('catalog-featured');
                $this->info($provider.': сохранено '.$count.' товаров'.($limit ? ' (проверка подключения)' : '').'.');
            } catch (Throwable $e) {
                // Do not expose exception messages from HTTP clients: they may include supplier URLs or secrets.
                $message = 'Ошибка загрузки или обработки каталога ('.class_basename($e).'). Существующие товары сохранены.';
                DB::table('sync_runs')->where('id', $run)->update(['status' => 'failed', 'processed' => $count, 'message' => $message, 'finished_at' => now()]);
                $this->error($provider.': '.$message);
                $failed = true;
            } finally {
                $lock->release();
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
