<?php

namespace App\Support;

use App\Models\DigitalDownload;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\ProductLicenseKey;
use App\Notifications\DigitalOrderReady;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Digital products (games, software, e-books, music, templates...). The
 * seller uploads files (kept private) or gives a download link, and can add
 * license keys — one per unit sold. Once an order is paid, its digital lines
 * are "ready": keys are handed out and the buyer downloads from "Your
 * downloads" through short-lived signed links, up to the download limit.
 */
class DigitalProducts
{
    /** "Unlimited" stock for digital products without license keys. */
    public const UNLIMITED = 999999;

    public const DEFAULT_DOWNLOAD_LIMIT = 5;

    /** Biggest single file a seller can upload (sent in chunks). */
    public const MAX_FILE_BYTES = 4 * 1024 * 1024 * 1024;

    /** Upload pieces are at most this big — smaller when the server's PHP upload limit is lower. */
    public const MAX_CHUNK_BYTES = 5 * 1024 * 1024;

    /** The piece size this server accepts: below both upload_max_filesize and post_max_size. */
    public static function chunkBytes(): int
    {
        $limit = min(self::iniBytes((string) ini_get('upload_max_filesize')), self::iniBytes((string) ini_get('post_max_size')));
        $limit = $limit > 0 ? $limit - 64 * 1024 : self::MAX_CHUNK_BYTES; // leave room for the other form fields

        return max(256 * 1024, min(self::MAX_CHUNK_BYTES, $limit));
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $n = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
        };
    }

    /** @return array{download_limit: int, instructions: ?string, license_keys: bool} */
    public static function settings(Product $product): array
    {
        $s = (array) ($product->digital_settings ?? []);

        return [
            'download_limit' => max(0, (int) ($s['download_limit'] ?? self::DEFAULT_DOWNLOAD_LIMIT)), // 0 = unlimited
            'instructions' => trim((string) ($s['instructions'] ?? '')) ?: null,
            'license_keys' => (bool) ($s['license_keys'] ?? false),
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'product_type' => ['sometimes', 'in:physical,digital'],
            'digital_settings' => ['sometimes', 'nullable', 'array'],
            'digital_settings.download_limit' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'digital_settings.instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'digital_settings.license_keys' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Stock follows the product: unlimited, or — when it sells license
     * keys — the keys not yet given out.
     */
    public static function syncStock(Product $product): void
    {
        if (! $product->isDigital()) {
            return;
        }
        $stock = self::settings($product)['license_keys']
            ? $product->licenseKeys()->whereNull('order_item_id')->count()
            : self::UNLIMITED;
        $product->forceFill(['inventory_quantity' => $stock])->saveQuietly();
    }

    /**
     * Add license keys, one per line (blank lines and duplicates skipped).
     *
     * @return array{added: int, skipped: int}
     */
    public static function addKeys(Product $product, string $text): array
    {
        $lines = collect(preg_split('/\r\n|\r|\n/', $text))->map(fn ($l) => trim($l))->filter()->unique()->values();
        $added = 0;
        foreach ($lines as $key) {
            abort_if(mb_strlen($key) > 500, 422, 'A license key is too long (500 characters max).');
            $hash = hash('sha256', $key);
            if (ProductLicenseKey::where('product_id', $product->id)->where('key_hash', $hash)->exists()) {
                continue;
            }
            ProductLicenseKey::create(['product_id' => $product->id, 'license_key' => $key, 'key_hash' => $hash]);
            $added++;
        }
        self::syncStock($product);

        return ['added' => $added, 'skipped' => $lines->count() - $added];
    }

    /**
     * A paid order: its digital lines become downloadable, license keys are
     * handed out, and an all-digital order is completed. Safe to call again.
     */
    public static function fulfill(Order $order): void
    {
        $order->refresh();
        if ($order->payment_status !== 'paid' || $order->status === 'cancelled') {
            return;
        }
        $ready = 0;
        DB::transaction(function () use ($order, &$ready) {
            foreach ($order->items()->where('fulfilled_by', 'digital')->whereNull('digital_ready_at')->lockForUpdate()->get() as $item) {
                $product = Product::find($item->product_id);
                if ($product && self::settings($product)['license_keys']) {
                    $keys = ProductLicenseKey::where('product_id', $product->id)->whereNull('order_item_id')->orderBy('id')->lockForUpdate()->limit($item->quantity)->get();
                    foreach ($keys as $key) {
                        $key->update(['order_item_id' => $item->id, 'assigned_at' => now()]);
                    }
                    self::syncStock($product);
                }
                $item->forceFill(['digital_ready_at' => now()])->save();
                $ready++;
            }
            // Nothing else to deliver: the order is done.
            if ($ready && ! $order->items()->where('fulfilled_by', '!=', 'digital')->exists() && $order->status !== 'completed') {
                $order->forceFill(['status' => 'completed', 'delivered_at' => now()])->save();
            }
        });
        if ($ready) {
            DB::afterCommit(function () use ($order) {
                $order->emailCustomer(new DigitalOrderReady($order->fresh()));
                $order->fresh()->sendDeliveredReceiptIfReady();
            });
        }
    }

    /** Why this buyer can't download this line, or null when they can. */
    public static function blockedReason(OrderItem $item, int $userId): ?string
    {
        $order = $item->order;
        if (! $order || $order->user_id !== $userId) {
            return 'Not found.';
        }
        if ($order->status === 'cancelled' || in_array($order->payment_status, ['refunded', 'refund_pending'], true)) {
            return 'This order was cancelled or refunded.';
        }
        if ($order->payment_status !== 'paid' || ! $item->digital_ready_at) {
            return 'Your download is ready once payment is complete.';
        }

        return null;
    }

    public static function downloadsUsed(OrderItem $item, int $fileId): int
    {
        return DigitalDownload::where('order_item_id', $item->id)->where('product_file_id', $fileId)->count();
    }

    /** Downloads allowed for this line (limit × quantity bought); 0 = unlimited. */
    public static function limitFor(OrderItem $item, Product $product): int
    {
        $limit = self::settings($product)['download_limit'];

        return $limit === 0 ? 0 : $limit * max(1, (int) $item->quantity);
    }

    /** A 10-minute signed link to one file of a purchased line. */
    public static function signedLink(OrderItem $item, ProductFile $file): string
    {
        return URL::temporarySignedRoute('digital.download', now()->addMinutes(10), ['item' => $item->id, 'file' => $file->id]);
    }

    /** What the buyer sees for one purchased line. */
    public static function present(OrderItem $item): array
    {
        $product = Product::with('files')->find($item->product_id);
        $settings = $product ? self::settings($product) : ['download_limit' => 0, 'instructions' => null, 'license_keys' => false];
        $limit = $product ? self::limitFor($item, $product) : 0;

        return [
            'order_item_id' => $item->id,
            'order_id' => $item->order_id,
            'product_name' => $item->product_name,
            'product_slug' => $product?->slug,
            'image_url' => $product?->image_url,
            'purchased_at' => $item->created_at,
            'ready' => $item->digital_ready_at !== null,
            'instructions' => $settings['instructions'],
            'license_keys' => ProductLicenseKey::where('order_item_id', $item->id)->get()->map(fn ($k) => $k->license_key)->values(),
            'download_limit' => $limit,
            'files' => $product ? $product->files->map(fn (ProductFile $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'size_bytes' => $f->size_bytes,
                'external' => $f->external_url !== null,
                'used' => self::downloadsUsed($item, $f->id),
            ])->values() : [],
        ];
    }
}
