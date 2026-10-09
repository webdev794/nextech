<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Support\ExtraHolidays;
use App\Support\Market;
use App\Support\SellerNotify;
use App\Support\SellerShipping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Shipping → Holidays: holidays admin adds per country or store, and sellers' day-off requests. */
class AdminHolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->query('market'))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'market' => ['required', 'string', 'size:2'],
            'date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', 'max:60'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
        ]);
        ExtraHolidays::add($data['market'], $data['date'], $data['name'], $data['shop_id'] ?? null);

        return response()->json(['data' => $this->payload($data['market'])], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        ExtraHolidays::remove($id);

        return response()->json(['data' => $this->payload($request->query('market'))]);
    }

    /** A seller's request: add it for their store, for the whole country, or decline (they're told). */
    public function decide(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:store,country,decline'], 'note' => ['nullable', 'string', 'max:300']]);
        $req = ExtraHolidays::takeRequest($id);
        abort_unless($req, 404);
        $shop = Shop::with('seller')->find($req['shop_id']);
        if ($shop && $data['decision'] !== 'decline') {
            ExtraHolidays::add($shop->market, $req['date'], $req['name'], $data['decision'] === 'store' ? $shop->id : null);
        }
        if ($shop?->seller) {
            $text = match ($data['decision']) {
                'store' => "Your day off on {$req['date']} ({$req['name']}) is added for your store — delivery dates skip it.",
                'country' => "{$req['date']} ({$req['name']}) is added as a holiday for every seller — tick it in Holiday settings if you work that day.",
                default => "Your day off on {$req['date']} ({$req['name']}) wasn’t added".(($data['note'] ?? '') !== '' ? ": {$data['note']}" : '.'),
            };
            SellerNotify::send($shop->seller, $request->user(), 'Day off request', $text);
        }

        return response()->json(['data' => $this->payload($shop?->market)]);
    }

    private function payload(?string $market): array
    {
        $market = strtoupper($market ?: 'US');
        $shops = Shop::query()->whereIn('id', collect(ExtraHolidays::all())->pluck('shop_id')->merge(collect(ExtraHolidays::requests())->pluck('shop_id'))->filter())->pluck('name', 'id');
        $names = Market::holidays($market);
        $builtIn = collect(SellerShipping::holidayDates(now()->year, $market) + SellerShipping::holidayDates(now()->year + 1, $market))
            ->filter(fn ($key, $date) => $date >= now()->toDateString() && ! str_starts_with($key, 'x'))
            ->map(fn ($key, $date) => ['date' => $date, 'name' => $names[$key] ?? $key])->values();

        return [
            'market' => $market,
            'built_in' => $builtIn,
            'extras' => collect(ExtraHolidays::all())->where('market', $market)->map(fn ($h) => $h + ['shop' => $h['shop_id'] ? $shops[$h['shop_id']] ?? null : null])->values(),
            'requests' => collect(ExtraHolidays::requests())->map(fn ($r) => $r + ['shop' => $shops[$r['shop_id']] ?? null])->values(),
        ];
    }
}
