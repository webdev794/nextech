<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the customer
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('comment')->nullable();   // admin-only — never shown to the rider
            $table->string('source', 16)->default('delivery'); // 'delivery' | 'chat'
            $table->timestamps();

            $table->index(['rider_id', 'created_at']);
        });

        // Denormalised so the admin list and the rider dashboard don't re-average
        // every request.
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('rider_rating_avg', 3, 2)->nullable()->after('rider_is_active');
            $table->unsignedInteger('rider_rating_count')->default(0)->after('rider_rating_avg');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_reviews');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['rider_rating_avg', 'rider_rating_count']));
    }
};
