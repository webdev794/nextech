<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // When this account became a delivery rider. The attendance report
            // only counts "days off" from this date onward — days before it are
            // "not a rider yet", not absences.
            $table->timestamp('rider_since')->nullable()->after('rider_daily_target_minutes');
        });

        // Backfill existing riders: earliest recorded shift, else account creation.
        foreach (DB::table('users')->where('is_rider', true)->whereNull('rider_since')->pluck('created_at', 'id') as $id => $created) {
            $firstShift = DB::table('rider_shifts')->where('user_id', $id)->min('clock_in_at');
            DB::table('users')->where('id', $id)->update(['rider_since' => $firstShift ?: $created]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('rider_since'));
    }
};
