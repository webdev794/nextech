<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual NexTech labels: a seller asks for a shipping label, an admin buys it
 * on NexTech's courier account, uploads the label file with the tracking
 * number and postage, and the seller downloads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ship_from_address_id')->nullable()->constrained('shop_addresses')->nullOnDelete();
            $table->json('items'); // [{order_item_id, quantity}]
            $table->string('status', 12)->default('requested'); // requested | ready | cancelled
            $table->string('note', 500)->nullable(); // seller: weight, box size…
            $table->string('admin_note', 500)->nullable(); // e.g. why it was cancelled
            $table->foreignId('order_package_id')->nullable()->constrained('order_packages')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::table('order_packages', function (Blueprint $table) {
            // An uploaded label file (private disk) — set for admin-uploaded labels.
            $table->string('label_path', 255)->nullable()->after('label_url');
        });
    }

    public function down(): void
    {
        Schema::table('order_packages', fn (Blueprint $table) => $table->dropColumn('label_path'));
        Schema::dropIfExists('label_requests');
    }
};
