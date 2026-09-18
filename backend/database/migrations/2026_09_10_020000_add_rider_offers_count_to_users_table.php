<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Total delivery offers this rider has received — the denominator for
            // their acceptance rate (accepted = offers - declined - missed).
            $table->unsignedInteger('rider_offers_count')->default(0)->after('rider_missed_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('rider_offers_count'));
    }
};
