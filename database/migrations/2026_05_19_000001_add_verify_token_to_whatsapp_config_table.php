<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_config', 'verify_token')) {
                $table->string('verify_token')->nullable()->after('webhook_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_config', 'verify_token')) {
                $table->dropColumn('verify_token');
            }
        });
    }
};
