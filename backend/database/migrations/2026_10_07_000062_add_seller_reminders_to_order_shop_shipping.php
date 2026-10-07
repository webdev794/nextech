<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Chasing sellers who ship themselves: when they were last reminded about an
// order, how many times, and when admin was alerted that it's overdue.
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run on a database that already has them (e.g. imported from edp.sql).
        if (! Schema::hasColumn('order_shop_shipping', 'reminded_at')) {
            Schema::table('order_shop_shipping', function (Blueprint $table) {
                $table->timestamp('reminded_at')->nullable();
                $table->unsignedTinyInteger('reminder_count')->default(0);
                $table->timestamp('escalated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('order_shop_shipping', fn (Blueprint $table) => $table->dropColumn(['reminded_at', 'reminder_count', 'escalated_at']));
    }
};
