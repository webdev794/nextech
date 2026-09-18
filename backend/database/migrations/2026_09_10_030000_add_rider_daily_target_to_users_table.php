<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Expected worked minutes for a "full" day. Null falls back to the
            // app default (8h). Used to classify each day in the attendance
            // report as full / short / off.
            $table->unsignedSmallInteger('rider_daily_target_minutes')->nullable()->after('rider_offers_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('rider_daily_target_minutes'));
    }
};
