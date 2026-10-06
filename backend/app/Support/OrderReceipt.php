<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * The customer's printable order bill. Shared by the download endpoint and the
 * "order delivered" email so both render the exact same document.
 */
class OrderReceipt
{
    public static function pdf(Order $order): DomPdf
    {
        $order->loadMissing('items.product', 'items.productVariant', 'items.shop.seller', 'store');
        $store = $order->fulfillingStore();

        return Pdf::setOption(['isFontSubsettingEnabled' => true])
            ->loadView('receipts.order', [
                'order' => $order,
                'store' => $store,
                'branding' => Branding::current(),
                'sellers' => self::sellers($order, $store),
            ]);
    }

    /**
     * Who sold what, as on Amazon invoices: each seller's legal name, address and
     * tax number (GSTIN in India), keyed by shop id ('nextech' for NexTech's own
     * stock — from Store settings → Business & tax details).
     *
     * @return array<string, array{name: string, lines: list<string>, tax_label: string, tax_number: ?string}>
     */
    public static function sellers(Order $order, $store = null): array
    {
        // Only a GSTIN is printed (India); a seller without one (PAN only, new
        // business) shows none, and US tax IDs — possibly a personal SSN — never print.
        $gstin = fn ($value) => $order->market === 'IN' && is_string($value) && preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', strtoupper(trim($value))) ? strtoupper(trim($value)) : null;
        $taxLabel = 'GSTIN';
        $sellers = [];
        foreach ($order->items as $line) {
            $key = $line->shop_id ? (string) $line->shop_id : 'nextech';
            if (isset($sellers[$key])) {
                continue;
            }
            $seller = $line->shop?->seller;
            if ($seller) {
                $sellers[$key] = [
                    'name' => $seller->company_name ?: $line->shop->name,
                    'shop' => $line->shop->name,
                    'lines' => array_values(array_filter([
                        $seller->registered_line1,
                        $seller->registered_line2,
                        trim(implode(', ', array_filter([$seller->registered_city, $seller->registered_state, $seller->registered_postal_code]))),
                    ])),
                    'tax_label' => $taxLabel,
                    'tax_number' => $gstin($seller->tax_info['tax_number'] ?? null) ?? $gstin($seller->tax_id),
                ];
            } else {
                $own = ((array) Setting::get('business_details', []))[$order->market ?: Market::home()] ?? [];
                $address = trim((string) ($own['address'] ?? ''));
                $sellers[$key] = [
                    'name' => ($own['legal_name'] ?? null) ?: ($store?->name ?? ((Branding::current()['store_name'] ?? '') ?: config('app.name'))),
                    'shop' => null,
                    'lines' => $address !== '' ? preg_split('/\r?\n/', $address) : ($store ? array_values(array_filter([
                        $store->line1,
                        $store->line2,
                        trim(implode(', ', array_filter([$store->city, $store->state, $store->postal_code]))),
                    ])) : []),
                    'tax_label' => $taxLabel,
                    'tax_number' => $order->market === 'IN' ? ($own['tax_number'] ?? null) : null,
                ];
            }
        }

        return $sellers;
    }

    public static function filename(Order $order): string
    {
        return "bill-order-{$order->id}.pdf";
    }
}
