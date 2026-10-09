<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The hours a store's riders must be on duty: {"days":[1..7], "start":"09:00", "end":"18:00"}. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stores', 'rider_hours')) {
            Schema::table('stores', fn (Blueprint $table) => $table->json('rider_hours')->nullable()->after('rider_pickup_hours'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stores', 'rider_hours')) {
            Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('rider_hours'));
        }
    }
};
