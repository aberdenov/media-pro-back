<?php

namespace App\Services\Catalog;

use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OasisClient
{
    public function products(): Generator
    {
        $key = config('catalog.oasis.key');
        if (! $key) {
            throw new RuntimeException('Не задан OASIS_API_KEY.');
        }
        $limit = config('catalog.oasis.page_size');
        $offset = 0;
        do {
            if ($offset > 0) {
                usleep((int) config('catalog.oasis.request_interval_ms') * 1000);
            }
            $response = Http::acceptJson()->withBasicAuth($key, '')
                ->connectTimeout(20)->timeout(90)->retry(3, 2500)
                ->get(config('catalog.oasis.url').'/v4/products', [
                    'limit' => $limit, 'offset' => $offset, 'currency' => config('catalog.oasis.currency'),
                    'format' => 'json',
                ])->throw();
            $rows = $response->json();
            if (! is_array($rows) || ! array_is_list($rows)) {
                throw new RuntimeException('Oasis вернул неожиданный формат данных.');
            }
            foreach ($rows as $row) {
                yield $this->normalize($row);
            }
            $offset += count($rows);
        } while (count($rows) === $limit);
    }

    public function normalize(array $row): array
    {
        $images = array_values(array_filter(array_map(
            fn ($image) => Taxonomy::image(is_array($image) ? ($image['big'] ?? $image['superbig'] ?? null) : $image),
            $row['images'] ?? [],
        )));
        $attributes = [];
        foreach ($row['attributes'] ?? [] as $attribute) {
            if (isset($attribute['name'], $attribute['value'])) {
                $attributes[] = ['name' => Taxonomy::text($attribute['name']), 'value' => Taxonomy::text($attribute['value'].(isset($attribute['dim']) ? ' '.$attribute['dim'] : ''))];
            }
        }

        return [
            'external_id' => (string) $row['id'],
            'article' => (string) ($row['article'] ?? $row['id']),
            'name' => Taxonomy::text($row['full_name'] ?? $row['name']),
            'description' => Taxonomy::text($row['description'] ?? ''),
            'category' => Taxonomy::detect($row['name'].' '.($row['full_name'] ?? '')),
            'brand' => Taxonomy::text($row['brand'] ?? ''),
            'price' => isset($row['price']) && is_numeric($row['price']) ? (float) $row['price'] : null,
            'currency' => 'KZT',
            'stock' => isset($row['total_stock']) ? max(0, (int) $row['total_stock']) : null,
            'images' => $images,
            'attributes' => $attributes,
            'source_url' => null,
            'active' => ! ($row['is_deleted'] ?? false) && ! ($row['is_stopped'] ?? false),
        ];
    }
}
