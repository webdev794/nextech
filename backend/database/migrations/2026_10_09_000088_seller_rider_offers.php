<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A seller offers a local order to all their riders until a deadline; the seller sets how many hours riders get. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_shop_shipping', function (Blueprint $table) {
            if (! Schema::hasColumn('order_shop_shipping', 'rider_offer_until')) {
                $table->timestamp('rider_offer_until')->nullable()->after('method');
                $table->timestamp('rider_offer_missed_at')->nullable()->after('rider_offer_until');
            }
        });
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'rider_pickup_hours')) {
                $table->unsignedSmallInteger('rider_pickup_hours')->nullable()->after('rider_pay_cents');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_shop_shipping', 'rider_offer_until')) {
            Schema::table('order_shop_shipping', fn (Blueprint $table) => $table->dropColumn(['rider_offer_until', 'rider_offer_missed_at']));
        }
        if (Schema::hasColumn('stores', 'rider_pickup_hours')) {
            Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('rider_pickup_hours'));
        }
    }
};
