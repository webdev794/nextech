<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A rider's day-off request waits for the store (seller / admin) to accept or decline it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_leaves', function (Blueprint $table) {
            if (! Schema::hasColumn('rider_leaves', 'status')) {
                $table->string('status', 10)->default('requested')->after('kind'); // requested · approved · declined (absences: approved)
                $table->string('decision_note', 300)->nullable()->after('reason');
                $table->foreignId('decided_by')->nullable()->after('decision_note')->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable()->after('decided_by');
            }
        });
        // Past days and absences count as before; upcoming day-off requests wait for the store's answer.
        DB::table('rider_leaves')->where(fn ($q) => $q->where('kind', '!=', 'leave')->orWhereDate('date', '<', now()->toDateString()))->update(['status' => 'approved']);
        DB::table('rider_leaves')->where('kind', 'leave')->whereDate('date', '>=', now()->toDateString())->whereNull('decided_by')->update(['status' => 'requested']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('rider_leaves', 'status')) {
            Schema::table('rider_leaves', function (Blueprint $table) {
                $table->dropConstrainedForeignId('decided_by');
                $table->dropColumn(['status', 'decision_note', 'decided_at']);
            });
        }
    }
};
