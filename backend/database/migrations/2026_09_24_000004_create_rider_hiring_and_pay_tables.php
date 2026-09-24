<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Someone applying to deliver for a store near them — reviewed by an
        // admin like a seller application. Approval flips users.is_rider.
        Schema::create('rider_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete(); // preferred store
            $table->string('phone', 40);
            $table->string('home_address', 255);
            $table->decimal('home_lat', 10, 7)->nullable();
            $table->decimal('home_lng', 10, 7)->nullable();
            $table->string('vehicle_type', 20); // bicycle | scooter | motorbike | car
            $table->string('license_number', 60)->nullable();
            $table->string('license_document_path')->nullable(); // private `local` disk, kyc/{user_id}/...
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Append-only rider pay ledger, same shape as the seller one — a
        // rider's balance is always SUM(amount_cents).
        Schema::create('rider_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // null only for a payout row
            $table->string('type', 20); // delivery_credit | payout_debit
            $table->bigInteger('amount_cents');
            $table->decimal('distance_miles', 8, 2)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['order_id', 'type']);
        });

        Schema::create('rider_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('status', 16)->default('pending'); // pending | paid | rejected
            $table->string('admin_note', 500)->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('rider_ledger_entries')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('rider_payout_method', 20)->nullable(); // bank | paypal
            $table->json('rider_payout_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rider_payout_method', 'rider_payout_details']);
        });
        Schema::dropIfExists('rider_payout_requests');
        Schema::dropIfExists('rider_ledger_entries');
        Schema::dropIfExists('rider_applications');
    }
};
