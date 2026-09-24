<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The uploaded label PDF on the request itself (the seller downloads it, then ships and adds tracking). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_requests', function (Blueprint $table) {
            $table->string('label_path', 255)->nullable()->after('admin_note');
        });
    }

    public function down(): void
    {
        Schema::table('label_requests', fn (Blueprint $table) => $table->dropColumn('label_path'));
    }
};
