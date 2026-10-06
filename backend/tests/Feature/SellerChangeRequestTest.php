<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): Seller
    {
        return Seller::forceCreate([
            'user_id' => User::factory()->create()->id,
            'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX',
            'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X123', 'date_of_birth' => '1990-01-01',
            'status' => 'pending',
        ]);
    }

    // Temu-style: the admin ticks the exact items to fix, each with an optional note.
    public function test_admin_sends_back_specific_items(): void
    {
        $seller = $this->seller();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/sellers/{$seller->id}/request-changes", ['items' => [
            ['key' => 'id_document', 'note' => 'Photo is blurry'],
            ['key' => 'registered_address'],
        ]])->assertOk()->assertJsonPath('data.status', 'needs_changes')->assertJsonPath('data.change_items.0.note', 'Photo is blurry');

        $seller->refresh();
        $this->assertSame([['key' => 'id_document', 'note' => 'Photo is blurry'], ['key' => 'registered_address', 'note' => null]], $seller->change_items);
        $this->assertNull($seller->rejection_reason);
    }

    public function test_items_or_a_message_are_required_and_keys_are_checked(): void
    {
        $seller = $this->seller();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/sellers/{$seller->id}/request-changes", [])->assertStatus(422);
        $this->postJson("/api/admin/sellers/{$seller->id}/request-changes", ['items' => [['key' => 'password']]])->assertStatus(422);
        $this->postJson("/api/admin/sellers/{$seller->id}/request-changes", ['reason' => 'Fix the name'])->assertOk();
        $this->assertNull($seller->fresh()->change_items);
    }
}
