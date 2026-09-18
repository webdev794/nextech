<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GiftCard;
use App\Models\GiftCardRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GiftCardTest extends TestCase
{
    use RefreshDatabase;

    private function paidCodOrder(User $customer): Order
    {
        $order = Order::create([
            'user_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'subtotal_cents' => 1500, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1500,
            'delivery_address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
        ]);
        $order->items()->create([
            'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id,
            'product_name' => 'Milk', 'sku' => 'M1', 'quantity' => 1, 'unit_price_cents' => 500, 'line_total_cents' => 500,
        ]);
        $order->items()->create([
            'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id,
            'product_name' => 'Bread', 'sku' => 'B1', 'quantity' => 1, 'unit_price_cents' => 1000, 'line_total_cents' => 1000,
        ]);

        return $order;
    }

    public function test_support_issues_a_gift_card_for_the_missing_items(): void
    {
        $customer = User::factory()->create();
        $order = $this->paidCodOrder($customer);
        $thread = SupportThread::create(['user_id' => $customer->id, 'order_id' => $order->id, 'issue_type' => 'order', 'status' => 'open']);
        $missing = $order->items->firstWhere('product_name', 'Bread');

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $res = $this->postJson("/api/admin/orders/{$order->id}/gift-card", [
            'item_ids' => [$missing->id],
            'support_thread_id' => $thread->id,
        ])->assertCreated();

        $code = $res->json('data.code');
        $pin = $res->json('data.pin');
        $this->assertSame(1000, $res->json('data.amount_cents'));

        $card = GiftCard::where('code', $code)->first();
        $this->assertNotNull($card);
        $this->assertSame($customer->id, $card->user_id);
        $this->assertSame(1000, $card->balance_cents);
        $this->assertTrue(Hash::check($pin, $card->pin_hash));

        // The code + password were posted into the support thread.
        $this->assertTrue($thread->messages()->where('body', 'like', "%{$code}%")->exists());
    }

    public function test_the_order_view_shows_who_issued_the_gift_card(): void
    {
        $customer = User::factory()->create();
        $order = $this->paidCodOrder($customer);
        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Support Agent']);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/orders/{$order->id}/gift-card", ['amount_cents' => 500, 'reason' => 'Late delivery'])
            ->assertCreated();

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.gift_cards.0.reason', 'Late delivery')
            ->assertJsonPath('data.gift_cards.0.issued_by.name', 'Support Agent');
    }

    public function test_admin_support_thread_view_exposes_gift_cards_already_issued(): void
    {
        $customer = User::factory()->create();
        $order = $this->paidCodOrder($customer);
        $thread = SupportThread::create(['user_id' => $customer->id, 'order_id' => $order->id, 'issue_type' => 'order', 'status' => 'open']);
        $missing = $order->items->firstWhere('product_name', 'Bread');

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->postJson("/api/admin/orders/{$order->id}/gift-card", ['item_ids' => [$missing->id]])->assertCreated();

        // The chat drawer's order needs to see the card too, so it can't be re-issued from there.
        $this->getJson("/api/admin/support/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.order.gift_cards')
            ->assertJsonPath('data.order.gift_cards.0.initial_cents', 1000);
    }

    public function test_gift_card_reason_is_posted_as_an_internal_note_hidden_from_the_customer(): void
    {
        $customer = User::factory()->create();
        $order = $this->paidCodOrder($customer);
        $thread = SupportThread::create(['user_id' => $customer->id, 'order_id' => $order->id, 'issue_type' => 'order', 'status' => 'open']);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->postJson("/api/admin/orders/{$order->id}/gift-card", [
            'amount_cents' => 500, 'reason' => 'Goodwill credit — repeat customer', 'support_thread_id' => $thread->id,
        ])->assertCreated();

        // Admin sees both the customer-facing code/password message and the
        // internal-only reason note.
        $this->getJson("/api/admin/support/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.1.internal', true)
            ->assertJsonPath('data.messages.1.body', 'Reason: Goodwill credit — repeat customer');

        // The customer never sees the reason.
        Sanctum::actingAs($customer);
        $this->getJson("/api/support/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonMissing(['body' => 'Reason: Goodwill credit — repeat customer']);
    }

    public function test_admin_support_thread_view_exposes_the_customers_own_spendable_gift_cards(): void
    {
        $customer = User::factory()->create();
        $order = $this->openCodOrder($customer, 1500);
        $thread = SupportThread::create(['user_id' => $customer->id, 'order_id' => $order->id, 'issue_type' => 'other', 'status' => 'open']);
        GiftCard::create([
            'code' => 'GC-SPEND-01', 'pin_hash' => Hash::make('x'), 'user_id' => $customer->id,
            'initial_cents' => 500, 'balance_cents' => 500, 'is_active' => true,
        ]);
        // Spent and deactivated — must not show as available to apply.
        GiftCard::create([
            'code' => 'GC-SPENT-02', 'pin_hash' => Hash::make('x'), 'user_id' => $customer->id,
            'initial_cents' => 200, 'balance_cents' => 0, 'is_active' => false,
        ]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->getJson("/api/admin/support/threads/{$thread->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.user.gift_cards')
            ->assertJsonPath('data.user.gift_cards.0.code', 'GC-SPEND-01');
    }

    public function test_a_second_gift_card_cannot_be_issued_for_the_same_order(): void
    {
        $customer = User::factory()->create();
        $order = $this->paidCodOrder($customer); // total 1500
        $missing = $order->items->firstWhere('product_name', 'Bread');
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/orders/{$order->id}/gift-card", ['item_ids' => [$missing->id]])
            ->assertCreated();

        // Plenty of room left ($5 of the $15 total was credited) — still refused.
        $this->postJson("/api/admin/orders/{$order->id}/gift-card", ['amount_cents' => 100])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'already been issued'));
    }

    public function test_selecting_every_item_issues_store_credit_for_the_full_order_including_fees(): void
    {
        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'subtotal_cents' => 1500, 'tax_cents' => 100, 'delivery_fee_cents' => 300, 'total_cents' => 1900,
            'delivery_address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
        ]);
        $item1 = $order->items()->create([
            'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id,
            'product_name' => 'Milk', 'sku' => 'M1', 'quantity' => 1, 'unit_price_cents' => 500, 'line_total_cents' => 500,
        ]);
        $item2 = $order->items()->create([
            'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id,
            'product_name' => 'Bread', 'sku' => 'B1', 'quantity' => 1, 'unit_price_cents' => 1000, 'line_total_cents' => 1000,
        ]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        // Both items are missing — the credit should cover the whole order
        // (1900), not just the two line totals (1500).
        $res = $this->postJson("/api/admin/orders/{$order->id}/gift-card", [
            'item_ids' => [$item1->id, $item2->id],
        ])->assertCreated();

        $this->assertSame(1900, $res->json('data.amount_cents'));
    }

    public function test_selecting_only_some_items_still_credits_just_their_price(): void
    {
        $customer = User::factory()->create();
        $order = Order::create([
            'user_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'subtotal_cents' => 1500, 'tax_cents' => 100, 'delivery_fee_cents' => 300, 'total_cents' => 1900,
            'delivery_address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
        ]);
        $item1 = $order->items()->create([
            'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id,
            'product_name' => 'Milk', 'sku' => 'M1', 'quantity' => 1, 'unit_price_cents' => 500, 'line_total_cents' => 500,
        ]);
        $order->items()->create([
            'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id,
            'product_name' => 'Bread', 'sku' => 'B1', 'quantity' => 1, 'unit_price_cents' => 1000, 'line_total_cents' => 1000,
        ]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $res = $this->postJson("/api/admin/orders/{$order->id}/gift-card", [
            'item_ids' => [$item1->id],
        ])->assertCreated();

        $this->assertSame(500, $res->json('data.amount_cents'));
    }

    public function test_gift_card_cannot_exceed_what_was_paid(): void
    {
        $customer = User::factory()->create();
        $order = $this->paidCodOrder($customer); // total 1500
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/orders/{$order->id}/gift-card", ['amount_cents' => 2000])
            ->assertStatus(422);
    }

    public function test_customer_checks_their_card_and_wrong_pin_is_rejected(): void
    {
        $customer = User::factory()->create();
        $card = GiftCard::create([
            'code' => 'GC-TEST-0001', 'pin_hash' => Hash::make('secret12'),
            'user_id' => $customer->id, 'initial_cents' => 1000, 'balance_cents' => 1000, 'is_active' => true,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/gift-cards/check', ['code' => 'GC-TEST-0001', 'pin' => 'secret12'])
            ->assertOk()->assertJsonPath('data.balance_cents', 1000);
        $this->postJson('/api/gift-cards/check', ['code' => 'GC-TEST-0001', 'pin' => 'wrong'])
            ->assertStatus(422);

        // Not the owner.
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/gift-cards/check', ['code' => 'GC-TEST-0001', 'pin' => 'secret12'])
            ->assertStatus(422);
    }

    public function test_checkout_applies_the_gift_card_and_keeps_the_remainder(): void
    {
        $customer = User::factory()->create(['phone' => '+1 555 0100']);
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'price_cents' => 1000, 'inventory_quantity' => 10]);
        $card = GiftCard::create([
            'code' => 'GC-GIFT-0002', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $customer->id, 'initial_cents' => 5000, 'balance_cents' => 5000, 'is_active' => true,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2]);
        $res = $this->postJson('/api/checkout', [
            'address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            'gift_card_code' => 'GC-GIFT-0002', 'gift_card_pin' => 'pass1234',
        ])->assertCreated();

        $gross = $res->json('data.subtotal_cents') + $res->json('data.tax_cents')
            + $res->json('data.delivery_fee_cents') + $res->json('data.handling_fee_cents')
            + $res->json('data.small_cart_fee_cents');
        $this->assertSame($gross, $res->json('data.gift_card_discount_cents') + $res->json('data.total_cents'));
        $this->assertSame($gross, $res->json('data.gift_card_discount_cents')); // fully covered here
        $this->assertSame('paid', $res->json('data.payment_status'));
        $this->assertSame('confirmed', $res->json('data.status'));

        $card->refresh();
        $this->assertSame(5000 - $gross, $card->balance_cents);
        $this->assertDatabaseHas('gift_card_redemptions', [
            'gift_card_id' => $card->id, 'order_id' => $res->json('data.id'), 'amount_cents' => $gross,
        ]);
    }

    public function test_checkout_rejects_a_gift_card_belonging_to_another_customer(): void
    {
        $owner = User::factory()->create();
        $card = GiftCard::create([
            'code' => 'GC-GIFT-0007', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $owner->id, 'initial_cents' => 5000, 'balance_cents' => 5000, 'is_active' => true,
        ]);

        $stranger = User::factory()->create(['phone' => '+1 555 0100']);
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'price_cents' => 1000, 'inventory_quantity' => 10]);

        Sanctum::actingAs($stranger);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->postJson('/api/checkout', [
            'address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            'gift_card_code' => 'GC-GIFT-0007', 'gift_card_pin' => 'pass1234', // correct code + pin, wrong customer
        ])->assertStatus(422)->assertJsonValidationErrors('gift_card_code');

        // Nothing was spent — the card the stranger tried to use is untouched.
        $card->refresh();
        $this->assertSame(5000, $card->balance_cents);
    }

    public function test_a_used_up_gift_card_is_rejected_at_the_next_checkout(): void
    {
        $customer = User::factory()->create(['phone' => '+1 555 0100']);
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'price_cents' => 1000, 'inventory_quantity' => 10]);
        GiftCard::create([
            'code' => 'GC-ZERO-0003', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $customer->id, 'initial_cents' => 300, 'balance_cents' => 0, 'is_active' => false,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $this->postJson('/api/checkout', [
            'address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            'gift_card_code' => 'GC-ZERO-0003', 'gift_card_pin' => 'pass1234',
        ])->assertStatus(422);
    }

    public function test_cancelling_an_order_restores_the_gift_card_it_spent(): void
    {
        $customer = User::factory()->create(['phone' => '+1 555 0100']);
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'price_cents' => 1000, 'inventory_quantity' => 10]);
        $card = GiftCard::create([
            'code' => 'GC-GIFT-0004', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $customer->id, 'initial_cents' => 5000, 'balance_cents' => 5000, 'is_active' => true,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2]);
        $res = $this->postJson('/api/checkout', [
            'address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            'gift_card_code' => 'GC-GIFT-0004', 'gift_card_pin' => 'pass1234',
        ])->assertCreated();
        $orderId = $res->json('data.id');
        $spent = $res->json('data.gift_card_discount_cents');

        $card->refresh();
        $this->assertSame(5000 - $spent, $card->balance_cents);

        $this->postJson("/api/orders/{$orderId}/cancel")->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.payment_status', 'refunded'); // gift card fully covered it — nothing left to refund manually

        $card->refresh();
        $this->assertSame(5000, $card->balance_cents);
        $this->assertTrue($card->is_active);
        $this->assertDatabaseHas('gift_card_redemptions', [
            'gift_card_id' => $card->id, 'order_id' => $orderId,
        ]);
        $this->assertNotNull(GiftCardRedemption::where('order_id', $orderId)->first()->reversed_at);
    }

    public function test_admin_cancelling_an_order_restores_the_gift_card_it_spent(): void
    {
        $customer = User::factory()->create(['phone' => '+1 555 0100']);
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'price_cents' => 300, 'inventory_quantity' => 10]);
        $card = GiftCard::create([
            'code' => 'GC-GIFT-0005', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $customer->id, 'initial_cents' => 5000, 'balance_cents' => 5000, 'is_active' => true,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $res = $this->postJson('/api/checkout', [
            'address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            'gift_card_code' => 'GC-GIFT-0005', 'gift_card_pin' => 'pass1234',
        ])->assertCreated();
        $orderId = $res->json('data.id');

        $card->refresh();
        $this->assertLessThan(5000, $card->balance_cents);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson("/api/admin/orders/{$orderId}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'refunded'); // gift card fully covered it

        $card->refresh();
        $this->assertSame(5000, $card->balance_cents);
    }

    public function test_cancelling_a_partially_gift_card_paid_order_still_needs_manual_refund(): void
    {
        $customer = User::factory()->create(['phone' => '+1 555 0100']);
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'price_cents' => 5000, 'inventory_quantity' => 10]);
        $card = GiftCard::create([
            'code' => 'GC-GIFT-0006', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $customer->id, 'initial_cents' => 500, 'balance_cents' => 500, 'is_active' => true,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $res = $this->postJson('/api/checkout', [
            'address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            'gift_card_code' => 'GC-GIFT-0006', 'gift_card_pin' => 'pass1234',
        ])->assertCreated();
        $orderId = $res->json('data.id');
        // The gift card only covers part of a $50 product — real payment is still due,
        // so this order is never "paid" and can't be cancelled via the customer route
        // (that requires a paid or COD order). Simulate the paid state directly, as a
        // completed Stripe checkout would leave it.
        Order::whereKey($orderId)->update(['payment_status' => 'paid']);

        $card->refresh();
        $this->assertSame(0, $card->balance_cents);

        $this->postJson("/api/orders/{$orderId}/cancel")->assertOk()
            ->assertJsonPath('data.payment_status', 'refund_pending'); // real money moved — needs manual refund

        $card->refresh();
        $this->assertSame(500, $card->balance_cents);
    }

    private function openCodOrder(User $customer, int $total = 1500): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'subtotal_cents' => $total, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => $total,
            'delivery_address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
        ]);
    }

    public function test_admin_applies_an_existing_gift_card_to_a_different_open_order(): void
    {
        $customer = User::factory()->create();
        $card = GiftCard::create([
            'code' => 'GC-APPLY-01', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $customer->id, 'initial_cents' => 5000, 'balance_cents' => 5000, 'is_active' => true,
        ]);
        $order = $this->openCodOrder($customer, 1500);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/orders/{$order->id}/apply-gift-card", ['gift_card_code' => 'gc-apply-01'])
            ->assertOk()
            ->assertJsonPath('data.applied_cents', 1500)
            ->assertJsonPath('data.order.payment_status', 'paid')
            ->assertJsonPath('data.order.gift_card_discount_cents', 1500)
            ->assertJsonPath('data.order.total_cents', 0);

        $card->refresh();
        $this->assertSame(3500, $card->balance_cents);
        $this->assertTrue($card->is_active);
        $this->assertDatabaseHas('gift_card_redemptions', ['gift_card_id' => $card->id, 'order_id' => $order->id, 'amount_cents' => 1500]);
    }

    public function test_applying_a_gift_card_that_only_partially_covers_the_order_leaves_it_pending(): void
    {
        $customer = User::factory()->create();
        GiftCard::create([
            'code' => 'GC-APPLY-02', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $customer->id, 'initial_cents' => 500, 'balance_cents' => 500, 'is_active' => true,
        ]);
        $order = $this->openCodOrder($customer, 1500);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/orders/{$order->id}/apply-gift-card", ['gift_card_code' => 'GC-APPLY-02'])
            ->assertOk()
            ->assertJsonPath('data.applied_cents', 500)
            ->assertJsonPath('data.order.payment_status', 'pending') // $10 still due
            ->assertJsonPath('data.order.total_cents', 1000);
    }

    public function test_cannot_apply_a_gift_card_belonging_to_another_customer(): void
    {
        $owner = User::factory()->create();
        GiftCard::create([
            'code' => 'GC-APPLY-03', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $owner->id, 'initial_cents' => 5000, 'balance_cents' => 5000, 'is_active' => true,
        ]);
        $stranger = User::factory()->create();
        $order = $this->openCodOrder($stranger, 1500);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/orders/{$order->id}/apply-gift-card", ['gift_card_code' => 'GC-APPLY-03'])
            ->assertStatus(422);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total_cents' => 1500, 'gift_card_discount_cents' => 0]);
    }

    public function test_cannot_apply_a_gift_card_to_an_order_that_is_not_open(): void
    {
        $customer = User::factory()->create();
        $card = GiftCard::create([
            'code' => 'GC-APPLY-04', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $customer->id, 'initial_cents' => 5000, 'balance_cents' => 5000, 'is_active' => true,
        ]);
        $order = $this->openCodOrder($customer, 1500);
        $order->update(['payment_status' => 'paid']);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/orders/{$order->id}/apply-gift-card", ['gift_card_code' => 'GC-APPLY-04'])
            ->assertStatus(422);

        $card->refresh();
        $this->assertSame(5000, $card->balance_cents);
    }

    public function test_applying_a_gift_card_posts_a_note_into_the_support_thread(): void
    {
        $customer = User::factory()->create();
        GiftCard::create([
            'code' => 'GC-APPLY-05', 'pin_hash' => Hash::make('pass1234'),
            'user_id' => $customer->id, 'initial_cents' => 500, 'balance_cents' => 500, 'is_active' => true,
        ]);
        $order = $this->openCodOrder($customer, 1500);
        $thread = SupportThread::create(['user_id' => $customer->id, 'order_id' => $order->id, 'issue_type' => 'other', 'status' => 'open']);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/orders/{$order->id}/apply-gift-card", [
            'gift_card_code' => 'GC-APPLY-05', 'support_thread_id' => $thread->id,
        ])->assertOk();

        $this->assertTrue($thread->messages()->where('body', 'like', '%GC-APPLY-05%')->exists());
    }
}
