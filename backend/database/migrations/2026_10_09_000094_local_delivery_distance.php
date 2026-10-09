<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How far a local-delivery buyer is from the shop (saved at checkout), for the seller's own delivery zone. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_shop_shipping', 'local_km')) {
            Schema::table('order_shop_shipping', fn (Blueprint $table) => $table->decimal('local_km', 6, 2)->nullable()->after('method'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_shop_shipping', 'local_km')) {
            Schema::table('order_shop_shipping', fn (Blueprint $table) => $table->dropColumn('local_km'));
        }
    }
};
