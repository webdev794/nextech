<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A rider says they handed the cash over; the seller confirms or disputes within a day (else it counts as received). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('order_packages', 'cash_handover_claimed_at')) {
                $table->timestamp('cash_handover_claimed_at')->nullable()->after('cash_handed_over_at');
                $table->timestamp('cash_disputed_at')->nullable()->after('cash_handover_claimed_at');
                $table->string('cash_dispute_note', 300)->nullable()->after('cash_disputed_at');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_packages', 'cash_handover_claimed_at')) {
            Schema::table('order_packages', fn (Blueprint $table) => $table->dropColumn(['cash_handover_claimed_at', 'cash_disputed_at', 'cash_dispute_note']));
        }
    }
};
