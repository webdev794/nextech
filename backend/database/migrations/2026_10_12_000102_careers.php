<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Careers: job roles admin opens (Careers page) and the applications; plus a "Partner with us" page. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('job_roles')) {
            Schema::create('job_roles', function (Blueprint $table) {
                $table->id();
                $table->string('title', 120);
                $table->string('kind', 10)->default('general'); // rider = the rider application; general = a short form
                $table->string('location', 120)->nullable();
                $table->text('description')->nullable();
                $table->text('requirements')->nullable();
                $table->boolean('is_open')->default(true);
                $table->timestamps();
            });
            DB::table('job_roles')->insert(['title' => 'Delivery rider', 'kind' => 'rider', 'location' => 'Near our stores', 'description' => 'Deliver orders from a store near you, on your own vehicle.', 'requirements' => 'Minimum age, ID, your own vehicle (and licence for motor vehicles).', 'is_open' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        if (! Schema::hasTable('job_applications')) {
            Schema::create('job_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('job_role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 120);
                $table->string('email', 160);
                $table->string('phone', 40)->nullable();
                $table->string('link', 500)->nullable(); // CV / profile link
                $table->text('message')->nullable();
                $table->string('status', 12)->default('new'); // new · reviewed · hired · declined
                $table->timestamps();
            });
        }
        if (Schema::hasTable('pages') && ! DB::table('pages')->where('slug', 'partner')->exists()) {
            DB::table('pages')->insert(['slug' => 'partner', 'title' => 'Partner with us', 'content' => 'Grow with us — sell your products, deliver orders near you, or work with us in other ways.',
                'is_published' => true, 'show_in_footer' => true, 'footer_group' => 'useful_links', 'sort_order' => 90, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_roles');
    }
};
