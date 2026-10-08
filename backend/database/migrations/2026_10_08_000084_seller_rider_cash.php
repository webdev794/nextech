<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash on delivery collected by a seller's riders: handed over to the seller
 * (per package), a per-store cash limit, and a per-store pause while a rider
 * holds cash they should have handed over (the seller can choose "Later").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('order_packages', 'cash_handed_over_at')) {
                $table->timestamp('cash_handed_over_at')->nullable()->after('cash_collected_at');
            }
        });
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'rider_cash_limit_cents')) {
                $table->unsignedInteger('rider_cash_limit_cents')->nullable()->after('hiring_open');
            }
        });
        Schema::table('rider_store', function (Blueprint $table) {
            if (! Schema::hasColumn('rider_store', 'cash_paused_at')) {
                $table->timestamp('cash_paused_at')->nullable();
                // The seller trusts this rider: keep working despite the limit until the next handover.
                $table->timestamp('cash_later_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_packages', 'cash_handed_over_at')) {
            Schema::table('order_packages', fn (Blueprint $table) => $table->dropColumn('cash_handed_over_at'));
        }
        if (Schema::hasColumn('stores', 'rider_cash_limit_cents')) {
            Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('rider_cash_limit_cents'));
        }
        if (Schema::hasColumn('rider_store', 'cash_paused_at')) {
            Schema::table('rider_store', fn (Blueprint $table) => $table->dropColumn(['cash_paused_at', 'cash_later_at']));
        }
    }
};
