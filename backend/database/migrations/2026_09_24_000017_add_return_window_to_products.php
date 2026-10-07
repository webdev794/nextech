<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-product return window in days (null = the platform default in
     * settings -> return_window_days; 0 = non-returnable). A seller's earnings
     * for an order are held until it's delivered and this window has passed,
     * so a payout never covers a sale that can still be returned.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('return_days')->nullable()->after('compare_at_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('return_days');
        });
    }
};
