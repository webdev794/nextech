<?php

namespace App\Support\Courier;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Support\Facades\Http;

/**
 * Generic REST template for a real third-party carrier, wired up against
 * admin-entered credentials (Admin console -> Secure access -> Courier).
 *
 * No specific carrier has been picked yet, so the endpoint paths, payload
 * shapes and auth style below are a starting-point guess (bearer token +
 * JSON, one REST resource per operation) — adjust field/endpoint names
 * against the real provider's docs once one is chosen. Every method throws
 * CourierProviderUnavailableException uniformly (missing config, HTTP
 * failure, or a response that doesn't parse as expected) so Courier's
 * fallback-to-mock wrapper has one thing to catch regardless of the cause.
 */
class RealCourierProvider implements CourierProvider
{
    /**
     * @param  array{provider: string, base_url: string, api_key: string, api_secret: string, account_code: string}  $credentials
     */
    public function __construct(private readonly array $credentials)
    {
    }

    public function isServiceable(array $address): bool
    {
        $response = $this->request()->post($this->endpoint('serviceability'), [
            'account_code' => $this->credentials['account_code'],
            'address' => $address,
        ]);

        return (bool) ($this->decode($response)['serviceable'] ?? false);
    }

    public function quote(array $address): array
    {
        $data = $this->decode($this->request()->post($this->endpoint('rate'), [
            'account_code' => $this->credentials['account_code'],
            'address' => $address,
        ]));

        if (! isset($data['cost_cents'], $data['eta_days'])) {
            throw new CourierProviderUnavailableException('Courier rate response missing cost_cents/eta_days.');
        }

        return [
            'cost_cents' => (int) $data['cost_cents'],
            'eta_days' => (int) $data['eta_days'],
        ];
    }

    public function book(Order $order, array $origin): array
    {
        $data = $this->decode($this->request()->post($this->endpoint('shipments'), [
            'account_code' => $this->credentials['account_code'],
            'order_id' => $order->id,
            'origin_address' => $origin,
            'address' => $order->delivery_address ?? [],
        ]));

        if (! isset($data['tracking_number'])) {
            throw new CourierProviderUnavailableException('Courier booking response missing tracking_number.');
        }

        return [
            'tracking_number' => (string) $data['tracking_number'],
            'carrier' => (string) ($data['carrier'] ?? $this->credentials['provider']),
            'label_url' => $data['label_url'] ?? null,
        ];
    }

    public function track(Shipment $shipment): string
    {
        $data = $this->decode($this->request()->get($this->endpoint("shipments/{$shipment->tracking_number}")));

        if (! isset($data['status'])) {
            throw new CourierProviderUnavailableException('Courier tracking response missing status.');
        }

        return (string) $data['status'];
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        if ($this->credentials['base_url'] === '' || $this->credentials['api_key'] === '') {
            throw new CourierProviderUnavailableException('Real courier provider is not configured (missing base_url/api_key).');
        }

        return Http::withToken($this->credentials['api_key'])
            ->acceptJson()
            ->asJson()
            ->timeout(10);
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->credentials['base_url'], '/').'/'.ltrim($path, '/');
    }

    /** @return array<string, mixed> */
    private function decode(\Illuminate\Http\Client\Response $response): array
    {
        try {
            if (! $response->successful()) {
                throw new CourierProviderUnavailableException("Courier request failed with status {$response->status()}.");
            }

            $json = $response->json();
        } catch (CourierProviderUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CourierProviderUnavailableException('Courier response could not be parsed: '.$e->getMessage(), 0, $e);
        }

        return is_array($json) ? $json : [];
    }
}
