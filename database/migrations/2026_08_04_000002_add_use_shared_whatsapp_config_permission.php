<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('use_shared_whatsapp_config', 'web');
    }

    public function down(): void
    {
        Permission::where('name', 'use_shared_whatsapp_config')
            ->where('guard_name', 'web')
            ->delete();
    }
};
