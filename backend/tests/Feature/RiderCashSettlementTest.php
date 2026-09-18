<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderCashSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function codOrder(User $rider, int $totalCents, bool $paid = true): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'delivery_partner_id' => $rider->id,
            'status' => 'out_for_delivery',
            'payment_status' => $paid ? 'paid' : 'pending',
            'payment_method' => 'cod',
            'subtotal_cents' => $totalCents, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => $totalCents,
            'delivery_address' => ['name' => 'C', 'line1' => '1 St', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10001'],
        ]);
    }

    public function test_rider_is_holding_the_cash_collected_but_not_yet_settled(): void
    {
        $rider = User::factory()->create(['is_rider' => true]);
        $this->codOrder($rider, 1000);
        $this->codOrder($rider, 500);
        $this->codOrder($rider, 2000, paid: false); // not collected yet — excluded

        $this->assertSame(1500, $rider->codHoldingCents());
    }

    public function test_admin_settles_the_riders_full_holding_balance(): void
    {
        $rider = User::factory()->create(['is_rider' => true]);
        $a = $this->codOrder($rider, 1000);
        $b = $this->codOrder($rider, 500);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/riders/{$rider->id}/cash-settle")
            ->assertOk()
            ->assertJsonPath('data.settled_cents', 1500)
            ->assertJsonPath('data.holding_cents', 0);

        $this->assertSame(0, $rider->codHoldingCents());
        $this->assertNotNull($a->fresh()->cash_settled_at);
        $this->assertNotNull($b->fresh()->cash_settled_at);
    }

    public function test_settling_with_nothing_to_settle_is_rejected(): void
    {
        $rider = User::factory()->create(['is_rider' => true]);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson("/api/admin/riders/{$rider->id}/cash-settle")->assertStatus(422);
    }

    public function test_the_admin_rider_list_shows_the_holding_balance(): void
    {
        $rider = User::factory()->create(['is_rider' => true]);
        $this->codOrder($rider, 750);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->getJson('/api/admin/riders')
            ->assertOk()
            ->assertJsonFragment(['cash_holding_cents' => 750]);
    }

    public function test_the_rider_dashboard_shows_their_own_holding_balance(): void
    {
        $rider = User::factory()->create(['is_rider' => true]);
        $this->codOrder($rider, 1200);

        Sanctum::actingAs($rider);

        $this->getJson('/api/rider/stats')
            ->assertOk()
            ->assertJsonPath('data.cod_holding_cents', 1200);
    }
}
