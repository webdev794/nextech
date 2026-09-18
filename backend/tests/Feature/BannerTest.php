<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_returns_active_banners_in_order(): void
    {
        Banner::create(['image_url' => 'https://x/c.jpg', 'sort_order' => 3]);
        Banner::create(['image_url' => 'https://x/a.jpg', 'sort_order' => 1]);
        Banner::create(['image_url' => 'https://x/b.jpg', 'sort_order' => 2]);
        Banner::create(['image_url' => 'https://x/hidden.jpg', 'sort_order' => 0, 'is_active' => false]);

        $this->getJson('/api/config')
            ->assertOk()
            ->assertJsonPath('data.banners.0.image_url', 'https://x/a.jpg')
            ->assertJsonPath('data.banners.1.image_url', 'https://x/b.jpg')
            ->assertJsonPath('data.banners.2.image_url', 'https://x/c.jpg')
            ->assertJsonCount(3, 'data.banners');
    }

    public function test_admin_can_create_update_and_delete_a_banner(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $category = Category::factory()->create(['slug' => 'snacks']);
        Sanctum::actingAs($admin);

        $id = $this->postJson('/api/admin/banners', [
            'image_url' => 'https://x/promo.jpg',
            'headline' => 'Big weekend sale',
            'category_slug' => 'snacks',
            'placement' => 'strip',
            'sort_order' => 2,
        ])->assertCreated()
            ->assertJsonPath('data.placement', 'strip')
            ->json('data.id');

        $this->postJson('/api/admin/banners', ['image_url' => 'https://x/b.jpg', 'placement' => 'sidebar'])
            ->assertUnprocessable()->assertJsonValidationErrors(['placement']);

        $this->patchJson("/api/admin/banners/{$id}", ['is_active' => false, 'placement' => 'hero'])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.placement', 'hero');

        $this->deleteJson("/api/admin/banners/{$id}")->assertNoContent();
        $this->assertDatabaseCount('banners', 0);
    }

    public function test_banner_category_slug_must_exist(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/banners', [
            'image_url' => 'https://x/promo.jpg',
            'category_slug' => 'does-not-exist',
        ])->assertUnprocessable()->assertJsonValidationErrors(['category_slug']);
    }

    public function test_non_admin_cannot_manage_banners(): void
    {
        $this->getJson('/api/admin/banners')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/admin/banners', ['image_url' => 'https://x/p.jpg'])->assertForbidden();
    }
}
