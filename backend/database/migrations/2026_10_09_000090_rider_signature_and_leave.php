<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The rider signs the store's terms (name + place); riders' days off asked for, and days missed. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('rider_applications', 'signed_name')) {
                $table->string('signed_name', 120)->nullable();
                $table->string('signed_place', 120)->nullable();
                $table->timestamp('signed_at')->nullable();
            }
        });
        if (! Schema::hasTable('rider_leaves')) {
            Schema::create('rider_leaves', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->string('kind', 10)->default('leave'); // leave = asked for · absent = missed without asking
                $table->boolean('told_ahead')->default(false);
                $table->string('reason', 300)->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'date', 'kind']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_leaves');
        if (Schema::hasColumn('rider_applications', 'signed_name')) {
            Schema::table('rider_applications', fn (Blueprint $table) => $table->dropColumn(['signed_name', 'signed_place', 'signed_at']));
        }
    }
};
