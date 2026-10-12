<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When the buyer is usually at an address (e.g. "Mon–Fri after 6 pm"), shown to riders and sellers. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('addresses', 'available_times')) {
            Schema::table('addresses', fn (Blueprint $table) => $table->string('available_times', 120)->nullable()->after('postal_code'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('addresses', 'available_times')) {
            Schema::table('addresses', fn (Blueprint $table) => $table->dropColumn('available_times'));
        }
    }
};
