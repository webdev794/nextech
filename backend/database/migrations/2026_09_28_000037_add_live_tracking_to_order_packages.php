<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Live courier tracking (AfterShip) for packages sellers ship: the tracker's
// id, its latest status and scans, and the courier's delivery estimate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_packages', function (Blueprint $table) {
            $table->string('tracking_ref', 64)->nullable()->after('tracking_number');
            $table->string('tracking_tag', 32)->nullable()->after('tracking_ref');
            $table->string('tracking_detail', 255)->nullable()->after('tracking_tag');
            $table->date('tracking_eta')->nullable()->after('tracking_detail');
            $table->json('tracking_events')->nullable()->after('tracking_eta');
            $table->timestamp('tracking_synced_at')->nullable()->after('tracking_events');
        });
    }

    public function down(): void
    {
        Schema::table('order_packages', function (Blueprint $table) {
            $table->dropColumn(['tracking_ref', 'tracking_tag', 'tracking_detail', 'tracking_eta', 'tracking_events', 'tracking_synced_at']);
        });
    }
};
