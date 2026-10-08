<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hiring riders per store: a store can be "hiring" (shown on the rider application
 * page and the storefront's "Work with us" link), and applications carry more
 * details — email, date of birth (minimum age per country), ID proof, an optional
 * education document and photo, and the rider's own vehicle.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stores', 'hiring_open')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->boolean('hiring_open')->default(false)->after('local_delivery_off_requested_at');
            });
        }
        Schema::table('rider_applications', function (Blueprint $table) {
            foreach ([
                'email' => fn () => $table->string('email', 160)->nullable()->after('phone'),
                'date_of_birth' => fn () => $table->date('date_of_birth')->nullable()->after('email'),
                'id_document_path' => fn () => $table->string('id_document_path')->nullable()->after('license_document_path'),
                'education_document_path' => fn () => $table->string('education_document_path')->nullable()->after('id_document_path'),
                'photo_path' => fn () => $table->string('photo_path')->nullable()->after('education_document_path'),
                'own_vehicle' => fn () => $table->boolean('own_vehicle')->default(false)->after('vehicle_type'),
                'decided_by_seller' => fn () => $table->boolean('decided_by_seller')->default(false)->after('reviewed_at'),
            ] as $column => $add) {
                if (! Schema::hasColumn('rider_applications', $column)) {
                    $add();
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('stores', 'hiring_open')) {
            Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('hiring_open'));
        }
        Schema::table('rider_applications', function (Blueprint $table) {
            foreach (['email', 'date_of_birth', 'id_document_path', 'education_document_path', 'photo_path', 'own_vehicle', 'decided_by_seller'] as $column) {
                if (Schema::hasColumn('rider_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
