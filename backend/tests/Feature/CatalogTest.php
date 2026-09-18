<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_active_categories(): void
    {
        Category::factory()->create([
            'name' => 'Fresh Produce',
            'slug' => 'fresh-produce',
            'sort_order' => 1,
        ]);
        Category::factory()->create([
            'name' => 'Hidden Category',
            'slug' => 'hidden-category',
            'is_active' => false,
        ]);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'fresh-produce');
    }

    public function test_customer_can_view_active_products_by_category(): void
    {
        $category = Category::factory()->create([
            'slug' => 'fresh-produce',
        ]);
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Bananas',
            'slug' => 'bananas',
        ]);
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Hidden Product',
            'slug' => 'hidden-product',
            'is_active' => false,
        ]);

        $this->getJson('/api/products?category=fresh-produce')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'bananas')
            ->assertJsonCount(1, 'data');
    }

    public function test_inactive_product_is_not_publicly_accessible(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $this->getJson("/api/products/{$product->slug}")
            ->assertNotFound();
    }

    public function test_customer_can_search_products_by_name_description_or_sku(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Organic Blueberries',
            'slug' => 'organic-blueberries',
            'sku' => 'GDP-BERRIES',
            'description' => 'Fresh berries for breakfast',
        ]);
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Whole Wheat Bread',
            'slug' => 'whole-wheat-bread',
        ]);

        $this->getJson('/api/products?search=berries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'organic-blueberries');

        $this->getJson('/api/products?search=GDP-BERRIES')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'organic-blueberries');
    }

    public function test_search_term_must_have_at_least_two_characters(): void
    {
        $this->getJson('/api/products?search=a')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['search']);
    }
}