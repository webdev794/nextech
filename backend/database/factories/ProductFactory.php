<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'sku' => fake()->unique()->bothify('SKU-####??'),
            'price_cents' => fake()->numberBetween(100, 5000),
            'inventory_quantity' => fake()->numberBetween(0, 100),
            'image_url' => null,
            'is_active' => true,
        ];
    }
}