<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Who took a live product off sale: "seller" (they can relist it themselves,
// like Temu's Deactivate / Relist) or "admin" (only NexTech can bring it back).
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('products', 'deactivated_by')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('deactivated_by', 10)->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('deactivated_by'));
    }
};
