<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            // The customer's satisfaction rating for the conversation itself.
            $table->unsignedTinyInteger('rating')->nullable()->after('resolved_at');
            $table->text('rating_comment')->nullable()->after('rating');
            $table->timestamp('rated_at')->nullable()->after('rating_comment');
        });
    }

    public function down(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            $table->dropColumn(['rating', 'rating_comment', 'rated_at']);
        });
    }
};
