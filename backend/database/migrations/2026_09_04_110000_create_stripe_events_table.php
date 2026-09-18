<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_events', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type')->index();
            $table->timestamp('processed_at');
        });
    }

    public function down(): void { Schema::dropIfExists('stripe_events'); }
};
