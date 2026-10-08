<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What a seller pays their riders per delivery (the store pays the rider and charges the seller). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'rider_pay_cents')) {
                $table->unsignedInteger('rider_pay_cents')->nullable()->after('rider_cash_limit_cents');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('stores', 'rider_pay_cents')) {
            Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('rider_pay_cents'));
        }
    }
};
