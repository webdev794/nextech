<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('postal_code');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            // Widen to match the relaxed validation (was char(2) / string(10), US-only).
            $table->string('state', 60)->change();
            $table->string('postal_code', 12)->change();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
            $table->char('state', 2)->change();
            $table->string('postal_code', 10)->change();
        });
    }
};
