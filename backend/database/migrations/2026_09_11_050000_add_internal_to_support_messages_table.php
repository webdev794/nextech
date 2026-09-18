<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            // A staff-only note (e.g. why a refund was issued) — recorded in the
            // same thread for later admin reference, but never shown to the
            // customer reading their own support conversation.
            $table->boolean('internal')->default(false)->after('is_staff');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn('internal');
        });
    }
};
