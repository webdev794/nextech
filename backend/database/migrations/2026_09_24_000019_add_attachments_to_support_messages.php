<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Photos attached to a chat message (e.g. a damaged item) — a list of media URLs. */
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
