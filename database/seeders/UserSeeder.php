<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = User::create([
            'workspace_id' => NULL,
            'name' => 'Zesan',
            'email' => 'zesan@gmail.com',
            'phone' => NULL,
            'avatar' => NULL,
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'is_active' => 1,
            'last_login_at' => NULL,
            'last_login_ip' => NULL,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $superAdmin->assignRole('superadmin');

        // Get all permission names and sync to superadmin role
        $allPermissionNames = Permission::pluck('name')->all();
        $superAdminRole = Role::findByName('superadmin');
        $superAdminRole->syncPermissions($allPermissionNames);

        $admin = User::create([
            'workspace_id' => 1,
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'phone' => NULL,
            'avatar' => NULL,
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'is_active' => 1,
            'last_login_at' => NULL,
            'last_login_ip' => NULL,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin->assignRole('admin');

        // Get all permission names and sync to admin role
        $adminPermissionNames = Permission::pluck('name')->all();
        $adminRole = Role::findByName('admin');
        $adminRole->syncPermissions($adminPermissionNames);

    }
}
