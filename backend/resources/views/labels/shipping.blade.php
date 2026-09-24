@php
    /** @var \App\Models\LabelTemplate $template */
    $big = $template->size === 'a4';
    $fromLines = array_values(array_filter([
        $shop?->name,
        $from?->contact_name,
        $from?->line1,
        $from?->line2,
        trim(implode(', ', array_filter([$from?->city, $from?->state, $from?->postal_code]))),
        $from?->country,
    ]));
    $toLines = array_values(array_filter([
        $to['line1'] ?? null,
        $to['line2'] ?? null,
        trim(implode(', ', array_filter([$to['city'] ?? null, $to['state'] ?? null, $to['postal_code'] ?? null]))),
    ]));
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: {{ $big ? '28px' : '10px' }}; }
    body { font-family: DejaVu Sans, sans-serif; color: #000; font-size: {{ $big ? '13px' : '9px' }}; margin: 0; }
    .label { border: 2px solid #000; {{ $big ? 'width: 62%;' : '' }} }
    .row { border-bottom: 2px solid #000; padding: {{ $big ? '10px 12px' : '6px 8px' }}; }
    .row:last-child { border-bottom: 0; }
    .head td { vertical-align: middle; padding: 0; }
    .head img { max-height: {{ $big ? '38px' : '24px' }}; max-width: 160px; }
    .brand { font-weight: bold; font-size: {{ $big ? '18px' : '12px' }}; }
    .ref { text-align: right; font-size: {{ $big ? '12px' : '8px' }}; }
    .tag { font-size: {{ $big ? '10px' : '7px' }}; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 3px; }
    .to-name { font-size: {{ $big ? '22px' : '15px' }}; font-weight: bold; }
    .to-line { font-size: {{ $big ? '17px' : '12px' }}; line-height: 1.3; }
    .from-line { line-height: 1.35; }
    .order { font-size: {{ $big ? '26px' : '18px' }}; font-weight: bold; letter-spacing: 2px; text-align: center; }
    .tracking { height: {{ $big ? '46px' : '30px' }}; border: 1px dashed #000; margin-top: 4px; font-size: {{ $big ? '10px' : '7px' }}; padding: 3px; color: #444; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 1px 0; vertical-align: top; }
    .qty { text-align: right; white-space: nowrap; }
    .note { font-size: {{ $big ? '11px' : '7.5px' }}; }
    .cut { margin-top: 10px; font-size: 10px; color: #666; }
</style>
</head>
<body>
<div class="label">
    <div class="row">
        <table class="head"><tr>
            <td>@if ($logo)<img src="{{ $logo }}" alt="">@else<span class="brand">{{ $template->header_text ?: $brand }}</span>@endif</td>
            <td class="ref">Order #{{ $order->id }}<br>{{ now()->format('M j, Y') }}</td>
        </tr></table>
    </div>

    <div class="row">
        <div class="tag">Ship to</div>
        <div class="to-name">{{ $to['name'] ?? '' }}</div>
        @foreach ($toLines as $line)
            <div class="to-line">{{ $line }}</div>
        @endforeach
        @if ($template->show_phone && ! empty($to['phone']))
            <div class="to-line">Tel: {{ $to['phone'] }}</div>
        @endif
    </div>

    <div class="row">
        <div class="tag">From</div>
        @foreach ($fromLines as $line)
            <div class="from-line">{{ $line }}</div>
        @endforeach
    </div>

    <div class="row">
        <div class="order">{{ $reference }}</div>
        <div class="tracking">Carrier / tracking no.</div>
    </div>

    @if ($template->show_items && $items->isNotEmpty())
        <div class="row">
            <div class="tag">Contents</div>
            <table>
                @foreach ($items as $item)
                    <tr><td>{{ $item['name'] }}@if ($item['sku']) <span style="color:#555">({{ $item['sku'] }})</span>@endif</td><td class="qty">× {{ $item['quantity'] }}</td></tr>
                @endforeach
            </table>
        </div>
    @endif

    @if ($template->footer_note)
        <div class="row note">{{ $template->footer_note }}</div>
    @endif
</div>
@if ($big)
    <div class="cut">✂ Cut along the border and attach to the package.</div>
@endif
</body>
</html>
