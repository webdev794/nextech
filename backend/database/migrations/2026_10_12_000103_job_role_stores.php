<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Which stores a job is for (a seller's store → that seller sees the applications); fuller rider requirements. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('job_roles', 'store_ids')) {
            Schema::table('job_roles', fn (Blueprint $table) => $table->json('store_ids')->nullable()->after('location'));
        }
        DB::table('job_roles')->where('kind', 'rider')->where('requirements', 'Minimum age, ID, your own vehicle (and licence for motor vehicles).')->update([
            'requirements' => "Minimum age (18 or as the country requires) and an ID proof\nYour own vehicle — with a driving licence and vehicle papers for motor vehicles\nA smartphone for the Rider app\nPast delivery or driving experience helps\nYour education (any level)\nGood health for the work, and knowing the local area\nAble to work the store's hours",
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('job_roles', 'store_ids')) {
            Schema::table('job_roles', fn (Blueprint $table) => $table->dropColumn('store_ids'));
        }
    }
};
