<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('purpose'); // register | login
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['email', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_otps');
    }
};
