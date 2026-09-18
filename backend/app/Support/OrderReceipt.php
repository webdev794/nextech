<?php

namespace App\Support;

use App\Models\Order;
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
        $order->loadMissing('items.product', 'items.productVariant', 'store');

        return Pdf::setOption(['isFontSubsettingEnabled' => true])
            ->loadView('receipts.order', [
                'order' => $order,
                'store' => $order->fulfillingStore(),
                'branding' => Branding::current(),
            ]);
    }

    public static function filename(Order $order): string
    {
        return "bill-order-{$order->id}.pdf";
    }
}
