<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'reminder_sent_at')) {
                $table->dateTime('reminder_sent_at')->nullable()->after('scheduled_at');
            }
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            if (!Schema::hasColumn('follow_ups', 'reminder_sent_at')) {
                $table->dateTime('reminder_sent_at')->nullable()->after('follow_up_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'reminder_sent_at')) {
                $table->dropColumn('reminder_sent_at');
            }
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            if (Schema::hasColumn('follow_ups', 'reminder_sent_at')) {
                $table->dropColumn('reminder_sent_at');
            }
        });
    }
};
