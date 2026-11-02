<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'oasis', 'external_id' => fake()->unique()->uuid(),
            'article' => fake()->unique()->bothify('MP-####'), 'name' => 'Термокружка для бренда',
            'search_text' => 'термокружка для бренда mp-123', 'category' => 'drinkware',
            'brand' => 'Test Brand', 'price' => 2500, 'currency' => 'KZT', 'stock' => 100,
            'description' => 'Товар для проверки каталога',
            'images' => ['https://example.com/product.jpg'], 'attributes' => [], 'active' => true,
            'synced_at' => now(),
        ];
    }
}
