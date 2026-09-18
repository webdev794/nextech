<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\DeliveryHandoverCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryOtpTest extends TestCase
{
    use RefreshDatabase;

    private function setup2(): array
    {
        $customer = User::factory()->create();
        $rider = User::factory()->create(['is_rider' => true]);
        $order = Order::create([
            'user_id' => $customer->id, 'delivery_partner_id' => $rider->id,
            'status' => 'out_for_delivery', 'payment_status' => 'paid', 'payment_method' => 'card',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
        ]);

        return [$customer, $rider, $order];
    }

    public function test_rider_sends_a_code_and_delivers_with_it(): void
    {
        Notification::fake();
        [$customer, $rider, $order] = $this->setup2();

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$order->id}/delivery-otp")
            ->assertOk()
            ->assertJsonPath('data.sent', true);

        Notification::assertSentOnDemand(DeliveryHandoverCode::class);

        $code = $order->fresh()->delivery_code;
        $this->assertNotNull($code);

        // The customer can read the code off their own order.
        Sanctum::actingAs($customer);
        $this->getJson("/api/orders/{$order->id}")->assertJsonPath('data.delivery_code', $code);

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['code' => $code])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $fresh = $order->fresh();
        $this->assertTrue($fresh->delivery_verified);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNull($fresh->delivery_code);
    }

    public function test_wrong_or_expired_code_is_rejected(): void
    {
        [, $rider, $order] = $this->setup2();
        $order->forceFill(['delivery_code' => '123456', 'delivery_code_expires_at' => now()->addMinutes(10)])->save();

        Sanctum::actingAs($rider);
        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $order->forceFill(['delivery_code_expires_at' => now()->subMinute()])->save();
        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['code' => '123456'])
            ->assertUnprocessable();

        $this->assertSame('out_for_delivery', $order->fresh()->status);
    }

    public function test_rider_can_mark_delivered_without_a_code_but_it_is_recorded(): void
    {
        [, $rider, $order] = $this->setup2();
        Sanctum::actingAs($rider);

        // Override needs a note.
        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['override' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('note');

        $this->postJson("/api/rider/orders/{$order->id}/deliver", [
            'override' => true, 'note' => 'Customer email bouncing; handed over in person, ID checked.',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        $fresh = $order->fresh();
        $this->assertFalse($fresh->delivery_verified);
        $this->assertStringContainsString('ID checked', $fresh->delivery_note);
    }

    public function test_cannot_request_a_code_before_pickup_or_deliver_a_non_out_order(): void
    {
        [, $rider, $order] = $this->setup2();
        $order->forceFill(['status' => 'ready_for_delivery'])->save();
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/delivery-otp")->assertUnprocessable();
        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['override' => true, 'note' => 'x'])->assertUnprocessable();
    }

    public function test_another_rider_cannot_deliver_or_request_a_code(): void
    {
        [, , $order] = $this->setup2();
        Sanctum::actingAs(User::factory()->create(['is_rider' => true]));

        $this->postJson("/api/rider/orders/{$order->id}/delivery-otp")->assertNotFound();
        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['code' => '1'])->assertNotFound();
    }

    public function test_status_endpoint_no_longer_completes_an_order(): void
    {
        [, $rider, $order] = $this->setup2();
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/status", ['status' => 'completed'])
            ->assertUnprocessable();
    }
}
