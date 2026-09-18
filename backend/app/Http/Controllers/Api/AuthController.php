<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'array'],
            'address.name' => ['required_with:address', 'string', 'max:120'],
            'address.line1' => ['required_with:address', 'string', 'max:255'],
            'address.city' => ['required_with:address', 'string', 'max:100'],
            'address.state' => ['required_with:address', 'string', 'max:60'],
            'address.postal_code' => ['required_with:address', 'string', 'max:12'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create($validated);

            if (isset($validated['address'])) {
                $user->addresses()->create([...$validated['address'], 'label' => 'Home', 'is_default' => true]);
            }

            return $user;
        });

        if (config('otp.enabled')) {
            $this->otp->issue($user->email, 'register');

            return response()->json([
                'requires_otp' => true,
                'purpose' => 'register',
                'email' => $user->email,
                'message' => 'We sent a verification code to your email.',
            ], 202);
        }

        return $this->tokenResponse($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 422);
        }

        if (config('otp.enabled')) {
            $this->otp->issue($user->email, 'login');

            return response()->json([
                'requires_otp' => true,
                'purpose' => 'login',
                'email' => $user->email,
                'message' => 'We sent a verification code to your email.',
            ]);
        }

        return $this->tokenResponse($user);
    }

    /**
     * Passwordless entry: send a code to an email. Existing accounts get a
     * login code; unknown emails get a register code and the account is
     * created when that code is verified.
     */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = mb_strtolower(trim($data['email']));
        $known = User::where('email', $email)->exists();

        if (! config('otp.enabled')) {
            // No code step: sign in, or create the account, immediately.
            $user = $known ? User::where('email', $email)->first() : $this->createPasswordless($email);

            return $this->tokenResponse($user, $known ? 200 : 201);
        }

        $this->otp->issue($email, $known ? 'login' : 'register');

        return response()->json([
            'requires_otp' => true,
            'purpose' => $known ? 'login' : 'register',
            'email' => $email,
            'known' => $known,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'purpose' => ['required', Rule::in(['register', 'login'])],
            'code' => ['required', 'string'],
        ]);

        if (! $this->otp->verify($validated['email'], $validated['purpose'], $validated['code'])) {
            return response()->json([
                'message' => 'That code is invalid or has expired. Request a new one.',
            ], 422);
        }

        $user = $validated['purpose'] === 'register'
            ? User::firstOrCreate(
                ['email' => mb_strtolower(trim($validated['email']))],
                [
                    'name' => (string) Str::of($validated['email'])->before('@')->replace(['.', '_', '-'], ' ')->title(),
                    'password' => Hash::make(Str::random(40)),
                    'email_verified_at' => now(),
                ]
            )
            : User::where('email', $validated['email'])->firstOrFail();

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $this->tokenResponse($user, $validated['purpose'] === 'register' ? 201 : 200);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'purpose' => ['required', Rule::in(['register', 'login'])],
        ]);

        if (User::where('email', $validated['email'])->exists()) {
            $this->otp->issue($validated['email'], $validated['purpose']);
        }

        return response()->json(['message' => 'If that account exists, a new code is on its way.']);
    }

    /**
     * "Forgot password" — email a reset code. The response is deliberately the
     * same whether or not the account exists.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $email = mb_strtolower(trim($validated['email']));

        if (User::where('email', $email)->exists()) {
            try {
                $this->otp->issue($email, 'password_reset');
            } catch (\Illuminate\Validation\ValidationException) {
                // resend cooldown — act as if it was sent
            }
        }

        return response()->json(['message' => 'If that email has an account, a reset code is on its way.']);
    }

    /**
     * Set a new password with the code from forgotPassword(). Signs the user in
     * and revokes every other session.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'max:12'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $email = mb_strtolower(trim($validated['email']));

        if (! $this->otp->verify($email, 'password_reset', $validated['code'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => ['That code is invalid or has expired.'],
            ]);
        }

        $user = User::where('email', $email)->firstOrFail();
        $user->tokens()->delete();
        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        return $this->tokenResponse($user);
    }

    private function createPasswordless(string $email): User
    {
        return User::create([
            'email' => $email,
            'name' => (string) Str::of($email)->before('@')->replace(['.', '_', '-'], ' ')->title(),
            'password' => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'user' => $user,
            'token' => $user->createToken('customer')->plainTextToken,
        ], $status);
    }
}
