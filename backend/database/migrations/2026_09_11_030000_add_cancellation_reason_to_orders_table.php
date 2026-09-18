<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Who cancelled it (customer / admin / rider) and why — e.g. a rider
            // reporting the customer refused to pay for a cash-on-delivery order.
            $table->string('cancelled_by', 20)->nullable()->after('status');
            $table->string('cancel_reason', 300)->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cancelled_by', 'cancel_reason']);
        });
    }
};
