<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tickets handled by a named support person ("Mak from … Support"), shown on each reply. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('support_threads', 'support_agent')) {
            Schema::table('support_threads', fn (Blueprint $table) => $table->string('support_agent', 40)->nullable());
        }
        if (! Schema::hasColumn('support_messages', 'agent_name')) {
            Schema::table('support_messages', fn (Blueprint $table) => $table->string('agent_name', 40)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_threads', 'support_agent')) {
            Schema::table('support_threads', fn (Blueprint $table) => $table->dropColumn('support_agent'));
        }
        if (Schema::hasColumn('support_messages', 'agent_name')) {
            Schema::table('support_messages', fn (Blueprint $table) => $table->dropColumn('agent_name'));
        }
    }
};
