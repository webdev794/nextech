<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            // What the seller asked for — their balance at request time, capped
            // at the per-transfer maximum. The admin may pay less (daily cap).
            $table->unsignedBigInteger('amount_cents');
            $table->string('status', 16)->default('pending'); // pending | paid | rejected
            $table->string('admin_note', 500)->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('seller_ledger_entries')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
