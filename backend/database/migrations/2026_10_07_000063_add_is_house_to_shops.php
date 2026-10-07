<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The house shop: the store owner's own shop, run by admin on the seller tools
// (shipping templates, couriers and tracking, international, own delivery),
// with no commission and no product review.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('shops', 'is_house')) {
            Schema::table('shops', fn (Blueprint $table) => $table->boolean('is_house')->default(false));
        }
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('is_house'));
    }
};
