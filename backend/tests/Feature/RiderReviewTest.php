<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\RiderReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_rates_their_rider_and_the_average_updates(): void
    {
        $rider = $this->rider();
        $order = $this->order($rider, ['status' => 'completed']);

        Sanctum::actingAs(User::find($order->user_id));
        $this->postJson("/api/orders/{$order->id}/rider-review", [
            'rating' => 5,
            'comment' => 'Super friendly, waited while I found my keys.',
        ])->assertOk()->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('rider_reviews', [
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'rating' => 5,
        ]);
        $this->assertEquals(5.0, (float) $rider->fresh()->rider_rating_avg);
        $this->assertSame(1, (int) $rider->fresh()->rider_rating_count);
    }

    public function test_a_second_submission_edits_the_same_review(): void
    {
        $rider = $this->rider();
        $order = $this->order($rider, ['status' => 'completed']);
        Sanctum::actingAs(User::find($order->user_id));

        $this->postJson("/api/orders/{$order->id}/rider-review", ['rating' => 2])->assertOk();
        $this->postJson("/api/orders/{$order->id}/rider-review", ['rating' => 4, 'comment' => 'On reflection, fine.'])->assertOk();

        $this->assertSame(1, RiderReview::where('order_id', $order->id)->count());
        $this->assertEquals(4.0, (float) $rider->fresh()->rider_rating_avg);
    }

    public function test_the_written_comment_is_never_exposed_to_the_rider(): void
    {
        $rider = $this->rider();
        $order = $this->order($rider, ['status' => 'completed']);

        Sanctum::actingAs(User::find($order->user_id));
        $this->postJson("/api/orders/{$order->id}/rider-review", [
            'rating' => 3,
            'comment' => 'secret note for the office',
        ])->assertOk();

        Sanctum::actingAs($rider->fresh());
        $stats = $this->getJson('/api/rider/stats')->assertOk();
        $stats->assertJsonPath('data.rating_count', 1)
            ->assertJsonPath('data.recent_ratings.0.rating', 3);
        $this->assertArrayNotHasKey('comment', $stats->json('data.recent_ratings.0'));
        $stats->assertDontSee('secret note for the office');

        $this->getJson('/api/rider/orders')->assertOk()->assertDontSee('secret note for the office');
    }

    public function test_admin_sees_the_comment_on_the_rider_detail(): void
    {
        $rider = $this->rider();
        $order = $this->order($rider, ['status' => 'completed']);

        Sanctum::actingAs(User::find($order->user_id));
        $this->postJson("/api/orders/{$order->id}/rider-review", [
            'rating' => 4,
            'comment' => 'left it with the neighbour as asked',
        ])->assertOk();

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson("/api/admin/riders/{$rider->id}")
            ->assertOk()
            ->assertJsonPath('data.rider.rating_avg', 4)
            ->assertJsonPath('data.reviews.0.comment', 'left it with the neighbour as asked');
    }

    public function test_you_cannot_rate_someone_elses_order(): void
    {
        $rider = $this->rider();
        $order = $this->order($rider, ['status' => 'completed']);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/orders/{$order->id}/rider-review", ['rating' => 1])->assertNotFound();
    }

    public function test_an_order_with_no_rider_or_still_in_the_store_cannot_be_rated(): void
    {
        $noRider = Order::create([
            'user_id' => User::factory()->create()->id,
            'payment_status' => 'paid', 'payment_method' => 'card',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'status' => 'completed',
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
        ]);
        Sanctum::actingAs(User::find($noRider->user_id));
        $this->postJson("/api/orders/{$noRider->id}/rider-review", ['rating' => 5])->assertStatus(422);

        $rider = $this->rider();
        $early = $this->order($rider, ['status' => 'ready_for_delivery']);
        Sanctum::actingAs(User::find($early->user_id));
        $this->postJson("/api/orders/{$early->id}/rider-review", ['rating' => 5])->assertStatus(422);
    }

    public function test_rating_is_bounded_to_one_through_five(): void
    {
        $rider = $this->rider();
        $order = $this->order($rider, ['status' => 'completed']);
        Sanctum::actingAs(User::find($order->user_id));

        $this->postJson("/api/orders/{$order->id}/rider-review", ['rating' => 6])->assertStatus(422);
        $this->postJson("/api/orders/{$order->id}/rider-review", ['rating' => 0])->assertStatus(422);
    }

    public function test_rider_stats_summarises_deliveries_and_rating(): void
    {
        $rider = $this->rider();
        $this->order($rider, ['status' => 'completed', 'delivered_at' => now()->subDays(1), 'delivery_verified' => true]);
        $this->order($rider, ['status' => 'completed', 'delivered_at' => now()->subDays(3), 'delivery_verified' => false]);
        $this->order($rider, ['status' => 'completed', 'delivered_at' => now()->subDays(20)]);
        $this->order($rider, ['status' => 'out_for_delivery']);

        Sanctum::actingAs($rider);
        $this->getJson('/api/rider/stats')
            ->assertOk()
            ->assertJsonPath('data.deliveries_total', 3)
            ->assertJsonPath('data.deliveries_week', 2)
            ->assertJsonPath('data.deliveries_month', 3);
    }

    private function rider(): User
    {
        return User::factory()->create(['is_rider' => true]);
    }

    private function order(User $rider, array $overrides): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'payment_status' => 'paid', 'payment_method' => 'card',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_partner_id' => $rider->id,
            'delivery_address' => ['name' => 'X', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
            ...$overrides,
        ]);
    }
}
