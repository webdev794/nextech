<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMetricsCompareTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $createdAt, array $attributes = []): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'subtotal_cents' => 1000,
            'tax_cents' => 0,
            'delivery_fee_cents' => 0,
            'total_cents' => 1000,
            'delivery_address' => ['name' => 'x', 'line1' => 'y'],
            ...$attributes,
        ]);
        $order->forceFill(['created_at' => $createdAt])->saveQuietly();

        return $order;
    }

    public function test_month_preset_compares_this_month_to_date_against_the_whole_previous_month(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        // This month (to Sep 15): 2 orders, one unpaid.
        $this->order('2026-09-03 09:00:00', ['total_cents' => 1500]);
        $this->order('2026-09-10 09:00:00', ['payment_status' => 'pending', 'total_cents' => 900]);
        // All of last month counts now, not just its first 15 days.
        $this->order('2026-08-05 09:00:00', ['total_cents' => 4000]);
        $this->order('2026-08-20 09:00:00', ['total_cents' => 9999]);

        $this->getJson('/api/admin/metrics/compare?preset=month')
            ->assertOk()
            ->assertJsonPath('data.preset', 'month')
            ->assertJsonPath('data.bucket', 'day')
            ->assertJsonPath('data.partial', true)
            ->assertJsonPath('data.current.label', 'This month')
            ->assertJsonPath('data.current.orders', 2)
            ->assertJsonPath('data.current.paid_orders', 1)
            ->assertJsonPath('data.current.revenue_cents', 1500)
            ->assertJsonPath('data.previous.label', 'Last month')
            ->assertJsonPath('data.previous.orders', 2)         // all of August, incl. Aug 20
            ->assertJsonPath('data.previous.revenue_cents', 13999);

        Carbon::setTestNow();
    }

    public function test_custom_days_window(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        $this->order('2026-09-14 10:00:00');                       // within last 3 days
        $this->order('2026-09-13 10:00:00');                       // within last 3 days
        $this->order('2026-09-10 10:00:00');                       // within the previous 3 days
        $this->order('2026-09-01 10:00:00');                       // outside both windows

        $this->getJson('/api/admin/metrics/compare?preset=custom&days=3')
            ->assertOk()
            ->assertJsonPath('data.preset', 'custom')
            ->assertJsonPath('data.days', 3)
            ->assertJsonPath('data.bucket', 'day')
            ->assertJsonPath('data.current.label', 'Last 3 days')
            ->assertJsonPath('data.current.orders', 2)
            ->assertJsonPath('data.previous.orders', 1);

        Carbon::setTestNow();
    }

    public function test_custom_preset_requires_days(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/metrics/compare?preset=custom')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['days']);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->getJson('/api/admin/metrics/compare')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/admin/metrics/compare')->assertForbidden();
    }
}
