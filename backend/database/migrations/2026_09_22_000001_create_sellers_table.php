<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Drives which country's business_type/tax_id/id_type schema
            // (config/countries.php) applies to this application.
            $table->string('country', 2);
            $table->string('business_type', 32);
            $table->string('company_name', 160);
            $table->string('tax_id', 60);

            $table->string('registered_line1', 255);
            $table->string('registered_line2', 255)->nullable();
            $table->string('registered_city', 100);
            $table->string('registered_state', 60);
            $table->string('registered_postal_code', 12);
            $table->string('registered_country', 2);

            $table->string('contact_name', 160);
            $table->string('id_type', 32);
            $table->string('id_number', 60);
            $table->date('date_of_birth');

            // Paths on the private `local` disk — see SellerKycController.
            $table->string('id_document_path')->nullable();
            $table->string('business_document_path')->nullable();

            $table->string('status', 16)->default('pending'); // pending|approved|rejected|suspended
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
