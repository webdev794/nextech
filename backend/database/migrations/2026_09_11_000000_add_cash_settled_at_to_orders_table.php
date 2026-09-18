<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Null while the rider is still holding the cash they collected on a
            // COD order; stamped when an admin confirms it's been handed back to
            // the store. Independent of delivery status — cash is collected on
            // hand-off, settlement happens separately (often at shift end).
            $table->timestamp('cash_settled_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cash_settled_at');
        });
    }
};
