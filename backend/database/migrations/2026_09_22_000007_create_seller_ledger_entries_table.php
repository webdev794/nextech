<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only ledger of everything that moves a shop's balance: an
        // order_credit per shop on a paid order (net of commission), a
        // refund_debit clawing back a shop's pro-rata share of a refund, and a
        // payout_debit an admin records when they settle the balance outside
        // the app (bank transfer etc). Balance is always SUM(amount_cents) —
        // never a mutated column.
        Schema::create('seller_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // null only for a payout row
            $table->string('type', 20); // order_credit|refund_debit|payout_debit
            $table->integer('amount_cents'); // signed: positive credits, negative debits
            $table->integer('commission_cents')->nullable(); // informational only
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
            $table->index(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_ledger_entries');
    }
};
