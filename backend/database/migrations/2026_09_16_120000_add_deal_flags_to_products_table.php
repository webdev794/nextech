<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Mutually exclusive: a product is at most one of these.
            $table->string('deal_type')->nullable()->after('is_active');
            // Independent of deal_type — a product can be exclusive on its own,
            // or on top of a lightning/unbeatable deal.
            $table->boolean('is_exclusive_offer')->default(false)->after('deal_type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['deal_type', 'is_exclusive_offer']);
        });
    }
};
