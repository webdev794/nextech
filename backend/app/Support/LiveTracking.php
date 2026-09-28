<?php

namespace App\Support;

use App\Models\OrderPackage;
use App\Notifications\PackageTrackingUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Live courier tracking through AfterShip (Tracking API 2026-07), for the
 * packages sellers ship with their own courier. A package is registered when
 * it ships (or its tracking is corrected); AfterShip then reports progress by
 * webhook (POST /api/webhooks/aftership), and stale packages are refreshed
 * when their order is looked at, so tracking keeps moving even without the
 * webhook set up. Off until admin saves an AfterShip API key (Secure access ->
 * Courier). A tracking failure never breaks shipping.
 */
class LiveTracking
{
    private const API = 'https://api.aftership.com/tracking/2026-07';

    /** Refresh a package on view when its tracking is older than this. */
    private const STALE_MINUTES = 30;

    /** Our carriers (config/markets.php) -> AfterShip courier slugs; unknown ones are auto-detected. */
    private const SLUGS = [
        'UPS' => 'ups', 'USPS' => 'usps', 'FedEx' => 'fedex', 'DHL' => 'dhl', 'OnTrac' => 'ontrac',
        'Delhivery' => 'delhivery', 'BlueDart' => 'bluedart', 'DTDC' => 'dtdc', 'IndiaPost' => 'india-post',
        'Ekart' => 'ekart', 'XpressBees' => 'xpressbees', 'Shadowfax' => 'shadowfax',
    ];

    public static function enabled(): bool
    {
        return self::apiKey() !== '';
    }

    private static function apiKey(): string
    {
        return CourierCredentials::current()['tracking_api_key'];
    }

    public static function webhookSecret(): string
    {
        return CourierCredentials::current()['tracking_webhook_secret'];
    }

    /** Start tracking a package (after it ships, or after its tracking number changes). */
    public static function register(OrderPackage $package): void
    {
        if (! self::enabled() || ! $package->tracking_number) {
            return;
        }
        try {
            $body = array_filter([
                'tracking_number' => $package->tracking_number,
                'slug' => self::SLUGS[$package->carrier] ?? null,
                'title' => "Order #{$package->order_id}",
                'order_id' => (string) $package->order_id,
            ]);
            $response = self::http()->post(self::API.'/trackings', $body);
            // A courier code AfterShip doesn't accept: let it detect the courier itself.
            if ($response->status() === 400 && isset($body['slug'])) {
                unset($body['slug']);
                $response = self::http()->post(self::API.'/trackings', $body);
            }
            $tracking = $response->json('data');
            // Already tracked (same number registered before): look it up instead.
            if (! $response->successful() || ! isset($tracking['id'])) {
                $found = self::http()->get(self::API.'/trackings', ['tracking_numbers' => $package->tracking_number]);
                $tracking = collect($found->json('data.trackings') ?? [])->first();
            }
            if (isset($tracking['id'])) {
                self::apply($package, $tracking);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Pull the latest status for one package. */
    public static function refresh(OrderPackage $package): void
    {
        if (! self::enabled() || ! $package->tracking_number) {
            return;
        }
        if (! $package->tracking_ref) {
            self::register($package);

            return;
        }
        try {
            $response = self::http()->get(self::API.'/trackings/'.$package->tracking_ref);
            if ($response->successful() && is_array($response->json('data'))) {
                self::apply($package, $response->json('data'));
            } else {
                $package->forceFill(['tracking_synced_at' => now()])->saveQuietly();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Refresh the packages in this list whose tracking is stale — a few per
     * request, so viewing orders stays quick.
     *
     * @param  iterable<OrderPackage>  $packages
     */
    public static function refreshStale(iterable $packages, int $limit = 5): void
    {
        if (! self::enabled()) {
            return;
        }
        collect($packages)
            ->filter(fn (OrderPackage $p) => $p->label_source === 'own' && in_array($p->status, ['shipped', 'in_transit'], true)
                && (! $p->tracking_synced_at || $p->tracking_synced_at->lt(now()->subMinutes(self::STALE_MINUTES))))
            ->take($limit)
            ->each(fn (OrderPackage $p) => self::refresh($p));
    }

    /** A webhook from AfterShip: the tracking it describes, on whichever package carries it. */
    public static function fromWebhook(array $tracking): void
    {
        $query = OrderPackage::query();
        if (! empty($tracking['id'])) {
            $query->where('tracking_ref', $tracking['id']);
        } elseif (! empty($tracking['tracking_number'])) {
            $query->where('tracking_number', $tracking['tracking_number']);
        } else {
            return;
        }
        $query->get()->each(fn (OrderPackage $p) => self::apply($p, $tracking));
    }

    /**
     * Save a tracking onto the package, moving its status along (never back
     * from delivered). Delivered completes the order the usual way
     * (SellerFulfillment::sync); out for delivery and problems email the buyer.
     *
     * @param  array<string, mixed>  $tracking  AfterShip Tracking object
     */
    public static function apply(OrderPackage $package, array $tracking): void
    {
        $tag = (string) ($tracking['tag'] ?? '');
        $events = collect($tracking['checkpoints'] ?? [])
            ->map(fn ($c) => [
                'at' => $c['checkpoint_time'] ?? $c['created_at'] ?? null,
                'tag' => $c['tag'] ?? null,
                'message' => $c['message'] ?? $c['subtag_message'] ?? null,
                'location' => $c['location'] ?? implode(', ', array_filter([$c['city'] ?? null, $c['state'] ?? null, $c['country_region_name'] ?? null])),
            ])
            ->reverse()->take(20)->values()->all(); // newest first
        $latest = $events[0] ?? null;
        $eta = $tracking['latest_estimated_delivery']['datetime'] ?? $tracking['latest_estimated_delivery']['date']
            ?? $tracking['courier_estimated_delivery_date']['estimated_delivery_date'] ?? null;

        $status = match ($tag) {
            'Delivered' => 'delivered',
            'InTransit', 'OutForDelivery', 'AvailableForPickup', 'AttemptFail', 'Exception' => 'in_transit',
            default => $package->status,
        };
        if ($package->status === 'delivered' || ! in_array($package->status, ['shipped', 'in_transit', 'delivered'], true)) {
            $status = $package->status;
        }
        $previousTag = $package->tracking_tag;

        $package->forceFill([
            'tracking_ref' => $tracking['id'] ?? $package->tracking_ref,
            'tracking_tag' => $tag !== '' ? $tag : $package->tracking_tag,
            'tracking_detail' => mb_substr(trim(($tracking['subtag_message'] ?? '') ?: ($latest['message'] ?? '')), 0, 255) ?: $package->tracking_detail,
            'tracking_eta' => $eta ? substr((string) $eta, 0, 10) : $package->tracking_eta,
            'tracking_events' => $events ?: $package->tracking_events,
            'tracking_synced_at' => now(),
            'status' => $status,
            'delivered_at' => $status === 'delivered' ? ($package->delivered_at ?? now()) : $package->delivered_at,
        ])->save();

        if ($status === 'delivered' && $package->wasChanged('status')) {
            SellerFulfillment::sync($package->order);
        }
        if ($tag !== $previousTag && in_array($tag, ['OutForDelivery', 'AttemptFail', 'Exception', 'AvailableForPickup'], true)) {
            $order = $package->order;
            DB::afterCommit(fn () => $order?->emailCustomer(new PackageTrackingUpdate($order, $package->fresh())));
        }
    }

    /** Friendly label for an AfterShip status. */
    public static function label(?string $tag): ?string
    {
        return match ($tag) {
            'Pending' => 'Waiting for the courier', 'InfoReceived' => 'Courier has the details',
            'InTransit' => 'In transit', 'OutForDelivery' => 'Out for delivery',
            'AttemptFail' => 'Delivery attempt failed', 'Delivered' => 'Delivered',
            'AvailableForPickup' => 'Ready for pickup', 'Exception' => 'Delivery problem',
            'Expired' => 'No updates from the courier', default => null,
        };
    }

    /** Checks an AfterShip webhook signature (base64 HMAC-SHA256 of the raw body). */
    public static function validSignature(string $body, ?string $signature): bool
    {
        $secret = self::webhookSecret();
        if ($secret === '') {
            return true; // no secret saved: accept (the payload only moves tracking along)
        }

        return $signature !== null && hash_equals(base64_encode(hash_hmac('sha256', $body, $secret, true)), $signature);
    }

    private static function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['as-api-key' => self::apiKey()])->acceptJson()->asJson()->timeout(10);
    }

    /** @param  Collection<int, \App\Models\Order>  $orders */
    public static function refreshOrders(Collection $orders): void
    {
        self::refreshStale($orders->flatMap(fn ($o) => $o->relationLoaded('packages') ? $o->packages : collect()));
    }
}
