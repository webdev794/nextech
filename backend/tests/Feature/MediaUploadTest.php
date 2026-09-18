<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_a_product_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $response = $this->postJson('/api/admin/media', [
            'file' => UploadedFile::fake()->image('bananas.jpg', 400, 400),
        ])->assertCreated();

        $path = $response->json('data.path');
        $this->assertStringStartsWith('products/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString('/api/media/file/products/', $response->json('data.url'));
    }

    public function test_non_image_and_oversized_files_are_rejected(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('huge.jpg')->size(6000)])
            ->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_a_normal_user_cannot_upload(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->image('x.jpg')])
            ->assertForbidden();
    }
}
