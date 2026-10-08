<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fuller rider applications (education, work history, vehicle RC, health, stores in
 * order of priority, consent) and the rider's profile linked to the application
 * they were hired from, with their photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            foreach ([
                'education' => fn () => $table->string('education', 160)->nullable()->after('experience_months'),
                'work_history' => fn () => $table->text('work_history')->nullable()->after('education'),
                'rc_document_path' => fn () => $table->string('rc_document_path')->nullable()->after('license_document_path'),
                'health_issue' => fn () => $table->boolean('health_issue')->default(false)->after('work_history'),
                'health_details' => fn () => $table->string('health_details', 300)->nullable()->after('health_issue'),
                'preferred_store_ids' => fn () => $table->json('preferred_store_ids')->nullable()->after('store_id'),
                'consent_removal' => fn () => $table->boolean('consent_removal')->default(false)->after('health_details'),
            ] as $column => $add) {
                if (! Schema::hasColumn('rider_applications', $column)) {
                    $add();
                }
            }
        });
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'rider_photo_path')) {
                $table->string('rider_photo_path')->nullable()->after('rider_base_lng');
            }
            if (! Schema::hasColumn('users', 'rider_application_id')) {
                $table->unsignedBigInteger('rider_application_id')->nullable()->after('rider_photo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rider_applications', function (Blueprint $table) {
            foreach (['education', 'work_history', 'rc_document_path', 'health_issue', 'health_details', 'preferred_store_ids', 'consent_removal'] as $column) {
                if (Schema::hasColumn('rider_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('users', function (Blueprint $table) {
            foreach (['rider_photo_path', 'rider_application_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
