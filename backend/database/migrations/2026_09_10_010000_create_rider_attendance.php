<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Live "can the system offer this rider an order" flag. Derived from
            // the shift state (clocked in, not on a break) and any explicit
            // admin/rider pause. A rider is off until they clock in.
            $table->boolean('rider_available')->default(false)->after('rider_missed_count');
            $table->string('rider_unavailable_reason', 200)->nullable()->after('rider_available');
            // Heartbeat — stamped on every authenticated /rider/* request.
            $table->timestamp('rider_last_seen_at')->nullable()->after('rider_unavailable_reason');
        });

        // One row per work session (check-in .. check-out).
        Schema::create('rider_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('clock_in_at');
            $table->timestamp('clock_out_at')->nullable(); // null = still on the clock
            $table->string('source', 10)->default('rider'); // 'rider' | 'admin'
            $table->timestamps();

            $table->index(['user_id', 'clock_in_at']);
        });

        // Lunch / other breaks within a shift — time off the clock.
        Schema::create('rider_shift_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_shift_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 80)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable(); // null = still on the break
            $table->timestamps();

            $table->index('rider_shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_shift_breaks');
        Schema::dropIfExists('rider_shifts');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'rider_available', 'rider_unavailable_reason', 'rider_last_seen_at',
        ]));
    }
};
