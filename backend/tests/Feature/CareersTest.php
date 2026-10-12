<?php

namespace Tests\Feature;

use App\Models\JobRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CareersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opens_a_role_someone_applies_and_admin_reviews_it(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/careers/roles', ['title' => 'Packer', 'kind' => 'general', 'location' => 'Mohali', 'is_open' => true])->assertOk();
        $role = JobRole::where('title', 'Packer')->firstOrFail();

        // Public: open roles (the default rider role first), and applying.
        $this->app['auth']->forgetGuards();
        $titles = collect($this->getJson('/api/careers/roles')->assertOk()->json('data'))->pluck('title');
        $this->assertSame('Delivery rider', $titles->first());
        $this->assertContains('Packer', $titles);
        $this->postJson("/api/careers/roles/{$role->id}/apply", ['name' => 'Asha', 'email' => 'asha@example.com', 'link' => 'https://example.com/cv'])->assertCreated();
        $rider = JobRole::where('kind', 'rider')->first();
        $this->postJson("/api/careers/roles/{$rider->id}/apply", ['name' => 'X', 'email' => 'x@example.com'])->assertStatus(422); // riders use the rider application

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/notifications')->assertOk()->assertJsonPath('data.job_applications.0.name', 'Asha');
        $app = $this->getJson('/api/admin/careers')->assertOk()->json('data.applications.0.id');
        $this->patchJson("/api/admin/careers/applications/{$app}", ['status' => 'reviewed'])->assertOk();
        $this->getJson('/api/admin/notifications')->assertOk()->assertJsonCount(0, 'data.job_applications');
        // Closed roles leave the Careers page.
        $this->putJson("/api/admin/careers/roles/{$role->id}", ['title' => 'Packer', 'kind' => 'general', 'is_open' => false])->assertOk();
        $this->assertNotContains('Packer', collect($this->getJson('/api/careers/roles')->json('data'))->pluck('title'));
    }

    public function test_a_job_at_a_seller_store_reaches_that_seller(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $seller = \App\Models\Seller::forceCreate([
            'user_id' => User::factory()->create()->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $shop = \App\Models\Shop::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true, 'fulfillment_mode' => 'self', 'market' => 'US']);
        $store = \App\Support\SellerStores::ensure($shop);
        $role = JobRole::create(['title' => 'Packer', 'kind' => 'general', 'is_open' => true, 'store_ids' => [$store->id]]);

        $this->postJson("/api/careers/roles/{$role->id}/apply", ['name' => 'Asha', 'email' => 'asha@example.com'])->assertCreated();
        \Illuminate\Support\Facades\Notification::assertSentTo($seller->user, \App\Notifications\SellerNotice::class);
        Sanctum::actingAs($seller->user);
        $this->getJson('/api/seller/local-delivery')->assertOk()->assertJsonPath('data.job_applications.0.name', 'Asha');
        $this->assertStringContainsString('Acme', (string) $this->getJson('/api/careers/roles')->json('data.1.location'));
    }

    public function test_admin_chat_list_links_a_sellers_chat_to_their_page(): void
    {
        $seller = \App\Models\Seller::forceCreate([
            'user_id' => User::factory()->create()->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $thread = \App\Models\SupportThread::create(['user_id' => $seller->user_id, 'issue_type' => 'seller_local_delivery', 'status' => 'open']);
        $thread->post($seller->user, 'Please turn local delivery back on');
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/support/threads')->assertOk()->assertJsonPath('data.0.seller_id', $seller->id);
        $this->getJson("/api/admin/support/threads/{$thread->id}")->assertOk()->assertJsonPath('data.seller_id', $seller->id);
    }
}
