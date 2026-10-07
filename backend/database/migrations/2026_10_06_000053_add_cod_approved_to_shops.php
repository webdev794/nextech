<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Seller cash on delivery: admin approval per shop (used when Settings → "Only sellers I approve"). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shops', 'cod_approved')) {
            Schema::table('shops', function (Blueprint $table) {
                $table->boolean('cod_approved')->default(false)->after('accepts_cod');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn('cod_approved'));
    }
};
