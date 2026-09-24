<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The return window in force when the item was bought, frozen onto the
     * order line like price and SKU — so a seller shortening a product's
     * window afterwards can't release earnings on orders already sold.
     * Null on older lines (they fall back to the product / platform default).
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('return_days')->nullable()->after('compare_at_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('return_days');
        });
    }
};
