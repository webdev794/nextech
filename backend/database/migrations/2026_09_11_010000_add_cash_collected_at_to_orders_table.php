<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Stamped the moment a COD order's payment_status flips to 'paid' —
            // i.e. when the rider actually collected the cash. Lets admins see
            // how long a rider has been holding cash, independent of delivery
            // status or cash_settled_at.
            $table->timestamp('cash_collected_at')->nullable()->after('cash_settled_at');
        });

        // Backfill: orders already sitting paid-but-unsettled predate this column,
        // so there's no true collection timestamp to recover — best available
        // estimate is the row's last update.
        DB::table('orders')
            ->where('payment_method', 'cod')
            ->where('payment_status', 'paid')
            ->whereNull('cash_collected_at')
            ->update(['cash_collected_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cash_collected_at');
        });
    }
};
