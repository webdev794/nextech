<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('status');
            // null  = not completed by a rider (legacy / admin)
            // true  = a delivery OTP was confirmed at handover
            // false = the rider marked it delivered without the OTP
            $table->boolean('delivery_verified')->nullable()->after('delivered_at');
            $table->string('delivery_note', 300)->nullable()->after('delivery_verified');
            // Short-lived handover code the customer reads out to the rider.
            $table->string('delivery_code', 8)->nullable()->after('delivery_note');
            $table->timestamp('delivery_code_expires_at')->nullable()->after('delivery_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn([
            'delivered_at', 'delivery_verified', 'delivery_note',
            'delivery_code', 'delivery_code_expires_at',
        ]));
    }
};
