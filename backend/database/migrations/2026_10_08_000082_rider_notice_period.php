<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A rider giving notice (30 days) before they stop working, and admin settling it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'rider_notice_at')) {
                $table->timestamp('rider_notice_at')->nullable()->after('rider_application_id');
                $table->date('rider_leaving_on')->nullable()->after('rider_notice_at');
                $table->timestamp('rider_notice_processed_at')->nullable()->after('rider_leaving_on');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['rider_notice_at', 'rider_leaving_on', 'rider_notice_processed_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
