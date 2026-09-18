@php
    /** @var \App\Models\Order $order */
    $money = fn ($cents) => '$' . number_format(((int) $cents) / 100, 2);
    $brandName = $branding['store_name'] !== '' ? $branding['store_name'] : config('app.name');
    $accent = $branding['color_brand'] ?? '#1f7a3d';

    $addr = $order->delivery_address ?? [];
    $deliveryLines = array_values(array_filter([
        $addr['name'] ?? null,
        $addr['line1'] ?? null,
        $addr['line2'] ?? null,
        trim(implode(', ', array_filter([$addr['city'] ?? null, $addr['state'] ?? null, $addr['postal_code'] ?? null]))),
    ]));

    $storeLines = $store ? array_values(array_filter([
        $store->line1,
        $store->line2,
        trim(implode(', ', array_filter([$store->city, $store->state, $store->postal_code]))),
    ])) : [];

    $regularOf = function ($line) {
        $reg = $line->compare_at_price_cents
            ?? $line->productVariant?->compare_at_price_cents
            ?? $line->product?->compare_at_price_cents;

        return $reg !== null && (int) $reg > $line->unit_price_cents ? (int) $reg : null;
    };

    $savings = 0;
    foreach ($order->items as $line) {
        $reg = $regularOf($line);
        if ($reg !== null) {
            $savings += ($reg - $line->unit_price_cents) * $line->quantity;
        }
    }

    $paymentLabel = match ($order->payment_method) {
        'cod' => 'Cash on delivery',
        'card' => 'Card',
        default => ucfirst((string) $order->payment_method),
    };
    $paidLabel = $order->payment_status === 'paid'
        ? 'Paid'
        : ($order->payment_method === 'cod' ? 'Due on delivery' : 'Payment pending');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; color: #20291f; font-size: 12px; line-height: 1.45; margin: 0; }
        .wrap { padding: 36px 40px; }
        h1 { font-size: 20px; margin: 0 0 2px; letter-spacing: -0.02em; }
        .muted { color: #7c857a; }
        .accent { color: {{ $accent }}; }
        .head td { vertical-align: top; padding: 0; }
        .head .right { text-align: right; }
        .tag { font-size: 11px; text-transform: uppercase; letter-spacing: 0.09em; color: #7c857a; margin: 0 0 4px; }
        .parties { width: 100%; margin: 26px 0 8px; border-collapse: collapse; }
        .parties td { width: 50%; vertical-align: top; padding: 0 16px 0 0; }
        .parties strong { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #7c857a; margin-bottom: 4px; }
        .box { border: 1px solid #e4e7df; border-radius: 8px; padding: 14px 16px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 22px; }
        table.items th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.07em; color: #7c857a; border-bottom: 1px solid #cfd6c8; padding: 0 8px 8px; }
        table.items td { padding: 10px 8px; border-bottom: 1px solid #eef1ec; vertical-align: top; }
        table.items .num { text-align: right; white-space: nowrap; }
        .was { color: #20291f; text-decoration: line-through; font-size: 10px; }
        .sale { color: #1f7a3d; font-weight: bold; }
        .totals { width: 46%; margin-left: 54%; margin-top: 16px; border-collapse: collapse; }
        .totals td { padding: 5px 0; }
        .totals .num { text-align: right; }
        .totals .grand td { border-top: 2px solid #20291f; padding-top: 10px; font-size: 15px; font-weight: bold; }
        .totals .saved td { color: #1f7a3d; }
        .note { margin-top: 26px; padding: 12px 14px; background: #f4f7ef; border-radius: 8px; font-size: 11px; }
        .foot { margin-top: 34px; border-top: 1px solid #e4e7df; padding-top: 12px; font-size: 10px; color: #7c857a; }
    </style>
</head>
<body>
<div class="wrap">
    <table class="head" width="100%">
        <tr>
            <td>
                <h1 class="accent">{{ $brandName }}</h1>
                @if(($branding['tagline'] ?? '') !== '')
                    <div class="muted">{{ $branding['tagline'] }}</div>
                @endif
            </td>
            <td class="right">
                <p class="tag">Bill / Receipt</p>
                <div><strong>Order #{{ $order->id }}</strong></div>
                <div class="muted">{{ $order->created_at?->format('d M Y, H:i') }}</div>
                <div class="muted">{{ $paymentLabel }} &middot; {{ $paidLabel }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <strong>Sold by</strong>
                <div class="box">
                    <div><b>{{ $store?->name ?? $brandName }}</b></div>
                    @forelse($storeLines as $l)
                        <div class="muted">{{ $l }}</div>
                    @empty
                        <div class="muted">Address not set</div>
                    @endforelse
                </div>
            </td>
            <td>
                <strong>Delivered to</strong>
                <div class="box">
                    @forelse($deliveryLines as $l)
                        <div>{{ $l }}</div>
                    @empty
                        <div class="muted">&mdash;</div>
                    @endforelse
                    @if(!empty($addr['phone']))
                        <div class="muted">Phone: {{ $addr['phone'] }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Regular</th>
                <th class="num">Price</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
        @foreach($order->items as $line)
            @php
                $reg = $regularOf($line);
                $onSale = $reg !== null;
            @endphp
            <tr>
                <td>
                    {{ $line->product_name }}
                    @if($line->variant_label)<span class="muted"> &mdash; {{ $line->variant_label }}</span>@endif
                    @if($line->sku)<div class="muted">SKU: {{ $line->sku }}</div>@endif
                </td>
                <td class="num">{{ $line->quantity }}</td>
                <td class="num">@if($onSale)<span class="was">{{ $money($reg) }}</span>@else&mdash;@endif</td>
                <td class="num">@if($onSale)<span class="sale">{{ $money($line->unit_price_cents) }}</span>@else{{ $money($line->unit_price_cents) }}@endif</td>
                <td class="num">
                    @if($onSale)<span class="was">{{ $money($reg * $line->quantity) }}</span><br><span class="sale">{{ $money($line->line_total_cents) }}</span>@else{{ $money($line->line_total_cents) }}@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td class="num">
                @if($savings > 0)<span class="was">{{ $money($order->subtotal_cents + $savings) }}</span> @endif{{ $money($order->subtotal_cents) }}
            </td>
        </tr>
        @if($savings > 0)
        <tr class="saved">
            <td>You saved</td>
            <td class="num">&minus;{{ $money($savings) }}</td>
        </tr>
        @endif
        <tr>
            <td>Delivery fee</td>
            <td class="num">{{ $order->delivery_fee_cents === 0 ? 'FREE' : $money($order->delivery_fee_cents) }}</td>
        </tr>
        <tr>
            <td>Handling fee</td>
            <td class="num">{{ $money($order->handling_fee_cents) }}</td>
        </tr>
        @if($order->small_cart_fee_cents > 0)
        <tr>
            <td>Small cart fee</td>
            <td class="num">{{ $money($order->small_cart_fee_cents) }}</td>
        </tr>
        @endif
        <tr>
            <td>Tax</td>
            <td class="num">{{ $money($order->tax_cents) }}</td>
        </tr>
        @if(($order->gift_card_discount_cents ?? 0) > 0)
        <tr>
            <td>Gift card</td>
            <td class="num">&minus;{{ $money($order->gift_card_discount_cents) }}</td>
        </tr>
        @endif
        <tr class="grand">
            <td>{{ $order->payment_method === 'cod' && $order->payment_status !== 'paid' ? 'Total due' : 'Total paid' }}</td>
            <td class="num">{{ $money($order->total_cents) }}</td>
        </tr>
    </table>

    @if($order->delivery_instructions)
        <div class="note"><b>Note to rider:</b> {{ $order->delivery_instructions }}</div>
    @endif

    <div class="foot">
        This is a system-generated bill for order #{{ $order->id }} placed on
        {{ $order->created_at?->format('d M Y') }}. Prices are in USD and include the amounts charged at checkout.
        Thank you for shopping with {{ $brandName }}.
    </div>
</div>
</body>
</html>
