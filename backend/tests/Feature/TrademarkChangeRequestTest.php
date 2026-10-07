<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\Trademark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Changing an approved trademark: blocked while buyers are under returns /
// warranty; otherwise a request admin approves, rejects or asks documents for.
class TrademarkChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Trademark, 2: Product} */
    private function approvedTrademark(): array
    {
        $user = User::factory()->create();
        $seller = Seller::forceCreate([
            'user_id' => $user->id, 'country' => 'US', 'business_type' => 'individual', 'company_name' => 'Acme', 'tax_id' => '12-3456789',
            'registered_line1' => '1 Main St', 'registered_city' => 'Austin', 'registered_state' => 'TX', 'registered_postal_code' => '73301', 'registered_country' => 'US',
            'contact_name' => 'Pat Doe', 'id_type' => 'passport', 'id_number' => 'X1', 'date_of_birth' => '1990-01-01', 'status' => 'approved',
        ]);
        $shop = Shop::forceCreate(['seller_id' => $seller->id, 'name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);
        $trademark = Trademark::create(['shop_id' => $shop->id, 'name' => 'OldBrand', 'registration_number' => 'R1', 'registration_country' => 'US', 'certificate_path' => 'kyc/'.$user->id.'/a.pdf', 'status' => 'approved']);
        $product = Product::factory()->create(['shop_id' => $shop->id, 'status' => 'approved', 'is_active' => true, 'trademark_id' => $trademark->id]);

        return [$user, $trademark, $product];
    }

    public function test_change_is_blocked_while_buyers_are_covered(): void
    {
        [$user, $trademark, $product] = $this->approvedTrademark();
        $order = Order::create(['user_id' => User::factory()->create()->id, 'status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'card',
            'subtotal_cents' => 1000, 'total_cents' => 1000, 'delivery_address' => ['line1' => '1 A St', 'city' => 'B', 'state' => 'TX', 'postal_code' => '73301']]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => 'S1', 'quantity' => 1, 'unit_price_cents' => 1000, 'line_total_cents' => 1000, 'return_days' => 30]);
        Sanctum::actingAs($user);

        $this->postJson("/api/seller/trademarks/{$trademark->id}/change-request", ['name' => 'NewBrand', 'certificate_path' => 'kyc/'.$user->id.'/b.pdf', 'reason' => 'Renamed'])
            ->assertStatus(422);
        $this->assertNull($trademark->fresh()->change_status);
    }

    public function test_request_then_admin_asks_for_documents_then_approves(): void
    {
        [$user, $trademark, $product] = $this->approvedTrademark();
        Sanctum::actingAs($user);
        $this->patchJson("/api/seller/trademarks/{$trademark->id}", ['name' => 'NewBrand'])->assertStatus(422); // approved: request only
        $this->postJson("/api/seller/trademarks/{$trademark->id}/change-request", ['name' => 'NewBrand', 'reason' => 'Renamed'])->assertStatus(422); // certificate needed
        $this->postJson("/api/seller/trademarks/{$trademark->id}/change-request", ['name' => 'NewBrand', 'certificate_path' => 'kyc/'.$user->id.'/b.pdf', 'reason' => 'Renamed'])->assertOk();
        $this->assertSame('OldBrand', $trademark->fresh()->name);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->postJson("/api/admin/trademarks/{$trademark->id}/review", ['decision' => 'request_docs', 'note' => 'Compliance documents with the new brand'])->assertOk();
        $this->assertSame('docs_requested', $trademark->fresh()->change_status);

        $trademark->fresh()->forceFill(['change_status' => 'pending'])->save();
        $this->postJson("/api/admin/trademarks/{$trademark->id}/review", ['decision' => 'approve_change'])->assertOk();
        $this->assertSame('NewBrand', $trademark->fresh()->name);
        $this->assertNull($trademark->fresh()->change_status);
        $this->assertSame('NewBrand', $product->fresh()->trademark->name);
    }
}
