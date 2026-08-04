<?php

use App\Models\User;
use App\Models\WhatsappConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_config', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->index('user_id');
            }
        });

        $legacyConfig = WhatsappConfig::query()->orderBy('id')->first();
        if ($legacyConfig && ! $legacyConfig->user_id) {
            $admin = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-admin']))
                ->orderBy('id')
                ->first();

            if ($admin) {
                $legacyConfig->user_id = $admin->id;
                $legacyConfig->save();
            }
        }
    }

    public function down(): void
    {
        Schema::table('whatsapp_config', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_config', 'user_id')) {
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};
