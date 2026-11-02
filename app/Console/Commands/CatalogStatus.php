<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('catalog:status')]
#[Description('Show supplier catalog counts and latest sync results')]
class CatalogStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rows = [];
        foreach (['oasis', 'arte'] as $provider) {
            $last = DB::table('sync_runs')->where('provider', $provider)->orderByDesc('id')->first();
            $rows[] = [$provider, Product::where('provider', $provider)->where('active', true)->count(), $last?->status ?? 'not_started', $last?->finished_at ?? '—'];
        }
        $this->table(['Поставщик', 'Активных товаров', 'Последний импорт', 'Завершён'], $rows);

        return self::SUCCESS;
    }
}
