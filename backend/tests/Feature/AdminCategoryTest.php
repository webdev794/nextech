<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_list_categories_with_product_counts(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/categories', ['name' => 'Frozen Foods', 'sort_order' => 5])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'frozen-foods');

        $this->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Frozen Foods')
            ->assertJsonPath('data.0.products_count', 0);
    }

    public function test_admin_can_deactivate_a_category(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/categories/{$category->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);
        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/categories/{$category->id}")->assertStatus(409);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_empty_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();
        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/admin/categories/{$category->id}")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_non_admin_cannot_manage_categories(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/admin/categories', ['name' => 'X'])->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }
}
