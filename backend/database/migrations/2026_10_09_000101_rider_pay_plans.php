<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A NexTech rider's pay plan: null = per delivery; or monthly pay with a target, bonus and cap. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'rider_pay_plan')) {
            Schema::table('users', fn (Blueprint $table) => $table->json('rider_pay_plan')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'rider_pay_plan')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('rider_pay_plan'));
        }
    }
};
