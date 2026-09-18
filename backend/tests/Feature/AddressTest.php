<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private function address(User $user, array $attributes = []): Address
    {
        return $user->addresses()->create([
            'label' => 'Home', 'name' => 'Test Customer', 'line1' => '10 Main Street',
            'city' => 'Brooklyn', 'state' => 'NY', 'postal_code' => '11201',
            ...$attributes,
        ]);
    }

    public function test_customer_adds_edits_and_deletes_their_addresses(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/addresses', [
            'name' => 'Test Customer', 'line1' => '10 Main Street', 'city' => 'Brooklyn',
            'state' => 'NY', 'postal_code' => '11201', 'is_default' => true,
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/addresses/{$id}", ['line1' => '22 Elm Street', 'label' => 'Office'])
            ->assertOk()
            ->assertJsonPath('data.line1', '22 Elm Street')
            ->assertJsonPath('data.label', 'Office');

        $this->deleteJson("/api/addresses/{$id}")->assertNoContent();
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_only_one_address_is_default_at_a_time(): void
    {
        $user = User::factory()->create();
        $a = $this->address($user, ['is_default' => true]);
        $b = $this->address($user, ['label' => 'Work']);
        Sanctum::actingAs($user);

        $this->patchJson("/api/addresses/{$b->id}", ['is_default' => true])->assertOk();

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
    }

    public function test_deleting_the_default_promotes_another_address(): void
    {
        $user = User::factory()->create();
        $this->address($user);
        $default = $this->address($user, ['label' => 'Work', 'is_default' => true]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/addresses/{$default->id}")->assertNoContent();

        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_a_customer_cannot_touch_another_customers_address(): void
    {
        $other = $this->address(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/addresses/{$other->id}", ['line1' => 'x'])->assertNotFound();
        $this->deleteJson("/api/addresses/{$other->id}")->assertNotFound();
    }

    public function test_address_endpoints_require_authentication(): void
    {
        $address = $this->address(User::factory()->create());

        $this->patchJson("/api/addresses/{$address->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/addresses/{$address->id}")->assertUnauthorized();
    }
}
