<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

// Demo store: put up to 8 demo products — or, when none are marked demo,
// NexTech's own products — (one per category / country) on an
// auto-renewing lightning deal of about 30% off (28–32% each round, 50 units),
// so the Lightning deals section, countdowns and "% claimed" can be tried out.
return new class extends Migration
{
    public function up(): void
    {
        if (Product::query()->where('lightning_repeat', true)->exists()) {
            return; // already set (e.g. imported from edp.sql)
        }
        $demo = Product::query()->where('is_demo', true)->exists();
        $ids = Product::query()->when($demo, fn ($q) => $q->where('is_demo', true), fn ($q) => $q->whereNull('shop_id'))->where('status', 'approved')->where('is_active', true)->whereNull('affiliate_url')
            ->selectRaw('MIN(id) as id')->groupBy('category_id', 'market')->limit(8)->pluck('id');
        Product::query()->whereIn('id', $ids)->get()->each(fn (Product $p) => $p->forceFill([
            'lightning_pct' => 30, 'lightning_pct_min' => 28, 'lightning_pct_max' => 32, 'lightning_repeat' => true,
            'lightning_qty' => 50, 'lightning_starts_at' => now(), 'lightning_ends_at' => now()->addHours(12),
            'lightning_base_sold' => (int) $p->units_sold,
        ])->saveQuietly());
    }

    public function down(): void
    {
        Product::query()->where('lightning_repeat', true)->where(fn ($q) => $q->where('is_demo', true)->orWhereNull('shop_id'))->update([
            'lightning_pct' => null, 'lightning_pct_min' => null, 'lightning_pct_max' => null, 'lightning_repeat' => false,
            'lightning_qty' => null, 'lightning_starts_at' => null, 'lightning_ends_at' => null, 'lightning_base_sold' => null,
        ]);
    }
};
