<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Result of checking a seller's hosted download link: ok | page | broken. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_files', 'link_status')) {
            Schema::table('product_files', function (Blueprint $table) {
                $table->string('link_status', 12)->nullable()->after('external_url');
                $table->string('link_note', 255)->nullable()->after('link_status');
                $table->timestamp('link_checked_at')->nullable()->after('link_note');
            });
        }
    }

    public function down(): void
    {
        Schema::table('product_files', fn (Blueprint $table) => $table->dropColumn(['link_status', 'link_note', 'link_checked_at']));
    }
};
