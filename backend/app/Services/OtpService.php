<?php

namespace App\Services;

use App\Models\AuthOtp;
use App\Notifications\SendOtpCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OtpService
{
    /**
     * Generate a fresh code for the email/purpose pair and email it.
     * Honours the resend cooldown when a code is already outstanding.
     */
    public function issue(string $email, string $purpose): void
    {
        $email = mb_strtolower(trim($email));
        $existing = AuthOtp::where('email', $email)->where('purpose', $purpose)->first();

        if ($existing && $existing->last_sent_at->diffInSeconds(now()) < config('otp.resend_cooldown_seconds')) {
            throw ValidationException::withMessages([
                'code' => ['Please wait a moment before requesting another code.'],
            ])->status(429);
        }

        $code = str_pad((string) random_int(0, (10 ** config('otp.length')) - 1), config('otp.length'), '0', STR_PAD_LEFT);

        AuthOtp::updateOrCreate(
            ['email' => $email, 'purpose' => $purpose],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(config('otp.ttl_minutes')),
                'last_sent_at' => now(),
            ]
        );

        Notification::route('mail', $email)->notify(new SendOtpCode($code, config('otp.ttl_minutes')));

        if (config('app.debug')) {
            Log::info("OTP for {$email} ({$purpose}): {$code}");
        }
    }

    /**
     * Consume a code. Returns true on success (the row is deleted); on failure
     * the attempt is recorded and the row is dropped once attempts run out.
     */
    public function verify(string $email, string $purpose, string $code): bool
    {
        $email = mb_strtolower(trim($email));

        $bypass = (string) config('otp.bypass_code');
        if ($bypass !== '' && hash_equals($bypass, $code)) {
            AuthOtp::where('email', $email)->where('purpose', $purpose)->delete();

            return true;
        }

        $otp = AuthOtp::where('email', $email)->where('purpose', $purpose)->first();

        if (! $otp || $otp->isExpired()) {
            $otp?->delete();

            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            if ($otp->attempts >= config('otp.max_attempts')) {
                $otp->delete();
            }

            return false;
        }

        $otp->delete();

        return true;
    }
}
