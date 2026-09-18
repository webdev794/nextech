<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These cover the password path; OTP has its own suite.
        config(['otp.enabled' => false]);
    }

    public function test_guest_api_request_returns_json_unauthorized_response(): void
    {
        $this->get('/api/user')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
        ]);
    }

    public function test_customer_can_register_with_an_optional_delivery_address(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Address Customer',
            'email' => 'address@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => [
                'name' => 'Address Customer',
                'line1' => '10 Main Street',
                'city' => 'Brooklyn',
                'state' => 'NY',
                'postal_code' => '11201',
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('addresses', [
            'name' => 'Address Customer',
            'line1' => '10 Main Street',
            'is_default' => true,
        ]);
    }

    public function test_customer_can_login_and_access_current_user(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure(['user', 'token']);

        $token = $loginResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_customer_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('customer')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        app('auth')->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_invalid_login_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }
}