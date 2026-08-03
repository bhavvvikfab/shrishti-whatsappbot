<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. facebook_accounts
        Schema::create('facebook_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('fb_user_id')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->text('access_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // CRM User association
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 2. facebook_pages
        Schema::create('facebook_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facebook_account_id');
            $table->string('page_id')->unique();
            $table->string('name');
            $table->text('access_token'); // Page Access Token (long-lived)
            $table->boolean('is_synced')->default(false);
            $table->boolean('is_subscribed')->default(false); // Webhook subscription
            $table->timestamps();

            $table->foreign('facebook_account_id')->references('id')->on('facebook_accounts')->onDelete('cascade');
        });

        // 3. facebook_lead_forms
        Schema::create('facebook_lead_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facebook_page_id');
            $table->string('form_id')->unique();
            $table->string('name');
            $table->string('status')->nullable(); // e.g. ACTIVE, ARCHIVED
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->foreign('facebook_page_id')->references('id')->on('facebook_pages')->onDelete('cascade');
        });

        // 4. facebook_leads
        Schema::create('facebook_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facebook_lead_form_id')->nullable();
            $table->string('lead_id')->unique();
            $table->string('page_id')->index();
            $table->string('form_id')->index();
            $table->string('ad_id')->nullable()->index();
            $table->string('ad_name')->nullable();
            $table->string('campaign_id')->nullable()->index();
            $table->string('campaign_name')->nullable();
            $table->string('platform')->nullable(); // fb, ig, messenger, etc.
            $table->json('field_data')->nullable(); // Full payload mapping
            $table->string('full_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->boolean('is_imported')->default(false);
            $table->unsignedBigInteger('imported_lead_id')->nullable(); // FK to core leads table
            $table->timestamp('created_time')->nullable();
            $table->timestamps();

            $table->foreign('facebook_lead_form_id')->references('id')->on('facebook_lead_forms')->onDelete('cascade');
            $table->foreign('imported_lead_id')->references('id')->on('leads')->onDelete('set null');
        });

        // 5. facebook_campaigns
        Schema::create('facebook_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facebook_account_id');
            $table->string('campaign_id')->unique();
            $table->string('name');
            $table->string('status')->nullable();
            $table->string('objective')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->foreign('facebook_account_id')->references('id')->on('facebook_accounts')->onDelete('cascade');
        });

        // 6. facebook_adsets
        Schema::create('facebook_adsets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facebook_campaign_id');
            $table->string('adset_id')->unique();
            $table->string('campaign_id')->index();
            $table->string('name');
            $table->string('status')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->foreign('facebook_campaign_id')->references('id')->on('facebook_campaigns')->onDelete('cascade');
        });

        // 7. facebook_ads
        Schema::create('facebook_ads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facebook_adset_id');
            $table->string('ad_id')->unique();
            $table->string('adset_id')->index();
            $table->string('name');
            $table->string('status')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->foreign('facebook_adset_id')->references('id')->on('facebook_adsets')->onDelete('cascade');
        });

        // 8. webhook_logs
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('facebook_ads');
        Schema::dropIfExists('facebook_adsets');
        Schema::dropIfExists('facebook_campaigns');
        Schema::dropIfExists('facebook_leads');
        Schema::dropIfExists('facebook_lead_forms');
        Schema::dropIfExists('facebook_pages');
        Schema::dropIfExists('facebook_accounts');
    }
};
