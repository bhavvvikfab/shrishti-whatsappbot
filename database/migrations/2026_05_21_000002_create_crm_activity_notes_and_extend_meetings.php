<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activity_notes', function (Blueprint $table) {
            $table->id();
            $table->string('noteable_type');
            $table->unsignedBigInteger('noteable_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->string('note_type')->default('general');
            $table->boolean('is_private')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['noteable_type', 'noteable_id', 'created_at']);
        });

        Schema::table('follow_ups', function (Blueprint $table) {
            if (!Schema::hasColumn('follow_ups', 'related_type')) {
                $table->string('related_type')->nullable()->after('customer_id');
            }

            if (!Schema::hasColumn('follow_ups', 'related_id')) {
                $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            }

            $table->index(['related_type', 'related_id']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            if (!Schema::hasColumn('meetings', 'related_type')) {
                $table->string('related_type')->nullable()->after('customer_id');
            }

            if (!Schema::hasColumn('meetings', 'related_id')) {
                $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
            }

            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            if (Schema::hasColumn('follow_ups', 'related_type') && Schema::hasColumn('follow_ups', 'related_id')) {
                $table->dropIndex(['related_type', 'related_id']);
            }

            if (Schema::hasColumn('follow_ups', 'related_id')) {
                $table->dropColumn('related_id');
            }

            if (Schema::hasColumn('follow_ups', 'related_type')) {
                $table->dropColumn('related_type');
            }
        });

        Schema::table('meetings', function (Blueprint $table) {
            if (Schema::hasColumn('meetings', 'related_type') && Schema::hasColumn('meetings', 'related_id')) {
                $table->dropIndex(['related_type', 'related_id']);
            }

            if (Schema::hasColumn('meetings', 'related_id')) {
                $table->dropColumn('related_id');
            }

            if (Schema::hasColumn('meetings', 'related_type')) {
                $table->dropColumn('related_type');
            }
        });

        Schema::dropIfExists('crm_activity_notes');
    }
};
