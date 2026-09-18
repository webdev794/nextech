<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HomeTile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeTileTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_resolves_tiles_against_the_linked_category(): void
    {
        $category = Category::factory()->create(['name' => 'Fresh Produce', 'slug' => 'fresh-produce', 'image_url' => 'https://x/cat.png']);

        // Falls back to the category's name + image.
        HomeTile::create(['category_slug' => 'fresh-produce', 'sort_order' => 2]);
        // Custom title + image override.
        HomeTile::create(['category_slug' => 'fresh-produce', 'title' => 'Weekend fruit deals', 'image_url' => 'https://x/custom.png', 'sort_order' => 1]);
        // Inactive - hidden.
        HomeTile::create(['category_slug' => 'fresh-produce', 'is_active' => false, 'sort_order' => 0]);

        $response = $this->getJson('/api/config')->assertOk();

        $response->assertJsonCount(2, 'data.home_tiles')
            ->assertJsonPath('data.home_tiles.0.title', 'Weekend fruit deals')
            ->assertJsonPath('data.home_tiles.0.image_url', 'https://x/custom.png')
            ->assertJsonPath('data.home_tiles.0.category_slug', 'fresh-produce')
            ->assertJsonPath('data.home_tiles.1.title', 'Fresh Produce')
            ->assertJsonPath('data.home_tiles.1.image_url', 'https://x/cat.png');

        unset($category);
    }

    public function test_a_tile_with_a_missing_category_and_no_link_is_dropped(): void
    {
        HomeTile::create(['category_slug' => 'gone', 'sort_order' => 1]);
        HomeTile::create(['title' => 'Recipes', 'link_url' => 'https://example.com/recipes', 'sort_order' => 2]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonCount(1, 'data.home_tiles')
            ->assertJsonPath('data.home_tiles.0.title', 'Recipes')
            ->assertJsonPath('data.home_tiles.0.link_url', 'https://example.com/recipes');
    }

    public function test_admin_can_manage_tiles(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Category::factory()->create(['slug' => 'snacks']);
        Sanctum::actingAs($admin);

        $id = $this->postJson('/api/admin/home-tiles', ['category_slug' => 'snacks', 'sort_order' => 3])
            ->assertCreated()->json('data.id');

        $this->patchJson("/api/admin/home-tiles/{$id}", ['title' => 'Munchies', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.title', 'Munchies')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/admin/home-tiles/{$id}")->assertNoContent();
        $this->assertDatabaseCount('home_tiles', 0);
    }

    public function test_a_tile_needs_a_category_or_a_link(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/home-tiles', ['title' => 'Orphan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_slug']);
    }

    public function test_non_admin_cannot_manage_tiles(): void
    {
        $this->getJson('/api/admin/home-tiles')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/admin/home-tiles', ['link_url' => 'https://x'])->assertForbidden();
    }
}
