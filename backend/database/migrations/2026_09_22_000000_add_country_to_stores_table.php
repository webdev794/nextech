<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('postal_code');
        });

        // Every existing store predates the country foundation — backfill to
        // US, today's implicit default.
        DB::table('stores')->whereNull('country')->update(['country' => 'US']);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
