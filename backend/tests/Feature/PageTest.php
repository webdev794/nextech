<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_index_lists_only_published_pages(): void
    {
        Page::create(['slug' => 'privacy', 'title' => 'Privacy', 'content' => '# hi', 'footer_group' => 'legal', 'sort_order' => 1]);
        Page::create(['slug' => 'draft', 'title' => 'Draft', 'content' => 'wip', 'is_published' => false]);

        $this->getJson('/api/pages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'privacy');
    }

    public function test_public_show_returns_content_for_a_published_page_and_404_otherwise(): void
    {
        Page::create(['slug' => 'terms', 'title' => 'Terms', 'content' => '## Terms\n\nBody.']);
        Page::create(['slug' => 'hidden', 'title' => 'Hidden', 'content' => 'x', 'is_published' => false]);

        $this->getJson('/api/pages/terms')
            ->assertOk()
            ->assertJsonPath('data.title', 'Terms')
            ->assertJsonPath('data.content', '## Terms\n\nBody.');

        $this->getJson('/api/pages/hidden')->assertNotFound();
        $this->getJson('/api/pages/nope')->assertNotFound();
    }

    public function test_admin_creates_a_page_and_slug_is_generated_and_deduped(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        $first = $this->postJson('/api/admin/pages', ['title' => 'Return Policy'])
            ->assertCreated()->json('data');
        $this->assertSame('return-policy', $first['slug']);

        $second = $this->postJson('/api/admin/pages', ['title' => 'Return Policy'])
            ->assertCreated()->json('data');
        $this->assertSame('return-policy-2', $second['slug']);
    }

    public function test_admin_updates_and_deletes_a_page(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $page = Page::create(['slug' => 'faqs', 'title' => 'FAQs', 'content' => 'old']);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/pages/{$page->id}", ['content' => 'new body', 'is_published' => false])
            ->assertOk()
            ->assertJsonPath('data.content', 'new body')
            ->assertJsonPath('data.is_published', false);

        $this->deleteJson("/api/admin/pages/{$page->id}")->assertNoContent();
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_admin_page_slug_must_be_url_safe(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/pages', ['title' => 'X', 'slug' => 'Not A Slug'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_admin_saves_structured_sections_and_the_public_page_returns_them(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        $sections = [
            ['type' => 'hero', 'image_url' => '/img/about.png', 'heading' => 'Our story', 'text' => 'Since 2020.', 'button_label' => 'Shop', 'button_url' => '/'],
            ['type' => 'media_text', 'image_side' => 'right', 'heading' => 'Fresh daily', 'markdown' => 'We **restock** every morning.'],
            ['type' => 'feature_grid', 'heading' => 'Why us', 'items' => [
                ['title' => 'Fast', 'text' => '10 minutes'],
                ['title' => 'Fair', 'text' => 'Honest prices'],
            ]],
            ['type' => 'stats', 'heading' => 'Numbers', 'items' => [['title' => '~10 min', 'text' => 'Average delivery']]],
            ['type' => 'steps', 'heading' => 'How it works', 'items' => [['title' => 'Order', 'text' => 'Fill your basket']]],
            ['type' => 'faq', 'heading' => 'Common questions', 'items' => [['title' => 'How fast?', 'text' => 'About **10 minutes**.']]],
            ['type' => 'quote', 'text' => 'Faster than walking to the shop.', 'author' => 'Priya M.'],
        ];

        $page = $this->postJson('/api/admin/pages', ['title' => 'About us', 'banner_image' => '/img/pages/about-hero.jpg', 'sections' => $sections])
            ->assertCreated()
            ->assertJsonPath('data.banner_image', '/img/pages/about-hero.jpg')
            ->assertJsonPath('data.sections.0.type', 'hero')
            ->assertJsonPath('data.sections.2.items.1.title', 'Fair')
            ->assertJsonPath('data.sections.3.type', 'stats')
            ->assertJsonPath('data.sections.5.type', 'faq')
            ->assertJsonPath('data.sections.6.author', 'Priya M.')
            ->json('data');

        $this->getJson("/api/pages/{$page['slug']}")
            ->assertOk()
            ->assertJsonPath('data.banner_image', '/img/pages/about-hero.jpg')
            ->assertJsonPath('data.sections.1.image_side', 'right')
            ->assertJsonPath('data.sections.1.heading', 'Fresh daily')
            ->assertJsonPath('data.sections.4.items.0.title', 'Order')
            ->assertJsonPath('data.sections.5.items.0.title', 'How fast?')
            ->assertJsonCount(7, 'data.sections');
    }

    public function test_a_section_with_an_unknown_type_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/pages', ['title' => 'X', 'sections' => [['type' => 'raw_html', 'markdown' => '<script>alert(1)</script>']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sections.0.type']);
    }

    public function test_a_page_without_sections_still_returns_an_empty_array(): void
    {
        Page::create(['slug' => 'plain', 'title' => 'Plain', 'content' => 'body']);

        $this->getJson('/api/pages/plain')
            ->assertOk()
            ->assertJsonPath('data.sections', []);
    }

    public function test_non_admin_cannot_manage_pages(): void
    {
        $this->getJson('/api/admin/pages')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/admin/pages', ['title' => 'Nope'])->assertForbidden();
    }
}
