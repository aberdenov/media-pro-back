<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_filters_pagination_and_private_fields(): void
    {
        Product::factory()->create(['article' => 'MP-123', 'price' => 2000]);
        Product::factory()->create(['price' => 9000]);
        Product::factory()->create(['stock' => 0]);
        Product::factory()->create(['provider' => 'arte']);
        Product::factory()->create(['active' => false]);
        $response = $this->getJson('/api/products?'.http_build_query([
            'q' => 'ТЕРМОКРУЖКА', 'category' => 'drinkware', 'provider' => 'oasis',
            'max_price' => 3000, 'in_stock' => 1, 'per_page' => 1,
        ]));
        $response->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.article', 'MP-123');
        $this->assertArrayNotHasKey('sync_token', $response->json('data.0'));
        $this->assertArrayNotHasKey('search_text', $response->json('data.0'));
        $this->getJson('/api/products?per_page=500')->assertUnprocessable();
    }

    public function test_inactive_products_cannot_be_viewed_or_requested(): void
    {
        $product = Product::factory()->create(['active' => false]);
        $this->getJson('/api/products/'.$product->id)->assertNotFound();
        $this->postJson('/api/inquiries', $this->inquiry([['id' => $product->id, 'quantity' => 10]]))->assertUnprocessable();
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_inquiry_keeps_server_price_and_requires_consent(): void
    {
        $product = Product::factory()->create(['price' => 5000]);
        $payload = $this->inquiry([['id' => $product->id, 'quantity' => 100, 'price' => 1]]);
        $this->postJson('/api/inquiries', [...$payload, 'consent' => false])->assertUnprocessable();
        $response = $this->postJson('/api/inquiries', $payload)->assertCreated()->assertJsonStructure(['id']);
        $stored = DB::table('inquiries')->where('id', $response->json('id'))->first();
        $this->assertEquals(5000, json_decode($stored->items, true)[0]['price']);
        $this->assertDatabaseCount('inquiries', 1);
    }

    public function test_cached_catalog_data_survives_database_serialization(): void
    {
        config(['cache.default' => 'database']);
        Cache::purge('database');
        Product::factory()->create();
        $this->getJson('/api/catalog/meta')->assertOk()->assertJsonCount(8, 'categories');
        $this->getJson('/api/catalog/meta')->assertOk()->assertJsonPath('categories.0.slug', 'drinkware');
        $this->getJson('/api/catalog/featured')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/catalog/featured')->assertOk()->assertJsonPath('data.0.currency', 'KZT');
    }

    public function test_bad_or_duplicate_inquiry_items_are_rejected(): void
    {
        $product = Product::factory()->create();
        $this->postJson('/api/inquiries', $this->inquiry([['id' => $product->id, 'quantity' => 0]]))->assertUnprocessable();
        $this->postJson('/api/inquiries', $this->inquiry([
            ['id' => $product->id, 'quantity' => 1], ['id' => $product->id, 'quantity' => 1],
        ]))->assertUnprocessable();
        $this->postJson('/api/inquiries', [...$this->inquiry([]), 'website' => 'spam'])->assertUnprocessable();
    }

    private function inquiry(array $items): array
    {
        return ['name' => 'Тестовый клиент', 'phone' => '+77071234567', 'consent' => true, 'items' => $items];
    }
}
