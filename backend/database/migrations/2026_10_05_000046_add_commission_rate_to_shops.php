<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A shop's own commission rate: set when the admin changes the commission
// but keeps existing sellers on the rate they had. Null = the market's rate.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has it (e.g. imported from edp.sql).
        if (! Schema::hasColumn('shops', 'commission_rate_bps')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->unsignedSmallInteger('commission_rate_bps')->nullable()->after('market');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('commission_rate_bps'));
    }
};
