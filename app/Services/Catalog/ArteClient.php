<?php

namespace App\Services\Catalog;

use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use XMLReader;

class ArteClient
{
    public function products(): Generator
    {
        // The description feed may contain old prices; always join the current stock feed by product ID.
        $stocks = [];
        foreach ($this->items('stock_url') as $item) {
            $stocks[(string) $item->product_id] = [
                'price' => is_numeric((string) $item->maxprice_kz) ? (float) $item->maxprice_kz : null,
                'stock' => is_numeric((string) $item->full_stock) ? max(0, (int) $item->full_stock) : null,
            ];
        }
        if (! $stocks) {
            throw new RuntimeException('Arte Gifts: пустая выгрузка цен и остатков.');
        }
        foreach ($this->items('products_url') as $item) {
            yield $this->normalize($item, $stocks[(string) $item->id] ?? null);
        }
    }

    private function items(string $urlKey): Generator
    {
        if (! config('catalog.arte.username') || ! config('catalog.arte.password')) {
            throw new RuntimeException('Не заданы ARTE_USERNAME и ARTE_PASSWORD.');
        }
        $path = tempnam(storage_path('app/private'), 'arte-');
        $reader = new XMLReader;
        $previous = libxml_use_internal_errors(true);
        try {
            Http::withBasicAuth(config('catalog.arte.username'), config('catalog.arte.password'))
                ->connectTimeout(25)->timeout(180)->withOptions(['sink' => $path])
                ->get(config('catalog.arte.'.$urlKey))->throw();
            if (! $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException('Arte Gifts: не удалось открыть XML.');
            }
            $count = 0;
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    throw new RuntimeException('DTD в XML не поддерживается.');
                }
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'item') {
                    $xml = simplexml_load_string($reader->readOuterXml(), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
                    if (! $xml) {
                        throw new RuntimeException('Arte Gifts: повреждённый элемент XML.');
                    }
                    $count++;
                    yield $xml;
                }
            }
            if (libxml_get_errors() || $count === 0) {
                throw new RuntimeException('Arte Gifts: пустой или повреждённый XML.');
            }
        } finally {
            $reader->close();
            if (is_file($path)) {
                unlink($path);
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function normalize(SimpleXMLElement $item, ?array $stock): array
    {
        $attributes = [];
        foreach ($item->product_attributes->children() as $attribute) {
            $attributes[] = ['name' => Taxonomy::text($attribute['name']), 'value' => Taxonomy::text($attribute)];
        }
        if ((string) $item->minQuantity !== '') {
            $attributes[] = ['name' => 'Минимальное количество', 'value' => (string) $item->minQuantity];
        }
        if ((string) $item->notes !== '') {
            $attributes[] = ['name' => 'Условия поставки', 'value' => Taxonomy::text($item->notes)];
        }

        return [
            'external_id' => (string) $item->id,
            'article' => (string) $item->sku,
            'name' => Taxonomy::text($item->product),
            'description' => Taxonomy::text($item->general_description."\n".$item->variation_description),
            'category' => Taxonomy::detect($item->product.' '.$item->category),
            'brand' => Taxonomy::text($item->brand),
            // Missing current data is shown as "уточняйте", never as an old feed price.
            'price' => $stock['price'] ?? null,
            'currency' => 'KZT',
            'stock' => $stock['stock'] ?? null,
            'images' => array_values(array_filter(array_map(fn ($url) => Taxonomy::image(trim($url)), explode(',', (string) $item->images_urls)))),
            'attributes' => $attributes,
            'source_url' => Taxonomy::image((string) $item->url),
            'active' => true,
        ];
    }
}
