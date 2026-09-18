<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('handling_fee_cents')->default(0)->after('delivery_fee_cents');
            $table->unsignedInteger('small_cart_fee_cents')->default(0)->after('handling_fee_cents');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['handling_fee_cents', 'small_cart_fee_cents']);
        });
    }
};
