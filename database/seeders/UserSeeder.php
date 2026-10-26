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
        $superAdmin = User::firstOrCreate(
            ['email' => 'zesan@gmail.com'],
            [
                'workspace_id' => NULL,
                'name' => 'Zesan',
                'phone' => NULL,
                'avatar' => NULL,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
                'is_active' => 1,
            ]
        );

        $superAdmin->assignRole(RoleEnum::SUPERADMIN->value);

        $admin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'workspace_id' => 1,
                'name' => 'Admin',
                'phone' => NULL,
                'avatar' => NULL,
                'email_verified_at' => now(),
                'password' => bcrypt('password'),
                'is_active' => 1,
            ]
        );

        $admin->assignRole(RoleEnum::ADMIN->value);
    }
}
