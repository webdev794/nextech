<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * The signed-in customer's saved cards, backed by a Stripe Customer. Card data
 * never touches our servers — the browser confirms a SetupIntent with Stripe
 * directly and we only ever see payment-method ids.
 */
class BillingController extends Controller
{
    public function paymentMethods(Request $request): JsonResponse
    {
        $stripe = $this->stripe();
        if (! $stripe) {
            return response()->json(['data' => [], 'enabled' => false]);
        }

        $customerId = $request->user()->stripe_customer_id;
        if (! $customerId) {
            return response()->json(['data' => [], 'enabled' => true]);
        }

        try {
            $customer = $stripe->customers->retrieve($customerId, ['expand' => ['invoice_settings.default_payment_method']]);
            $methods = $stripe->paymentMethods->all(['customer' => $customerId, 'type' => 'card', 'limit' => 20]);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe list payment methods failed', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'Could not load your cards.'], 502);
        }

        $defaultId = is_object($customer->invoice_settings->default_payment_method ?? null)
            ? $customer->invoice_settings->default_payment_method->id
            : ($customer->invoice_settings->default_payment_method ?? null);

        $data = collect($methods->data)->map(fn ($pm) => [
            'id' => $pm->id,
            'brand' => $pm->card->brand,
            'last4' => $pm->card->last4,
            'exp_month' => $pm->card->exp_month,
            'exp_year' => $pm->card->exp_year,
            'is_default' => $pm->id === $defaultId,
        ])->values();

        return response()->json(['data' => $data, 'enabled' => true]);
    }

    public function setupIntent(Request $request): JsonResponse
    {
        $stripe = $this->stripe();
        if (! $stripe) {
            return response()->json(['message' => 'Card payments are not configured.'], 503);
        }

        try {
            $intent = $stripe->setupIntents->create([
                'customer' => $this->customerId($request->user(), $stripe),
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
            ]);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe SetupIntent failed', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'Could not start card setup. Please try again.'], 502);
        }

        return response()->json(['data' => ['client_secret' => $intent->client_secret]]);
    }

    public function setDefault(Request $request, string $paymentMethod): JsonResponse
    {
        $stripe = $this->stripe();
        $customerId = $request->user()->stripe_customer_id;
        if (! $stripe || ! $customerId) {
            return response()->json(['message' => 'No card to update.'], 422);
        }

        try {
            $this->assertOwned($stripe, $customerId, $paymentMethod);
            $stripe->customers->update($customerId, [
                'invoice_settings' => ['default_payment_method' => $paymentMethod],
            ]);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe set default card failed', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'Could not update your default card.'], 502);
        }

        return response()->json(status: 204);
    }

    public function detach(Request $request, string $paymentMethod): JsonResponse
    {
        $stripe = $this->stripe();
        $customerId = $request->user()->stripe_customer_id;
        if (! $stripe || ! $customerId) {
            return response()->json(status: 204);
        }

        try {
            $this->assertOwned($stripe, $customerId, $paymentMethod);
            $stripe->paymentMethods->detach($paymentMethod);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe detach card failed', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'Could not remove that card.'], 502);
        }

        return response()->json(status: 204);
    }

    private function stripe(): ?StripeClient
    {
        $secret = config('services.stripe.secret');

        return $secret ? new StripeClient($secret) : null;
    }

    /** Get-or-create the Stripe Customer for this user. */
    private function customerId(User $user, StripeClient $stripe): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = $stripe->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user_id' => (string) $user->id],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /** @throws \Symfony\Component\HttpKernel\Exception\HttpException */
    private function assertOwned(StripeClient $stripe, string $customerId, string $paymentMethod): void
    {
        $pm = $stripe->paymentMethods->retrieve($paymentMethod);
        abort_unless(($pm->customer ?? null) === $customerId, 404);
    }
}
