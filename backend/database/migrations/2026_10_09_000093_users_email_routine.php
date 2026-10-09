<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Routine reminder emails on / off (riders, sellers, admins); important emails always go. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'email_routine')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('email_routine')->default(true));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'email_routine')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('email_routine'));
        }
    }
};
