<?php

namespace App\Support;

use App\Models\LabelRequest;
use App\Models\LabelTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a seller's shipping label PDF from an admin label template: the
 * shop's ship-from address, the customer's ship-to address, the order
 * reference and (optionally) the items. Stored privately — it carries the
 * customer's address — and downloaded by the seller or admin.
 */
class ShippingLabel
{
    /** Paper in points: 4×6 in thermal, A6, A4. */
    private const PAPER = [
        '4x6' => [0, 0, 288, 432],
        'a6' => 'a6',
        'a4' => 'a4',
    ];

    public static function defaultTemplate(): ?LabelTemplate
    {
        return LabelTemplate::where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first();
    }

    /** The template to use: the requested one, the shop's last choice, or the default. */
    public static function templateFor(?int $templateId, ?int $shopPreference): ?LabelTemplate
    {
        foreach ([$templateId, $shopPreference] as $id) {
            if ($id && ($template = LabelTemplate::where('is_active', true)->find($id))) {
                return $template;
            }
        }

        return self::defaultTemplate();
    }

    /** Render the PDF for a request and store it; returns the private path. */
    public static function generate(LabelRequest $request, LabelTemplate $template): string
    {
        $request->loadMissing(['shop', 'shipFrom', 'order.items']);
        $order = $request->order;
        $lines = $order->items->keyBy('id');
        $items = collect($request->items)->map(fn ($i) => [
            'name' => trim(($lines->get($i['order_item_id'])?->product_name ?? 'Item').' '.($lines->get($i['order_item_id'])?->variant_label ? '· '.$lines->get($i['order_item_id'])->variant_label : '')),
            'sku' => $lines->get($i['order_item_id'])?->sku,
            'quantity' => $i['quantity'],
        ]);

        $pdf = Pdf::setOption(['isFontSubsettingEnabled' => true])
            ->setPaper(self::PAPER[$template->size] ?? self::PAPER['4x6'])
            ->loadView('labels.shipping', [
                'template' => $template,
                'request' => $request,
                'order' => $order,
                'shop' => $request->shop,
                'from' => $request->shipFrom,
                'to' => (array) $order->delivery_address,
                'items' => $items,
                'logo' => self::logoDataUri($template->logo_url ?: (Branding::current()['logo_url'] ?? null)),
                'brand' => Branding::current()['store_name'] ?: config('app.name'),
                'reference' => sprintf('NT-%d-%d', $order->id, $request->id),
            ]);

        $path = "labels/order-{$order->id}-request-{$request->id}-".now()->format('YmdHis').'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    /** Inline an uploaded logo (dompdf doesn't fetch remote images). */
    private static function logoDataUri(?string $url): ?string
    {
        if (! $url || ! preg_match('#(?:/api/media/file/|/storage/(?:app/public/)?)(.+)$#', $url, $m)) {
            return null;
        }
        $file = storage_path('app/public/'.$m[1]);
        if (! is_file($file) || filesize($file) > 1024 * 1024) {
            return null;
        }
        $mime = mime_content_type($file) ?: 'image/png';
        if (! str_starts_with($mime, 'image/') || $mime === 'image/svg+xml' || $mime === 'image/webp') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file));
    }
}
