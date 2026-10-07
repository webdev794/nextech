<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NexTech orders sent by a courier admin books by hand: the tracking link the
// buyer follows (the carrier's own page, or the site admin entered for "Other").
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('shipments', 'tracking_url')) {
            Schema::table('shipments', fn (Blueprint $table) => $table->string('tracking_url', 500)->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn('tracking_url'));
    }
};
