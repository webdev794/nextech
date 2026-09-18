<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// These columns already exist in some environments (e.g. this project's local
// dev database) without a migration ever having created them, so every
// addition here is guarded — this brings every environment in line without
// erroring where the column is already present.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'rating_avg')) {
                $table->decimal('rating_avg', 3, 2)->nullable()->after('image_url');
            }
            if (! Schema::hasColumn('products', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            }
            if (! Schema::hasColumn('products', 'units_sold')) {
                $table->unsignedInteger('units_sold')->default(0)->after('rating_count');
            }
        });
    }

    public function down(): void
    {
        // Intentionally a no-op: these columns predate this migration in some
        // environments, so dropping them here could destroy pre-existing data.
    }
};
