<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riders' work experience (from their application) and when each rider started
 * delivering for each store, so sellers and admin see how long they've worked together.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rider_applications', 'experience_months')) {
            Schema::table('rider_applications', function (Blueprint $table) {
                $table->unsignedSmallInteger('experience_months')->nullable()->after('own_vehicle');
            });
        }
        if (! Schema::hasColumn('rider_store', 'linked_at')) {
            Schema::table('rider_store', function (Blueprint $table) {
                $table->timestamp('linked_at')->nullable()->useCurrent();
            });
        }
        // Existing links: from when the rider started.
        foreach (DB::table('rider_store')->join('users', 'users.id', '=', 'rider_store.user_id')->whereNotNull('users.rider_since')->get(['rider_store.id', 'users.rider_since']) as $row) {
            DB::table('rider_store')->where('id', $row->id)->update(['linked_at' => $row->rider_since]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rider_applications', 'experience_months')) {
            Schema::table('rider_applications', fn (Blueprint $table) => $table->dropColumn('experience_months'));
        }
        if (Schema::hasColumn('rider_store', 'linked_at')) {
            Schema::table('rider_store', fn (Blueprint $table) => $table->dropColumn('linked_at'));
        }
    }
};
