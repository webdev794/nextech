<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Stamped once the delivered-order receipt email has gone out, so it
            // is never sent twice however many times the order is re-saved.
            $table->timestamp('receipt_emailed_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('receipt_emailed_at');
        });
    }
};
