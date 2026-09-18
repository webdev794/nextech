<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lifetime counters for rider performance: deliveries the rider
            // explicitly rejected, and offers that lapsed with no response.
            $table->unsignedInteger('rider_declined_count')->default(0)->after('rider_rating_count');
            $table->unsignedInteger('rider_missed_count')->default(0)->after('rider_declined_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'rider_declined_count', 'rider_missed_count',
        ]));
    }
};
