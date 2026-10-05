<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cache of US ZIP-code sales-tax rates from the live lookup (App\Support\SalesTax),
// so each ZIP is fetched about once a month and checkout never waits on it twice.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_tax_rates', function (Blueprint $table) {
            $table->string('zip_code', 5)->primary();
            $table->unsignedSmallInteger('rate_bps');
            $table->string('source', 30)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_tax_rates');
    }
};
