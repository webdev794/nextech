<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashOnDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_on_delivery_checkout_creates_a_confirmed_unpaid_order_when_enabled(): void
    {
        Setting::put('cod_enabled', true);
        $user = User::factory()->create();
        $product = $this->product(['price_cents' => 1000, 'inventory_quantity' => 5]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2]);

        $this->postJson('/api/checkout', [
            'payment_method' => 'cod',
            'address' => [
                'name' => 'Test Customer', 'line1' => '10 Main Street', 'city' => 'Brooklyn',
                'state' => 'NY', 'postal_code' => '11201',
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.stripe_payment_intent_id', null);
    }

    public function test_cash_on_delivery_checkout_is_rejected_when_disabled(): void
    {
        $user = User::factory()->create();
        $product = $this->product(['inventory_quantity' => 2]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->postJson('/api/checkout', [
            'payment_method' => 'cod',
            'address' => [
                'name' => 'Test Customer', 'line1' => '10 Main Street', 'city' => 'Brooklyn',
                'state' => 'NY', 'postal_code' => '11201',
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['payment_method']);
    }

    public function test_cash_on_delivery_order_advances_through_delivery_without_stripe(): void
    {
        $order = $this->codOrder();
        Sanctum::actingAs($this->admin());

        foreach (['packing', 'ready_for_delivery', 'out_for_delivery', 'completed'] as $status) {
            $this->patchJson("/api/admin/orders/{$order->id}", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'pending']);
    }

    public function test_admin_can_mark_cash_collected(): void
    {
        $order = $this->codOrder(['status' => 'out_for_delivery']);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/orders/{$order->id}", ['cash_collected' => true])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_rider_must_collect_the_cash_before_marking_a_cod_order_delivered(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true]);
        $order = $this->codOrder(['status' => 'out_for_delivery', 'delivery_partner_id' => $rider->id]);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['override' => true, 'note' => 'left at door'])
            ->assertStatus(422);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'out_for_delivery']);

        $this->postJson("/api/rider/orders/{$order->id}/cash-collected")->assertOk();

        $this->postJson("/api/rider/orders/{$order->id}/deliver", ['override' => true, 'note' => 'left at door'])
            ->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed', 'payment_status' => 'paid']);
    }

    public function test_rider_can_report_a_customer_refusing_to_pay_cod(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true]);
        $order = $this->codOrder(['status' => 'out_for_delivery', 'delivery_partner_id' => $rider->id]);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/payment-refused", ['note' => 'Said the price was wrong and drove off'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'status' => 'cancelled', 'payment_status' => 'cancelled',
            'cancelled_by' => 'rider', 'cancel_reason' => 'Said the price was wrong and drove off',
        ]);
    }

    public function test_reporting_a_refusal_requires_a_note(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true]);
        $order = $this->codOrder(['status' => 'out_for_delivery', 'delivery_partner_id' => $rider->id]);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/payment-refused", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('note');
    }

    public function test_a_refusal_cannot_be_reported_before_out_for_delivery(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true]);
        $order = $this->codOrder(['status' => 'ready_for_delivery', 'delivery_partner_id' => $rider->id]);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/payment-refused", ['note' => 'too early'])
            ->assertStatus(422);
    }

    public function test_a_refusal_cannot_be_reported_once_cash_is_already_collected(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true]);
        $order = $this->codOrder(['status' => 'out_for_delivery', 'delivery_partner_id' => $rider->id, 'payment_status' => 'paid']);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/payment-refused", ['note' => 'too late'])
            ->assertStatus(422);
    }

    public function test_a_rider_cannot_report_a_refusal_on_someone_elses_order(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true]);
        $other = User::factory()->create(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true]);
        $order = $this->codOrder(['status' => 'out_for_delivery', 'delivery_partner_id' => $other->id]);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/payment-refused", ['note' => 'not mine'])
            ->assertStatus(404);
    }

    public function test_a_refused_delivery_shows_up_as_a_pending_return_until_the_admin_confirms_it(): void
    {
        $rider = User::factory()->create(['is_rider' => true, 'rider_is_active' => true, 'rider_available' => true]);
        $order = $this->codOrder(['status' => 'out_for_delivery', 'delivery_partner_id' => $rider->id]);
        Sanctum::actingAs($rider);

        $this->postJson("/api/rider/orders/{$order->id}/payment-refused", ['note' => 'refused at door'])->assertOk();

        $this->getJson('/api/rider/orders')->assertOk()
            ->assertJsonPath('data.pending_returns.0.id', $order->id)
            ->assertJsonPath('data.pending_returns.0.reason', 'refused at door');

        Sanctum::actingAs($this->admin());
        $this->patchJson("/api/admin/orders/{$order->id}", ['items_returned' => true])
            ->assertOk()
            ->assertJsonPath('data.items_returned_at', fn ($v) => $v !== null);

        Sanctum::actingAs($rider);
        $this->getJson('/api/rider/orders')->assertOk()
            ->assertJsonCount(0, 'data.pending_returns');
    }

    public function test_items_returned_flag_is_ignored_unless_the_order_needs_it(): void
    {
        $order = $this->codOrder(['status' => 'confirmed']);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/orders/{$order->id}", ['items_returned' => true])
            ->assertOk()
            ->assertJsonPath('data.items_returned_at', null);
    }

    public function test_cash_collected_flag_is_ignored_for_a_card_order(): void
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal_cents' => 1000, 'tax_cents' => 89, 'delivery_fee_cents' => 599, 'total_cents' => 1688,
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'B', 'state' => 'NY', 'postal_code' => '11201'],
        ]);
        Sanctum::actingAs($this->admin());

        $this->patchJson("/api/admin/orders/{$order->id}", ['cash_collected' => true, 'courier_name' => 'Sam'])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_customer_can_switch_an_unpaid_card_order_to_cash_on_delivery(): void
    {
        Setting::put('cod_enabled', true);
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_method' => 'card',
            'subtotal_cents' => 1000, 'tax_cents' => 89, 'delivery_fee_cents' => 599, 'total_cents' => 1688,
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'B', 'state' => 'NY', 'postal_code' => '11201'],
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/orders/{$order->id}/payment-method", ['payment_method' => 'cod'])
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_switching_to_cod_is_refused_when_disabled_or_already_paid(): void
    {
        $user = User::factory()->create();
        $paid = $this->codOrder(['user_id' => $user->id, 'payment_method' => 'card', 'payment_status' => 'paid']);
        Sanctum::actingAs($user);

        // disabled toggle
        $pending = Order::create([
            'user_id' => $user->id, 'status' => 'pending_payment', 'payment_status' => 'pending', 'payment_method' => 'card',
            'subtotal_cents' => 1000, 'tax_cents' => 89, 'delivery_fee_cents' => 599, 'total_cents' => 1688,
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'B', 'state' => 'NY', 'postal_code' => '11201'],
        ]);
        $this->patchJson("/api/orders/{$pending->id}/payment-method", ['payment_method' => 'cod'])->assertStatus(422);

        // already paid
        Setting::put('cod_enabled', true);
        $this->patchJson("/api/orders/{$paid->id}/payment-method", ['payment_method' => 'cod'])->assertStatus(422);
    }

    public function test_payment_intent_is_refused_for_a_cash_on_delivery_order(): void
    {
        $user = User::factory()->create();
        $order = $this->codOrder(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payment-intent")->assertStatus(422);
    }

    public function test_admin_can_read_and_update_the_cod_setting(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.cod_enabled', false);

        $this->patchJson('/api/admin/settings', ['cod_enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.cod_enabled', true);

        $this->assertTrue((bool) Setting::get('cod_enabled'));
    }

    public function test_a_normal_user_cannot_reach_the_settings_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->patchJson('/api/admin/settings', ['cod_enabled' => true])->assertForbidden();
    }

    public function test_public_config_reports_cod_availability(): void
    {
        $this->getJson('/api/config')->assertOk()->assertJsonPath('data.cod_enabled', false);

        Setting::put('cod_enabled', true);

        $this->getJson('/api/config')->assertOk()->assertJsonPath('data.cod_enabled', true);
    }

    private function product(array $attributes = []): Product
    {
        $category = Category::factory()->create();

        return Product::factory()->create(['category_id' => $category->id, ...$attributes]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function codOrder(array $overrides = []): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'subtotal_cents' => 1000, 'tax_cents' => 89, 'delivery_fee_cents' => 599, 'total_cents' => 1688,
            'delivery_address' => [
                'name' => 'Test Customer', 'line1' => '10 Main Street',
                'city' => 'Brooklyn', 'state' => 'NY', 'postal_code' => '11201',
            ],
            ...$overrides,
        ]);
    }
}
