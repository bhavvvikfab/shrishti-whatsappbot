<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE whatsapp_conversations MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'open'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::table('whatsapp_conversations')
                ->whereNotIn('status', ['open', 'closed', 'archived'])
                ->update(['status' => 'open']);

            DB::statement("ALTER TABLE whatsapp_conversations MODIFY COLUMN status ENUM('open', 'closed', 'archived') NOT NULL DEFAULT 'open'");
        }
    }
};
