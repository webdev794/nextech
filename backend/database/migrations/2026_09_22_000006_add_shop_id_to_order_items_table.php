<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Snapshotted from product.shop_id at checkout time, the same way
            // product_name/sku are frozen — an admin can reassign a product's
            // shop later without rewriting history, and the seller ledger reads
            // this column, never the live product.
            $table->foreignId('shop_id')->nullable()->after('product_variant_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shop_id');
        });
    }
};
