<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Notifications\RiderAssigned;
use App\Support\RiderAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderAssignedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_admin_assignment_notifies_the_rider(): void
    {
        Notification::fake();
        $rider = User::factory()->create(['is_rider' => true]);
        $order = $this->order();

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson("/api/admin/orders/{$order->id}", ['delivery_partner_id' => $rider->id])->assertOk();

        Notification::assertSentTo($rider, RiderAssigned::class);
    }

    public function test_re_saving_the_same_rider_does_not_notify_again(): void
    {
        Notification::fake();
        $rider = User::factory()->create(['is_rider' => true]);
        $order = $this->order(['delivery_partner_id' => $rider->id, 'courier_name' => $rider->name]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson("/api/admin/orders/{$order->id}", ['delivery_partner_id' => $rider->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_clearing_the_rider_notifies_no_one(): void
    {
        Notification::fake();
        $rider = User::factory()->create(['is_rider' => true]);
        $order = $this->order(['delivery_partner_id' => $rider->id, 'courier_name' => $rider->name]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson("/api/admin/orders/{$order->id}", ['delivery_partner_id' => null])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_auto_assignment_notifies_the_chosen_rider(): void
    {
        Notification::fake();

        $store = Store::create([
            'name' => 'Hub', 'line1' => '1 Hub St', 'city' => 'New York', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => 40.7128, 'longitude' => -74.0060,
            'delivery_radius_km' => 10, 'is_active' => true,
        ]);
        $rider = User::factory()->create([
            'is_rider' => true, 'rider_is_active' => true, 'rider_available' => true,
            'rider_base_lat' => 40.7130, 'rider_base_lng' => -74.0058,
        ]);
        $rider->stores()->attach($store->id);

        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'store_id' => $store->id,
            'status' => 'ready_for_delivery', 'payment_status' => 'paid',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'C', 'line1' => '9 Elm', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10002'],
        ]);

        RiderAssignment::assign($order);

        Notification::assertSentTo($rider, RiderAssigned::class);
    }

    private function order(array $overrides = []): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'payment_status' => 'paid', 'payment_method' => 'card',
            'status' => 'confirmed',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'Austin', 'state' => 'TX', 'postal_code' => '73301'],
            ...$overrides,
        ]);
    }
}
