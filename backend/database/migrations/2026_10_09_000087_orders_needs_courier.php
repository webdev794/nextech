<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When an order became ready for delivery, and when it was flagged for admin to send by courier by hand. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'ready_at')) {
                $table->timestamp('ready_at')->nullable()->after('status');
                $table->timestamp('needs_courier_at')->nullable()->after('ready_at');
                $table->string('needs_courier_reason', 20)->nullable()->after('needs_courier_at'); // no_rider | outside_area
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'ready_at')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['ready_at', 'needs_courier_at', 'needs_courier_reason']));
        }
    }
};
