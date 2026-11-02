<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('inquiries:list {--id= : Show one inquiry including all items} {--limit=20 : Number of recent inquiries}')]
#[Description('View customer inquiries stored by MEDIAPRO')]
class ListInquiries extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = DB::table('inquiries');
        if ($id = $this->option('id')) {
            $inquiry = $query->where('id', $id)->first();
            if (! $inquiry) {
                $this->error('Заявка не найдена.');

                return self::FAILURE;
            }
            $this->table(['Поле', 'Значение'], collect((array) $inquiry)->except('items')->map(fn ($value, $key) => [$key, $value])->values()->all());
            $this->table(['Товар', 'Артикул', 'Поставщик', 'Количество', 'Цена, ₸'], array_map(
                fn ($item) => [$item['name'], $item['article'], $item['provider'], $item['quantity'], $item['price'] ?? 'По запросу'],
                json_decode($inquiry->items, true),
            ));
        } else {
            $this->table(['ID', 'Имя', 'Телефон', 'Компания', 'Дата'], $query->orderByDesc('created_at')->limit(max(1, min(100, (int) $this->option('limit'))))->get(['id', 'name', 'phone', 'company', 'created_at'])->map(fn ($row) => (array) $row)->all());
        }

        return self::SUCCESS;
    }
}
