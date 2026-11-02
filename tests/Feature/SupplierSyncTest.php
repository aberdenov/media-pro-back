<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Catalog\ArteClient;
use App\Services\Catalog\OasisClient;
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use Tests\TestCase;

class SupplierSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_import_is_idempotent_and_deactivates_only_its_own_missing_products(): void
    {
        config(['catalog.oasis.key' => 'test-key', 'catalog.oasis.request_interval_ms' => 0]);
        Http::preventStrayRequests();
        Http::fake(['api.oasiscatalog.com/*' => Http::response([[
            'id' => 'oasis-1', 'name' => 'Кружка', 'article' => 'ABC', 'price' => '3000',
            'total_stock' => 50, 'images' => [['big' => 'https://example.com/mug.jpg']],
        ]])]);
        $missing = Product::factory()->create();
        $arte = Product::factory()->create(['provider' => 'arte']);
        $this->artisan('catalog:sync oasis')->assertSuccessful();
        $this->artisan('catalog:sync oasis')->assertSuccessful();
        $this->assertDatabaseCount('products', 3);
        $this->assertFalse($missing->fresh()->active);
        $this->assertTrue($arte->fresh()->active);
        $this->assertDatabaseHas('products', ['external_id' => 'oasis-1', 'currency' => 'KZT', 'price' => 3000]);
        Http::assertSent(fn ($request) => $request['currency'] === 'kzt' && $request->hasHeader('Authorization', 'Basic '.base64_encode('test-key:')));
    }

    public function test_partial_connection_check_does_not_deactivate_existing_products(): void
    {
        config(['catalog.oasis.key' => 'test-key']);
        Http::fake(['api.oasiscatalog.com/*' => Http::response([['id' => 'new', 'name' => 'Ручка', 'price' => 100]])]);
        $existing = Product::factory()->create();
        $this->artisan('catalog:sync oasis --limit=1')->assertSuccessful();
        $this->assertTrue($existing->fresh()->active);
        $this->assertDatabaseHas('sync_runs', ['provider' => 'oasis', 'status' => 'partial']);
    }

    public function test_provider_failure_preserves_existing_catalog(): void
    {
        $product = Product::factory()->create();
        $this->mock(OasisClient::class, function ($mock) {
            $mock->shouldReceive('products')->once()->andReturn((function (): Generator {
                throw new RuntimeException('Simulated supplier failure');
                yield [];
            })());
        });
        $this->artisan('catalog:sync oasis')->assertFailed();
        $this->assertTrue($product->fresh()->active);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('sync_runs', ['status' => 'failed']);
    }

    public function test_arte_uses_current_stock_price_and_sanitizes_supplier_html(): void
    {
        $item = new SimpleXMLElement('<item><id>123</id><sku>AR-1</sku><product>Термокружка</product><price_kz>999</price_kz><stock>999</stock><category>Посуда</category><general_description><![CDATA[<b>Описание</b>]]></general_description><images_urls>https://example.com/mug.jpg,javascript:bad</images_urls><product_attributes><color name="Цвет">Белый</color></product_attributes></item>');
        $client = new ArteClient;
        $product = $client->normalize($item, ['price' => 3500, 'stock' => 12]);
        $this->assertSame(3500, $product['price']);
        $this->assertSame(12, $product['stock']);
        $this->assertSame('Описание', $product['description']);
        $this->assertSame(['https://example.com/mug.jpg'], $product['images']);
        $this->assertNull($client->normalize($item, null)['price']);
        $this->assertNull($client->normalize($item, null)['stock']);
    }
}
