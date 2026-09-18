<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Support\RiderAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function store(): Store
    {
        return Store::create([
            'name' => 'Hub', 'line1' => '1 Hub St', 'city' => 'NYC', 'state' => 'NY',
            'postal_code' => '10001', 'latitude' => 40.7128, 'longitude' => -74.0060,
            'delivery_radius_km' => 15, 'is_active' => true,
        ]);
    }

    private function rider(Store $store, array $overrides = []): User
    {
        $rider = User::factory()->create([
            'is_rider' => true, 'rider_is_active' => true,
            'rider_base_lat' => 40.7130, 'rider_base_lng' => -74.0058,
            ...$overrides,
        ]);
        $rider->stores()->attach($store->id);

        return $rider;
    }

    private function order(Store $store): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'store_id' => $store->id,
            'status' => 'ready_for_delivery', 'payment_status' => 'paid',
            'subtotal_cents' => 1000, 'tax_cents' => 0, 'delivery_fee_cents' => 0, 'total_cents' => 1000,
            'delivery_address' => ['name' => 'C', 'line1' => '9 Elm', 'city' => 'NY', 'state' => 'NY', 'postal_code' => '10002'],
        ]);
    }

    public function test_clock_in_opens_a_shift_and_marks_the_rider_available(): void
    {
        $rider = $this->rider($this->store());
        $this->assertFalse((bool) $rider->fresh()->rider_available);

        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/shift', ['action' => 'clock_in'])
            ->assertOk()
            ->assertJsonPath('data.status', 'clocked_in')
            ->assertJsonPath('data.clocked_in', true)
            ->assertJsonPath('data.available', true);

        $this->assertTrue($rider->fresh()->rider_available);
        $this->assertNotNull($rider->currentShift());
        $this->getJson('/api/rider/orders')->assertJsonPath('data.shift.status', 'clocked_in');
    }

    public function test_cannot_clock_in_twice(): void
    {
        $rider = $this->rider($this->store());
        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/shift', ['action' => 'clock_in'])->assertOk();
        $this->postJson('/api/rider/shift', ['action' => 'clock_in'])->assertStatus(422);
    }

    public function test_break_toggles_availability_without_closing_the_shift(): void
    {
        $rider = $this->rider($this->store());
        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/shift', ['action' => 'clock_in'])->assertOk();

        $this->postJson('/api/rider/shift', ['action' => 'break_start', 'reason' => 'Lunch'])
            ->assertOk()
            ->assertJsonPath('data.status', 'on_break')
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.break_reason', 'Lunch');
        $this->assertFalse($rider->fresh()->rider_available);
        $this->assertTrue($rider->fresh()->onBreak());

        $this->postJson('/api/rider/shift', ['action' => 'break_end'])
            ->assertOk()
            ->assertJsonPath('data.status', 'clocked_in')
            ->assertJsonPath('data.available', true);
        $this->assertTrue($rider->fresh()->rider_available);
        $this->assertNotNull($rider->currentShift()); // still on the clock
    }

    public function test_break_requires_being_clocked_in(): void
    {
        $rider = $this->rider($this->store());
        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/shift', ['action' => 'break_start'])->assertStatus(422);
        $this->postJson('/api/rider/shift', ['action' => 'break_end'])->assertStatus(422);
    }

    public function test_clock_out_closes_the_shift_and_any_open_break(): void
    {
        $rider = $this->rider($this->store());
        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/shift', ['action' => 'clock_in'])->assertOk();
        $this->postJson('/api/rider/shift', ['action' => 'break_start'])->assertOk();

        $this->postJson('/api/rider/shift', ['action' => 'clock_out'])
            ->assertOk()
            ->assertJsonPath('data.status', 'off')
            ->assertJsonPath('data.clocked_in', false);

        $rider->refresh();
        $this->assertFalse($rider->rider_available);
        $this->assertNull($rider->currentShift());
        $shift = $rider->riderShifts()->latest('clock_in_at')->first();
        $this->assertNotNull($shift->clock_out_at);
        $this->assertNull($shift->breaks()->whereNull('ended_at')->first());
    }

    public function test_clock_out_without_a_shift_is_rejected(): void
    {
        $rider = $this->rider($this->store());
        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/shift', ['action' => 'clock_out'])->assertStatus(422);
    }

    public function test_an_unavailable_rider_is_not_auto_assigned(): void
    {
        $store = $this->store();
        $this->rider($store); // rider_available defaults to false — never clocked in

        $order = $this->order($store);
        $this->assertNull(RiderAssignment::assign($order));
        $this->assertNull($order->fresh()->delivery_partner_id);
    }

    public function test_a_clocked_in_rider_is_auto_assigned(): void
    {
        $store = $this->store();
        $rider = $this->rider($store);
        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/shift', ['action' => 'clock_in'])->assertOk();

        $this->assertSame($rider->id, RiderAssignment::assign($this->order($store))?->id);
    }

    public function test_a_rider_on_break_is_not_auto_assigned(): void
    {
        $store = $this->store();
        $rider = $this->rider($store);
        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/shift', ['action' => 'clock_in'])->assertOk();
        $this->postJson('/api/rider/shift', ['action' => 'break_start'])->assertOk();

        $this->assertNull(RiderAssignment::assign($this->order($store)));
    }

    public function test_admin_can_force_a_rider_offline_with_a_reason(): void
    {
        $store = $this->store();
        $rider = $this->rider($store);
        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/shift', ['action' => 'clock_in'])->assertOk();

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->patchJson("/api/admin/riders/{$rider->id}", [
            'rider_available' => false,
            'rider_unavailable_reason' => 'No-show follow-up',
        ])->assertOk()->assertJsonPath('data.attendance.available', false);

        $rider->refresh();
        $this->assertFalse($rider->rider_available);
        $this->assertSame('No-show follow-up', $rider->rider_unavailable_reason);
        $this->assertNull(RiderAssignment::assign($this->order($store))); // still shows a shift, but not offered work
        $this->assertNotNull($rider->currentShift());                     // ledger untouched

        $this->patchJson("/api/admin/riders/{$rider->id}", ['rider_available' => true])->assertOk();
        $this->assertTrue($rider->fresh()->rider_available);
        $this->assertNull($rider->fresh()->rider_unavailable_reason);
    }

    public function test_worked_minutes_exclude_breaks(): void
    {
        $rider = $this->rider($this->store());
        $shift = $rider->riderShifts()->create([
            'clock_in_at' => now()->subHours(4), 'clock_out_at' => now(), 'source' => 'rider',
        ]);
        $shift->breaks()->create([
            'started_at' => now()->subHours(3), 'ended_at' => now()->subHours(2), 'reason' => 'Lunch',
        ]);

        $shift->load('breaks');
        $this->assertSame(60, $shift->breakMinutes());
        $this->assertSame(180, $shift->workedMinutes()); // 4h - 1h break
    }

    public function test_rider_stats_carry_shift_and_attendance(): void
    {
        $rider = $this->rider($this->store());
        $rider->riderShifts()->create([
            'clock_in_at' => now()->subDays(1)->setTime(9, 0), 'clock_out_at' => now()->subDays(1)->setTime(17, 0), 'source' => 'rider',
        ]);
        Sanctum::actingAs($rider);

        $res = $this->getJson('/api/rider/stats')->assertOk();
        $res->assertJsonPath('data.shift.status', 'off');
        $this->assertNotEmpty($res->json('data.attendance'));
        $this->assertSame(480, $res->json('data.attendance.0.worked_minutes'));
    }

    public function test_admin_rider_detail_and_roster_expose_attendance(): void
    {
        $store = $this->store();
        $rider = $this->rider($store);
        $rider->riderShifts()->create([
            'clock_in_at' => now()->setTime(8, 0), 'clock_out_at' => now()->setTime(12, 0), 'source' => 'rider',
        ]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->getJson("/api/admin/riders/{$rider->id}")
            ->assertOk()
            ->assertJsonPath('data.attendance.0.worked_minutes', 240);

        $this->getJson('/api/admin/riders/attendance')
            ->assertOk()
            ->assertJsonPath('data.0.total_worked_minutes', 240)
            ->assertJsonPath('data.0.days.0.worked_minutes', 240);
    }

    public function test_hitting_a_rider_endpoint_stamps_last_seen(): void
    {
        $rider = $this->rider($this->store());
        $this->assertNull($rider->rider_last_seen_at);

        Sanctum::actingAs($rider);
        $this->getJson('/api/rider/orders')->assertOk();

        $rider->refresh();
        $this->assertNotNull($rider->rider_last_seen_at);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson("/api/admin/riders/{$rider->id}")->assertJsonPath('data.rider.online', true);
    }

    public function test_shift_action_is_validated(): void
    {
        $rider = $this->rider($this->store());
        Sanctum::actingAs($rider);
        $this->postJson('/api/rider/shift', ['action' => 'nope'])->assertStatus(422);
        $this->postJson('/api/rider/shift', [])->assertStatus(422);
    }

    public function test_monthly_report_classifies_full_short_and_off_days(): void
    {
        $rider = $this->rider($this->store(), ['rider_daily_target_minutes' => 480]);
        $mon = now()->startOfMonth();

        // Day 1: a full 9h shift.
        $rider->riderShifts()->create([
            'clock_in_at' => (clone $mon)->setTime(9, 0), 'clock_out_at' => (clone $mon)->setTime(18, 0), 'source' => 'rider',
        ]);
        // Day 2: a short 3h shift.
        $rider->riderShifts()->create([
            'clock_in_at' => (clone $mon)->addDay()->setTime(9, 0), 'clock_out_at' => (clone $mon)->addDay()->setTime(12, 0), 'source' => 'rider',
        ]);
        // Day 3: no shift (off).

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $res = $this->getJson("/api/admin/riders/{$rider->id}/attendance?from={$mon->toDateString()}&to={$mon->copy()->addDays(2)->toDateString()}")
            ->assertOk();

        $res->assertJsonPath('data.target_minutes', 480)
            ->assertJsonPath('data.summary.days_full', 1)
            ->assertJsonPath('data.summary.days_short', 1)
            ->assertJsonPath('data.summary.days_off', 1)
            ->assertJsonPath('data.summary.total_worked_minutes', 540 + 180);

        // days are newest-first
        $days = $res->json('data.days');
        $this->assertCount(3, $days);
        $this->assertSame('off', $days[0]['status']);
        $this->assertSame('short', $days[1]['status']);
        $this->assertSame('full', $days[2]['status']);
    }

    public function test_report_defaults_to_the_current_month_and_stops_at_today(): void
    {
        $rider = $this->rider($this->store());
        $rider->riderShifts()->create([
            'clock_in_at' => now()->startOfMonth()->setTime(8, 0),
            'clock_out_at' => now()->startOfMonth()->setTime(16, 0),
            'source' => 'rider',
        ]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $res = $this->getJson("/api/admin/riders/{$rider->id}/attendance")->assertOk();

        $this->assertSame(now()->startOfMonth()->toDateString(), $res->json('data.from'));
        // never lists days in the future
        $this->assertLessThanOrEqual(today()->toDateString(), $res->json('data.days.0.date'));
        $this->assertSame(480, $res->json('data.target_minutes')); // app default when unset
    }

    public function test_admin_can_set_a_rider_daily_target(): void
    {
        $rider = $this->rider($this->store());
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson("/api/admin/riders/{$rider->id}", ['rider_daily_target_minutes' => 360])
            ->assertOk()
            ->assertJsonPath('data.daily_target_minutes', 360);
        $this->assertSame(360, $rider->fresh()->rider_daily_target_minutes);
    }

    public function test_rider_attendance_report_requires_a_rider(): void
    {
        $notRider = User::factory()->create();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $this->getJson("/api/admin/riders/{$notRider->id}/attendance")->assertNotFound();
    }

    public function test_days_before_the_rider_joined_are_not_counted_as_off(): void
    {
        $rider = $this->rider($this->store(), ['rider_since' => now()->subDays(3)->startOfDay()]);
        // worked a full day two days ago
        $rider->riderShifts()->create([
            'clock_in_at' => now()->subDays(2)->setTime(9, 0),
            'clock_out_at' => now()->subDays(2)->setTime(18, 0),
            'source' => 'rider',
        ]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $from = now()->subDays(8)->toDateString();
        $res = $this->getJson("/api/admin/riders/{$rider->id}/attendance?from={$from}&to=".today()->toDateString())->assertOk();

        // 8 days before "today" are in range, but the rider only joined 3 days
        // ago — so at most 2 completed days (yesterday + 2 days ago) can be off,
        // and one of those was worked. Days 4-8 back are "pre", not "off".
        $this->assertSame(now()->subDays(3)->toDateString(), $res->json('data.active_from'));
        $this->assertSame(1, $res->json('data.summary.days_full'));
        $this->assertLessThanOrEqual(2, $res->json('data.summary.days_off'));
        $this->assertContains('pre', array_column($res->json('data.days'), 'status'));
        foreach ($res->json('data.days') as $day) {
            if ($day['status'] === 'pre') {
                $this->assertLessThan($res->json('data.active_from'), $day['date']);
            }
        }
    }

    public function test_today_is_not_graded_while_the_rider_is_still_clocked_in(): void
    {
        $rider = $this->rider($this->store(), ['rider_since' => now()->subMonth()]);
        // clocked in 3h ago, still on the clock
        $rider->riderShifts()->create(['clock_in_at' => now()->subHours(3), 'source' => 'rider']);
        $rider->forceFill(['rider_available' => true])->save();

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
        $res = $this->getJson("/api/admin/riders/{$rider->id}/attendance")->assertOk();

        $this->assertTrue($res->json('data.today.on_the_clock'));
        $this->assertGreaterThanOrEqual(170, $res->json('data.today.worked_minutes'));

        $todayRow = collect($res->json('data.days'))->firstWhere('date', today()->toDateString());
        $this->assertSame('today', $todayRow['status']);
        // an in-progress day is never counted short/off
        $this->assertSame(0, $res->json('data.summary.days_short'));
        $this->assertSame(0, $res->json('data.summary.total_worked_minutes'));
    }

    public function test_promoting_a_rider_stamps_rider_since(): void
    {
        $user = User::factory()->create(['email' => 'promote-me@ex.com']);
        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->patchJson("/api/admin/customers/{$user->id}", ['is_rider' => true])->assertOk();

        $this->assertNotNull($user->fresh()->rider_since);
    }
}
