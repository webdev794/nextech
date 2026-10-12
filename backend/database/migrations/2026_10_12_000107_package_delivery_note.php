<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A store delivery marked delivered without the buyer's code, or refused by the buyer: the reason given. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('order_packages', 'delivery_verified')) {
                $table->boolean('delivery_verified')->nullable()->after('delivered_by');
            }
            if (! Schema::hasColumn('order_packages', 'delivery_note')) {
                $table->string('delivery_note', 300)->nullable()->after('delivery_verified');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_packages', function (Blueprint $table) {
            foreach (['delivery_verified', 'delivery_note'] as $col) {
                if (Schema::hasColumn('order_packages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
