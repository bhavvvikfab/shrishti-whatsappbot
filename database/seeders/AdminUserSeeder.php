<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create an admin user
        $user = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]
        );

        // Assign super-admin or admin role using Spatie Permission
        $role = Role::where('name', 'super-admin')->first();
        if (!$role) {
            $role = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        }
        
        $user->assignRole($role);
        
        $this->command->info('Admin user created/updated successfully!');
        $this->command->info('Email: admin@gmail.com');
        $this->command->info('Password: 12345678');
    }
}
