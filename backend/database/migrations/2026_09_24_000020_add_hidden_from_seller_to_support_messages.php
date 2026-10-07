<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messages the customer (and NexTech) see but a seller brought into the
     * chat must not — e.g. a gift card's code and password, which anyone
     * holding them could spend.
     */
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->boolean('hidden_from_seller')->default(false)->after('internal');
        });

        // Gift card messages already sent.
        DB::table('support_messages')->where('body', 'like', '%Gift card:%Password:%')->update(['hidden_from_seller' => true]);
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn('hidden_from_seller');
        });
    }
};
