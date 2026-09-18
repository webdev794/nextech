<?php

namespace Tests\Feature;

use App\Models\AuthOtp;
use App\Models\User;
use App\Notifications\SendOtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    // ---- change password (signed in) ----

    public function test_signed_in_user_changes_their_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpass123')]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/profile/password', [
            'current_password' => 'oldpass123',
            'password' => 'brandnew456',
            'password_confirmation' => 'brandnew456',
        ])->assertOk();

        $this->assertTrue(Hash::check('brandnew456', $user->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpass123')]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/profile/password', [
            'current_password' => 'nope',
            'password' => 'brandnew456',
            'password_confirmation' => 'brandnew456',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_change_password_needs_authentication(): void
    {
        $this->patchJson('/api/profile/password', [
            'current_password' => 'x', 'password' => 'yyyyyyyy', 'password_confirmation' => 'yyyyyyyy',
        ])->assertUnauthorized();
    }

    // ---- forgot / reset (not signed in) ----

    public function test_forgot_password_emails_a_code_and_reset_signs_the_user_in(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jo@example.com', 'password' => Hash::make('forgotten')]);

        $this->postJson('/api/auth/forgot-password', ['email' => 'jo@example.com'])->assertOk();
        Notification::assertSentOnDemand(SendOtpCode::class);

        // Pull the code the way the app would (from the hashed row we can't read,
        // so use the OTP bypass path via a fresh known code).
        $otp = AuthOtp::where('email', 'jo@example.com')->where('purpose', 'password_reset')->first();
        $this->assertNotNull($otp);
        // Overwrite with a code we know, mimicking what the user received.
        $otp->update(['code_hash' => Hash::make('123456')]);

        $res = $this->postJson('/api/auth/reset-password', [
            'email' => 'jo@example.com', 'code' => '123456',
            'password' => 'freshpass99', 'password_confirmation' => 'freshpass99',
        ])->assertOk();

        $res->assertJsonStructure(['token', 'user']);
        $this->assertTrue(Hash::check('freshpass99', $user->fresh()->password));
        $this->assertDatabaseMissing('auth_otps', ['email' => 'jo@example.com', 'purpose' => 'password_reset']);
    }

    public function test_reset_with_a_bad_code_fails(): void
    {
        $user = User::factory()->create(['email' => 'k@example.com', 'password' => Hash::make('orig')]);
        AuthOtp::create([
            'email' => 'k@example.com', 'purpose' => 'password_reset',
            'code_hash' => Hash::make('999999'), 'attempts' => 0,
            'expires_at' => now()->addMinutes(10), 'last_sent_at' => now(),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'k@example.com', 'code' => '000000',
            'password' => 'whatever12', 'password_confirmation' => 'whatever12',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertTrue(Hash::check('orig', $user->fresh()->password));
    }

    public function test_forgot_password_for_an_unknown_email_still_returns_ok(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('auth_otps', 0);
    }
}
