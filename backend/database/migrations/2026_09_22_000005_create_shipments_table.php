<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One shipment per order — kept as its own table (not columns bolted
        // onto orders) so courier-specific fields don't clutter the order
        // record, mirroring how Shop/Seller were kept separate.
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('tracking_number');
            $table->string('carrier');
            $table->string('label_url', 500)->nullable();
            $table->string('status')->default('booked'); // booked|in_transit|delivered|failed
            $table->unsignedInteger('cost_cents')->default(0);
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
