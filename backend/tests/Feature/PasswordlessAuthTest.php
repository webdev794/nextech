<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SendOtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordlessAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.enabled' => true, 'otp.resend_cooldown_seconds' => 60, 'otp.bypass_code' => '']);
        Notification::fake();
    }

    public function test_start_with_a_new_email_sends_a_register_code_and_creates_nothing(): void
    {
        $this->postJson('/api/auth/start', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('purpose', 'register')
            ->assertJsonPath('known', false);

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertDatabaseHas('auth_otps', ['email' => 'new@example.com', 'purpose' => 'register']);
        Notification::assertSentOnDemand(SendOtpCode::class);
    }

    public function test_verifying_a_register_code_creates_the_account_and_returns_a_token(): void
    {
        $this->postJson('/api/auth/start', ['email' => 'shopper@example.com']);

        $this->postJson('/api/auth/verify-otp', [
            'email' => 'shopper@example.com', 'purpose' => 'register', 'code' => $this->code(),
        ])->assertCreated()
            ->assertJsonStructure(['user', 'token'])
            ->assertJsonPath('user.email', 'shopper@example.com');

        $this->assertDatabaseHas('users', ['email' => 'shopper@example.com', 'is_admin' => false]);
        $this->assertNotNull(User::where('email', 'shopper@example.com')->first()->email_verified_at);
    }

    public function test_start_with_a_known_email_sends_a_login_code(): void
    {
        User::factory()->create(['email' => 'member@example.com']);

        $this->postJson('/api/auth/start', ['email' => 'member@example.com'])
            ->assertOk()
            ->assertJsonPath('purpose', 'login')
            ->assertJsonPath('known', true);

        $this->postJson('/api/auth/verify-otp', [
            'email' => 'member@example.com', 'purpose' => 'login', 'code' => $this->code(),
        ])->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_start_signs_in_directly_when_the_otp_step_is_disabled(): void
    {
        config(['otp.enabled' => false]);

        $this->postJson('/api/auth/start', ['email' => 'x@example.com'])
            ->assertCreated()
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', ['email' => 'x@example.com']);
        $this->assertDatabaseMissing('auth_otps', ['email' => 'x@example.com']);
    }

    public function test_a_configured_bypass_code_verifies_any_pending_otp(): void
    {
        config(['otp.enabled' => true, 'otp.bypass_code' => '424242']);
        $this->postJson('/api/auth/start', ['email' => 'bypass@example.com']);

        $this->postJson('/api/auth/verify-otp', [
            'email' => 'bypass@example.com', 'purpose' => 'register', 'code' => '424242',
        ])->assertCreated()->assertJsonStructure(['user', 'token']);
    }

    private function code(): string
    {
        $code = null;
        Notification::assertSentOnDemand(SendOtpCode::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        return $code;
    }
}
