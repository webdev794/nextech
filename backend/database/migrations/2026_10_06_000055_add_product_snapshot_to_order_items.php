<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The product as it was sold (name, details, warranty, return terms…), frozen
 * on each order line — what the buyer's warranty and returns are judged on,
 * whatever the seller changes on the listing later.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'product_snapshot')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->json('product_snapshot')->nullable()->after('variant_label');
            });
        }
        // Earlier orders: the closest record there is — the product as it is now.
        \App\Models\OrderItem::query()->whereNull('product_snapshot')->whereNotNull('product_id')->with('product', 'productVariant')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                if ($item->product) {
                    $item->forceFill(['product_snapshot' => \App\Support\ProductSnapshot::of($item->product, $item->productVariant) + ['backfilled' => true]])->saveQuietly();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('product_snapshot'));
    }
};
