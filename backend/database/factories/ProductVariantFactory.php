<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'label' => fake()->randomElement(['250 g', '500 g', '1 kg', '2 kg', 'Pack of 6']),
            'sku' => fake()->unique()->bothify('VAR-####??'),
            'price_cents' => fake()->numberBetween(100, 5000),
            'inventory_quantity' => fake()->numberBetween(0, 100),
            'image_url' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
