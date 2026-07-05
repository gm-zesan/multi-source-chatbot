<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'superadmin',
                'description' => 'Superadmin - Full access to all features and permissions',
                'guard_name' => 'web',
            ],
            [
                'name' => 'admin',
                'description' => 'Admin - Can manage system and users',
                'guard_name' => 'web',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::create($roleData);
        }
    }
}
