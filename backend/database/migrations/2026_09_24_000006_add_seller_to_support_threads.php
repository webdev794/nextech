<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An admin can bring a seller into a customer's order chat (a three-way
     * conversation; NexTech stays in it). seller_shop_id is the shop taking
     * part; from_seller marks that shop's messages.
     */
    public function up(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            $table->foreignId('seller_shop_id')->nullable()->after('order_id')->constrained('shops')->nullOnDelete();
            $table->timestamp('seller_joined_at')->nullable()->after('seller_shop_id');
        });

        Schema::table('support_messages', function (Blueprint $table) {
            $table->boolean('from_seller')->default(false)->after('is_staff');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn('from_seller');
        });

        Schema::table('support_threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_shop_id');
            $table->dropColumn('seller_joined_at');
        });
    }
};
