@php
    /** @var \App\Models\LabelTemplate $template */
    $cur = strtolower($currency);
    $fromLines = array_values(array_filter([
        $shop?->name,
        $from?->contact_name,
        $from?->line1,
        $from?->line2,
        trim(implode(', ', array_filter([$from?->city, $from?->state, $from?->postal_code]))),
        $fromCountry ? strtoupper($fromCountry) : null,
        $from?->phone ? 'Tel: '.$from->phone : null,
    ]));
    $toLines = array_values(array_filter([
        $to['line1'] ?? null,
        $to['line2'] ?? null,
        trim(implode(', ', array_filter([$to['city'] ?? null, $to['state'] ?? null, $to['postal_code'] ?? null]))),
    ]));
    $totalValue = $items->sum('total_cents');
    $totalWeight = $items->every(fn ($i) => $i['weight_grams']) ? $items->sum('weight_grams') : null;
    $header = $template->header_text ? str_replace('{store}', $brand, $template->header_text) : $brand;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px; }
    body { font-family: DejaVu Sans, sans-serif; color: #000; font-size: 11px; margin: 0; }
    .banner { border: 3px solid #000; text-align: center; padding: 8px; font-size: 26px; font-weight: bold; letter-spacing: 3px; }
    .box { border: 2px solid #000; border-top: 0; padding: 10px 12px; }
    .head td { vertical-align: middle; }
    .head img { max-height: 36px; max-width: 160px; }
    .brand { font-weight: bold; font-size: 15px; }
    .ref { text-align: right; }
    .tag { font-size: 9px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 3px; }
    .to-name { font-size: 18px; font-weight: bold; }
    .to-line { font-size: 14px; line-height: 1.3; }
    .country { font-size: 18px; font-weight: bold; margin-top: 3px; }
    .order { font-size: 20px; font-weight: bold; letter-spacing: 2px; }
    .tracking { height: 34px; border: 1px dashed #000; margin-top: 4px; font-size: 9px; padding: 3px; color: #444; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .cols td { width: 50%; padding: 0 8px 0 0; }
    .decl th, .decl td { border: 1px solid #000; padding: 4px 5px; font-size: 10px; text-align: left; }
    .decl th { background: #eee; }
    .num { text-align: right !important; white-space: nowrap; }
    .check { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; margin-right: 4px; text-align: center; line-height: 10px; font-size: 9px; }
    .sign td { padding-top: 22px; }
    .line { border-top: 1px solid #000; padding-top: 2px; font-size: 9px; }
    .note { font-size: 10px; }
    .cut { margin: 14px 0; border-top: 1px dashed #666; font-size: 9px; color: #666; padding-top: 2px; }
</style>
</head>
<body>
{{-- Part 1: the address label for the outside of the parcel. --}}
<div class="banner">INTERNATIONAL DELIVERY</div>
<div class="box">
    <table class="head"><tr>
        <td>@if ($logo)<img src="{{ $logo }}" alt="">@else<span class="brand">{{ $header }}</span>@endif</td>
        <td class="ref">Order #{{ $order->id }}<br>{{ now()->format('M j, Y') }}</td>
    </tr></table>
</div>
<div class="box">
    <table class="cols"><tr>
        <td>
            <div class="tag">Ship to</div>
            <div class="to-name">{{ $to['name'] ?? '' }}</div>
            @foreach ($toLines as $line)
                <div class="to-line">{{ $line }}</div>
            @endforeach
            <div class="country">{{ strtoupper($toCountry) }}</div>
            @if ($template->show_phone && ! empty($to['phone']))
                <div class="to-line">Tel: {{ $to['phone'] }}</div>
            @endif
        </td>
        <td>
            <div class="tag">From &mdash; if undelivered, return to</div>
            @foreach ($fromLines as $line)
                <div>{{ $line }}</div>
            @endforeach
        </td>
    </tr></table>
</div>
<div class="box">
    <div class="order">{{ $reference }}</div>
    <div class="tracking">Carrier / international tracking no.</div>
</div>
@if ($template->footer_note)
    <div class="box note">{{ $template->footer_note }}</div>
@endif

<div class="cut">✂ Cut here &mdash; attach the label above to the parcel; the declaration below goes with the customs paperwork.</div>

{{-- Part 2: customs declaration. --}}
<div class="banner" style="font-size:16px">CUSTOMS DECLARATION</div>
<div class="box">
    <span class="check">✓</span> Sale of goods (commercial) &nbsp;&nbsp;
    <span class="check"></span> Gift &nbsp;&nbsp;
    <span class="check"></span> Returned goods &nbsp;&nbsp;
    <span class="check"></span> Documents &nbsp;&nbsp;
    <span class="check"></span> Other
</div>
<div class="box">
    <table class="decl">
        <tr>
            <th>Description of contents</th>
            <th class="num">Qty</th>
            <th class="num">Unit value</th>
            <th class="num">Total value</th>
            <th>Country of origin</th>
            <th>HS code</th>
            <th class="num">Weight</th>
        </tr>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item['name'] }}@if ($template->show_items && $item['sku'])<br><span style="color:#555">{{ $item['sku'] }}</span>@endif</td>
                <td class="num">{{ $item['quantity'] }}</td>
                <td class="num">{{ \App\Support\Money::format($item['unit_cents'], $cur) }}</td>
                <td class="num">{{ \App\Support\Money::format($item['total_cents'], $cur) }}</td>
                <td>{{ $item['origin'] }}</td>
                <td>{{ $item['hs_code'] ?: '' }}</td>
                <td class="num">{{ $item['weight_grams'] ? number_format($item['weight_grams'] / 1000, 2).' kg' : '' }}</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="3">Total ({{ $currency }})</th>
            <th class="num">{{ \App\Support\Money::format($totalValue, $cur) }}</th>
            <th colspan="2"></th>
            <th class="num">{{ $totalWeight ? number_format($totalWeight / 1000, 2).' kg' : '' }}</th>
        </tr>
    </table>
    <div class="note" style="margin-top:6px">Fill in any blank HS code or weight by hand. Values are what the buyer paid for the goods, excluding shipping.</div>
</div>
<div class="box note">
    I certify that the particulars given in this declaration are correct and that this parcel does not contain any dangerous or prohibited articles.
    <table class="sign"><tr>
        <td style="width:45%"><div class="line">Sender's signature</div></td>
        <td style="width:10%"></td>
        <td style="width:45%"><div class="line">Date</div></td>
    </tr></table>
</div>
</body>
</html>
