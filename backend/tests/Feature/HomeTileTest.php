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

    // The homepage category carousel is every active category ticked "Show on
    // homepage", in the categories' own order (it replaced the separate tile list).
    public function test_config_lists_categories_shown_on_the_homepage(): void
    {
        Category::factory()->create(['name' => 'Phones', 'slug' => 'phones', 'image_url' => 'https://x/phones.png', 'show_on_home' => true, 'sort_order' => 2]);
        Category::factory()->create(['name' => 'Laptops', 'slug' => 'laptops', 'image_url' => 'https://x/laptops.png', 'show_on_home' => true, 'sort_order' => 1]);
        Category::factory()->create(['name' => 'Cables', 'slug' => 'cables', 'show_on_home' => false]);
        Category::factory()->create(['name' => 'Retired', 'slug' => 'retired', 'show_on_home' => true, 'is_active' => false]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonCount(2, 'data.home_tiles')
            ->assertJsonPath('data.home_tiles.0.title', 'Laptops')
            ->assertJsonPath('data.home_tiles.0.category_slug', 'laptops')
            ->assertJsonPath('data.home_tiles.0.image_url', 'https://x/laptops.png')
            ->assertJsonPath('data.home_tiles.1.title', 'Phones');
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
