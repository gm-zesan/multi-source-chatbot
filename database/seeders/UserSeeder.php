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
            'workspace_id' => 1,
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
        $permissions = Permission::pluck('id','name')->all();
        $superAdminRole = Role::findByName('superadmin');
        $superAdminRole->syncPermissions($permissions);


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
        $permissionsAdmin = Permission::whereNotIn('name', [])->pluck('id','name')->all();
        $adminRole = Role::findByName('admin');
        $adminRole->syncPermissions($permissionsAdmin);

    }
}
