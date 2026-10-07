<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer CRM: a log of every email sent to a customer (order emails, codes,
 * rider messages, custom and campaign emails), admin email campaigns that
 * send now or on a schedule, and a marketing opt-out per user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('subject', 200);
            $table->text('body'); // markdown; {name} / {first_name} placeholders
            // {"user_id": 12} for one customer, or a filter:
            // {"market": "IN", "has_ordered": true, "no_order_in_days": 30}
            $table->json('audience');
            $table->boolean('promotional')->default(true); // skips opted-out users, adds unsubscribe
            $table->timestamp('send_at')->nullable();
            $table->string('repeat', 10)->default('none'); // none | daily | weekly | monthly
            $table->string('status', 12)->default('draft'); // draft | scheduled | sending | sent | cancelled
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'send_at']);
        });

        Schema::create('customer_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to_email', 190);
            $table->string('kind', 30); // otp | order_confirmed | order_shipped | delivery_code | rider_message | order_delivered | custom | campaign | …
            $table->string('subject', 255)->nullable();
            $table->longText('body_html')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('email_campaigns')->nullOnDelete();
            $table->string('status', 12)->default('sent'); // sent | failed
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_opt_out')->default(false)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('marketing_opt_out'));
        Schema::dropIfExists('customer_emails');
        Schema::dropIfExists('email_campaigns');
    }
};
