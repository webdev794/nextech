<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 'own_rider' keeps every existing/new in-radius order on today's exact
        // behaviour with zero migration-time decision; 'online_courier' is the
        // new out-of-radius (or manually escalated) fallback.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method')->default('own_rider')->after('store_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_method');
        });
    }
};
