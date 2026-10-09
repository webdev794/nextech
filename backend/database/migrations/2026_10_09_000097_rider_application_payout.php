<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How the applicant wants to be paid (copied to their rider account when approved). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('rider_applications', 'payout_method')) {
                $table->string('payout_method', 20)->nullable();
                $table->text('payout_details')->nullable(); // encrypted
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('rider_applications', 'payout_method')) {
            Schema::table('rider_applications', fn (Blueprint $table) => $table->dropColumn(['payout_method', 'payout_details']));
        }
    }
};
