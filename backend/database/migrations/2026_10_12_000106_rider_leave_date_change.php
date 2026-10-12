<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An accepted day off can't be taken back; the rider asks to move it to another date
 * (new_date) and the store confirms or declines. Declined requests are removed once
 * the rider is told.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_leaves', function (Blueprint $table) {
            if (! Schema::hasColumn('rider_leaves', 'new_date')) {
                $table->date('new_date')->nullable()->after('date');
            }
            if (! Schema::hasColumn('rider_leaves', 'change_reason')) {
                $table->string('change_reason', 300)->nullable()->after('new_date');
            }
        });
        DB::table('rider_leaves')->where('status', 'declined')->delete();
    }

    public function down(): void
    {
        Schema::table('rider_leaves', function (Blueprint $table) {
            foreach (['new_date', 'change_reason'] as $col) {
                if (Schema::hasColumn('rider_leaves', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
