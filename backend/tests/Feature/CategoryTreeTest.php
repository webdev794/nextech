<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    // Subcategories take their parent's kind; a category page lists its subcategories' products too.
    public function test_subcategories_inherit_kind_and_parents_include_their_products(): void
    {
        $digital = Category::factory()->create(['name' => 'Digital stuff', 'slug' => 'digital-stuff', 'kind' => 'digital']);
        $games = Category::factory()->create(['name' => 'Games', 'slug' => 'games-x', 'parent_id' => $digital->id, 'kind' => 'physical']);
        $arcade = Category::factory()->create(['name' => 'Arcade', 'slug' => 'arcade-x', 'parent_id' => $games->id]);
        $this->assertSame('digital', $games->fresh()->kind);
        $this->assertSame('Digital stuff › Games › Arcade', $arcade->fresh()->path());

        Product::factory()->create(['category_id' => $arcade->id, 'name' => 'Pac Thing', 'status' => 'approved', 'is_active' => true]);
        $this->getJson('/api/products?category=digital-stuff')->assertOk()->assertJsonFragment(['name' => 'Pac Thing']);
        $this->getJson('/api/categories')->assertOk()->assertJsonFragment(['slug' => 'digital-stuff']);
    }

    public function test_admin_tree_rules(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $a = Category::factory()->create(['slug' => 'a1']);
        $b = Category::factory()->create(['slug' => 'b1', 'parent_id' => $a->id]);
        $c = Category::factory()->create(['slug' => 'c1', 'parent_id' => $b->id]);

        $this->postJson('/api/admin/categories', ['name' => 'Too deep', 'parent_id' => $c->id])->assertStatus(422);
        $this->patchJson("/api/admin/categories/{$a->id}", ['parent_id' => $c->id])->assertStatus(422);
        $this->deleteJson("/api/admin/categories/{$a->id}")->assertStatus(409);
        $this->getJson('/api/admin/categories')->assertOk()->assertJsonPath('data.2.depth', 2);
    }
}
