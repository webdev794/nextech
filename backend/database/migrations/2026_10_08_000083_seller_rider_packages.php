<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A seller's own-delivery package can be given to one of their riders (or delivered by the seller). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('order_packages', 'rider_id')) {
                $table->foreignId('rider_id')->nullable()->after('shop_id')->constrained('users')->nullOnDelete();
                $table->timestamp('rider_assigned_at')->nullable()->after('rider_id');
                // Who delivered it: rider | self (the seller) — for the rider money table later.
                $table->string('delivered_by', 10)->nullable()->after('delivered_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_packages', function (Blueprint $table) {
            if (Schema::hasColumn('order_packages', 'rider_id')) {
                $table->dropConstrainedForeignId('rider_id');
                $table->dropColumn(['rider_assigned_at', 'delivered_by']);
            }
        });
    }
};
