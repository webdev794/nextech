<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cash on delivery for orders sellers ship themselves, and the seller's
// step-by-step updates buyers and admin follow: packed, picked up by the
// courier, in transit, out for delivery, delivered (and cash collected).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('accepts_cod')->default(false)->after('fulfillment_mode');
        });
        Schema::table('order_shop_shipping', function (Blueprint $table) {
            $table->timestamp('packed_at')->nullable()->after('deliver_by');
        });
        Schema::table('order_packages', function (Blueprint $table) {
            $table->json('status_history')->nullable()->after('status');
            $table->timestamp('progress_updated_at')->nullable()->after('status_history');
            $table->timestamp('cash_collected_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('accepts_cod'));
        Schema::table('order_shop_shipping', fn (Blueprint $table) => $table->dropColumn('packed_at'));
        Schema::table('order_packages', fn (Blueprint $table) => $table->dropColumn(['status_history', 'progress_updated_at', 'cash_collected_at']));
    }
};
