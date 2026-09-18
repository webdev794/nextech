<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SendOtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['otp.enabled' => true, 'otp.resend_cooldown_seconds' => 60, 'otp.max_attempts' => 3]);
        Notification::fake();
    }

    public function test_registration_issues_an_otp_and_returns_no_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Otp User',
            'email' => 'otp@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('requires_otp', true)
            ->assertJsonMissing(['token']);

        $this->assertDatabaseHas('users', ['email' => 'otp@example.com', 'email_verified_at' => null]);
        $this->assertDatabaseHas('auth_otps', ['email' => 'otp@example.com', 'purpose' => 'register']);
        Notification::assertSentOnDemand(SendOtpCode::class);
    }

    public function test_verifying_the_code_returns_a_token_and_marks_verified(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Otp User', 'email' => 'otp@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertStatus(202);

        $code = $this->capturedCode();

        $this->postJson('/api/auth/verify-otp', [
            'email' => 'otp@example.com', 'purpose' => 'register', 'code' => $code,
        ])->assertCreated()->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseMissing('auth_otps', ['email' => 'otp@example.com']);
        $this->assertNotNull(User::where('email', 'otp@example.com')->first()->email_verified_at);
    }

    public function test_wrong_code_is_rejected_and_counts_against_the_attempt_limit(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()->assertJsonPath('requires_otp', true);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/verify-otp', ['email' => $user->email, 'purpose' => 'login', 'code' => '000000'])
                ->assertStatus(422);
        }

        // After max attempts the code is discarded; the real code no longer works.
        $this->postJson('/api/auth/verify-otp', ['email' => $user->email, 'purpose' => 'login', 'code' => $this->capturedCode()])
            ->assertStatus(422);
    }

    public function test_login_with_otp_enabled_does_not_return_a_token_until_verified(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('requires_otp', true)
            ->assertJsonMissing(['token']);

        $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email, 'purpose' => 'login', 'code' => $this->capturedCode(),
        ])->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_resend_is_rate_limited_by_the_cooldown(): void
    {
        $user = User::factory()->create(['password' => 'password123']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123']);

        $this->postJson('/api/auth/resend-otp', ['email' => $user->email, 'purpose' => 'login'])
            ->assertStatus(429);
    }

    public function test_bad_password_never_reaches_the_otp_step(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('auth_otps', ['email' => $user->email]);
    }

    private function capturedCode(): string
    {
        $code = null;
        Notification::assertSentOnDemand(SendOtpCode::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        return $code;
    }
}
