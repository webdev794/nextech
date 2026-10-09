<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A rider asking to move to a new home area: {address, lat, lng, at}; the store approves or declines. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'rider_move_request')) {
            Schema::table('users', fn (Blueprint $table) => $table->json('rider_move_request')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'rider_move_request')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('rider_move_request'));
        }
    }
};
