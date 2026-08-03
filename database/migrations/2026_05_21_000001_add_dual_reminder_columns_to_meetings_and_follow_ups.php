<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'first_reminder_sent_at')) {
                $table->dateTime('first_reminder_sent_at')->nullable()->after('reminder_sent_at');
            }

            if (!Schema::hasColumn('meetings', 'final_reminder_sent_at')) {
                $table->dateTime('final_reminder_sent_at')->nullable()->after('first_reminder_sent_at');
            }
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            if (!Schema::hasColumn('follow_ups', 'first_reminder_sent_at')) {
                $table->dateTime('first_reminder_sent_at')->nullable()->after('reminder_sent_at');
            }

            if (!Schema::hasColumn('follow_ups', 'final_reminder_sent_at')) {
                $table->dateTime('final_reminder_sent_at')->nullable()->after('first_reminder_sent_at');
            }
        });

        if (Schema::hasColumn('meetings', 'reminder_sent_at')) {
            DB::table('meetings')
                ->whereNull('first_reminder_sent_at')
                ->whereNotNull('reminder_sent_at')
                ->update(['first_reminder_sent_at' => DB::raw('reminder_sent_at')]);
        }

        if (Schema::hasColumn('follow_ups', 'reminder_sent_at')) {
            DB::table('follow_ups')
                ->whereNull('first_reminder_sent_at')
                ->whereNotNull('reminder_sent_at')
                ->update(['first_reminder_sent_at' => DB::raw('reminder_sent_at')]);
        }
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'final_reminder_sent_at')) {
                $table->dropColumn('final_reminder_sent_at');
            }

            if (Schema::hasColumn('meetings', 'first_reminder_sent_at')) {
                $table->dropColumn('first_reminder_sent_at');
            }
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            if (Schema::hasColumn('follow_ups', 'final_reminder_sent_at')) {
                $table->dropColumn('final_reminder_sent_at');
            }

            if (Schema::hasColumn('follow_ups', 'first_reminder_sent_at')) {
                $table->dropColumn('first_reminder_sent_at');
            }
        });
    }
};
