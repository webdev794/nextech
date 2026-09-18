<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_downloads_a_pdf_bill_for_their_own_order(): void
    {
        Store::create([
            'name' => 'GDP Warehouse', 'line1' => '5 Depot Road', 'line2' => 'Unit 2',
            'city' => 'Brooklyn', 'state' => 'NY', 'postal_code' => '11201', 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $order = $this->order($user);
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Greek Yogurt',
            'sku' => 'GDP-PROD-010',
            'quantity' => 2,
            'unit_price_cents' => 415,
            'compare_at_price_cents' => 519,
            'line_total_cents' => 830,
        ]);

        Sanctum::actingAs($user);

        $response = $this->get("/api/orders/{$order->id}/receipt");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString(
            "bill-order-{$order->id}.pdf",
            (string) $response->headers->get('content-disposition')
        );
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_bill_shows_the_discount_from_the_line_snapshot_or_the_current_product(): void
    {
        $store = Store::create([
            'name' => 'GDP Warehouse', 'line1' => '5 Depot Road',
            'city' => 'Brooklyn', 'state' => 'NY', 'postal_code' => '11201', 'is_active' => true,
        ]);
        $order = $this->order(User::factory()->create());

        // Line A: regular price frozen onto the item at checkout.
        $order->items()->create([
            'product_id' => Product::factory()->create(['category_id' => Category::factory()->create()->id])->id,
            'product_name' => 'Greek Yogurt', 'sku' => 'GDP-PROD-010', 'quantity' => 10,
            'unit_price_cents' => 519, 'compare_at_price_cents' => 649, 'line_total_cents' => 5190,
        ]);

        // Line B: no snapshot (older order) — falls back to the product's current regular price.
        $legacyProduct = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price_cents' => 899, 'compare_at_price_cents' => 1099,
        ]);
        $order->items()->create([
            'product_id' => $legacyProduct->id,
            'product_name' => 'Olive Oil', 'sku' => 'GDP-PROD-013', 'quantity' => 5,
            'unit_price_cents' => 899, 'compare_at_price_cents' => null, 'line_total_cents' => 4495,
        ]);

        $order->load('items.product', 'items.productVariant');
        $html = view('receipts.order', [
            'order' => $order,
            'store' => $store,
            'branding' => Branding::current(),
        ])->render();

        $this->assertStringContainsString('$6.49', $html);   // Line A regular, struck
        $this->assertStringContainsString('$10.99', $html);  // Line B regular via fallback
        $this->assertStringContainsString('You saved', $html);
        // (649-519)*10 + (1099-899)*5 = 1300 + 1000 = 2300
        $this->assertStringContainsString('$23.00', $html);
    }

    public function test_bill_is_issued_from_the_store_recorded_on_the_order(): void
    {
        // A store nearer the delivery address, plus the one that actually took
        // the order (recorded as store_id at checkout). The bill must name the
        // recorded store, not the nearest one.
        Store::create([
            'name' => 'Corner Shop', 'line1' => '1 Near St', 'city' => 'Brooklyn', 'state' => 'NY',
            'postal_code' => '11201', 'latitude' => 40.6782, 'longitude' => -73.9442, 'is_active' => true,
        ]);
        $serving = Store::create([
            'name' => 'Serving Hub', 'line1' => '99 Hub Ave', 'city' => 'Queens', 'state' => 'NY',
            'postal_code' => '11101', 'latitude' => 40.7000, 'longitude' => -73.8000, 'is_active' => true,
        ]);

        $order = $this->order(User::factory()->create(), ['store_id' => $serving->id]);
        $order->delivery_address = [
            'name' => 'C', 'line1' => '10 Main', 'city' => 'Brooklyn', 'state' => 'NY',
            'postal_code' => '11201', 'latitude' => 40.6780, 'longitude' => -73.9440,
        ];
        $order->save();

        $this->assertSame($serving->id, $order->fulfillingStore()?->id);
    }

    public function test_bill_falls_back_to_the_nearest_store_for_a_legacy_order_without_store_id(): void
    {
        $far = Store::create([
            'name' => 'Far Store', 'line1' => '1 Far Rd', 'city' => 'Queens', 'state' => 'NY',
            'postal_code' => '11101', 'latitude' => 41.2000, 'longitude' => -73.2000, 'is_active' => true,
        ]);
        $near = Store::create([
            'name' => 'Near Store', 'line1' => '1 Near Rd', 'city' => 'Brooklyn', 'state' => 'NY',
            'postal_code' => '11201', 'latitude' => 40.6780, 'longitude' => -73.9440, 'is_active' => true,
        ]);

        $order = $this->order(User::factory()->create()); // no store_id
        $order->delivery_address = [
            'name' => 'C', 'line1' => '10 Main', 'city' => 'Brooklyn', 'state' => 'NY',
            'postal_code' => '11201', 'latitude' => 40.6781, 'longitude' => -73.9441,
        ];
        $order->save();

        $this->assertSame($near->id, $order->fulfillingStore()?->id);
        $this->assertNotSame($far->id, $order->fulfillingStore()?->id);
    }

    public function test_customer_cannot_download_another_customers_bill(): void
    {
        $order = $this->order(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->get("/api/orders/{$order->id}/receipt")->assertNotFound();
    }

    public function test_guest_cannot_download_a_bill(): void
    {
        $order = $this->order(User::factory()->create());

        $this->get("/api/orders/{$order->id}/receipt")->assertUnauthorized();
    }

    public function test_bill_is_unavailable_while_a_card_payment_is_still_pending(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, ['payment_status' => 'pending', 'status' => 'pending_payment']);

        Sanctum::actingAs($user);

        $this->get("/api/orders/{$order->id}/receipt")->assertForbidden();
    }

    public function test_cash_on_delivery_bill_is_available_before_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, ['payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'confirmed']);

        Sanctum::actingAs($user);

        $this->get("/api/orders/{$order->id}/receipt")->assertOk();
    }

    public function test_no_bill_for_a_cancelled_order(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, ['payment_method' => 'cod', 'payment_status' => 'pending', 'status' => 'cancelled']);

        Sanctum::actingAs($user);

        $this->get("/api/orders/{$order->id}/receipt")->assertForbidden();
    }

    private function order(User $user, array $attributes = []): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal_cents' => 830,
            'tax_cents' => 73,
            'delivery_fee_cents' => 299,
            'handling_fee_cents' => 99,
            'small_cart_fee_cents' => 199,
            'total_cents' => 1500,
            'delivery_address' => [
                'name' => 'Test Customer', 'line1' => '10 Main Street',
                'city' => 'Brooklyn', 'state' => 'NY', 'postal_code' => '11201',
                'phone' => '+1 555 0100',
            ],
            'delivery_instructions' => 'Leave at the door',
            ...$attributes,
        ]);
    }
}
