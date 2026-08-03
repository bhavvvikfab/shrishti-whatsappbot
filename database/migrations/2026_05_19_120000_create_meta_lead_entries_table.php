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
        Schema::create('meta_lead_entries', function (Blueprint $table) {
            $table->id();
            $table->string('page_id')->nullable();
            $table->string('form_id')->nullable();
            $table->string('leadgen_id')->unique();
            $table->string('ad_id')->nullable();
            $table->string('adgroup_id')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('platform')->nullable();
            $table->timestamp('created_time')->nullable();
            $table->string('status')->default('received');
            $table->longText('webhook_payload')->nullable();
            $table->longText('lead_data')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->text('fetch_error')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'form_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_lead_entries');
    }
};
