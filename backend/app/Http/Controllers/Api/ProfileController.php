<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Update the signed-in customer's own name / phone number. Phone is optional
     * at sign-up; this is where a customer can add it before checkout (or the
     * checkout call saves it for them).
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        if (array_key_exists('phone', $validated)) {
            $validated['phone'] = $validated['phone'] === null ? null : trim($validated['phone']);
        }

        $request->user()->update($validated);

        return response()->json(['data' => $request->user()->fresh()]);
    }

    /**
     * Change the signed-in user's password. Requires the current password;
     * signs every other device out.
     */
    public function password(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['That password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        return response()->json(['data' => ['ok' => true]]);
    }
}
