<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Support tickets on rider ↔ seller chats: who opened it, and where it stands. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            if (! Schema::hasColumn('support_threads', 'ticket_status')) {
                $table->string('ticket_by', 10)->nullable();      // rider | seller
                $table->string('ticket_status', 12)->nullable();  // open · resolved · declined · withdrawn
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_threads', 'ticket_status')) {
            Schema::table('support_threads', fn (Blueprint $table) => $table->dropColumn(['ticket_by', 'ticket_status']));
        }
    }
};
