<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rider ↔ seller chats stay between them until one side brings in the store's team. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('support_threads', 'admin_called_at')) {
            Schema::table('support_threads', fn (Blueprint $table) => $table->timestamp('admin_called_at')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_threads', 'admin_called_at')) {
            Schema::table('support_threads', fn (Blueprint $table) => $table->dropColumn('admin_called_at'));
        }
    }
};
