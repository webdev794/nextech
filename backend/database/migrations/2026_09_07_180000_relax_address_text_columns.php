<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The delivery point is the map pin (latitude/longitude). The address text
     * is a best-effort label — geocoders (especially outside the US) often can't
     * fill a structured city/state/postcode — so these stop being required, and
     * `state` widens from a 2-char code to a region name.
     */
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->change();
            $table->string('state', 60)->nullable()->change();
            $table->string('postal_code', 12)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('city', 100)->nullable(false)->change();
            $table->char('state', 2)->nullable(false)->change();
            $table->string('postal_code', 10)->nullable(false)->change();
        });
    }
};
