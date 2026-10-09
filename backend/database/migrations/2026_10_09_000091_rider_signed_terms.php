<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The exact terms a rider signed, kept with their application. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rider_applications', 'signed_terms')) {
            Schema::table('rider_applications', fn (Blueprint $table) => $table->json('signed_terms')->nullable()->after('signed_at'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rider_applications', 'signed_terms')) {
            Schema::table('rider_applications', fn (Blueprint $table) => $table->dropColumn('signed_terms'));
        }
    }
};
