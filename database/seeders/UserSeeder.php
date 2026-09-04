<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates default users and assigns roles using RoleEnum.
     * Permissions are assigned to roles via RoleSeeder.
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

        $superAdmin->assignRole(RoleEnum::SUPERADMIN->value);

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

        $admin->assignRole(RoleEnum::ADMIN->value);
    }
}
