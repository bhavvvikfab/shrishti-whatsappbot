<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('view_whatsapp', 'web');
    }

    public function down(): void
    {
        Permission::where('name', 'view_whatsapp')
            ->where('guard_name', 'web')
            ->delete();
    }
};
