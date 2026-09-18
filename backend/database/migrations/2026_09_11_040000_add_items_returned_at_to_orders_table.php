<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Null while a rider is still holding the physical items from a
            // cancelled cash-on-delivery order (e.g. the customer refused to
            // pay at the door) — stamped once an admin confirms they're back
            // at the store. Mirrors cash_settled_at, but for goods, not cash.
            $table->timestamp('items_returned_at')->nullable()->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('items_returned_at');
        });
    }
};
