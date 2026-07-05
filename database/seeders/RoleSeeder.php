<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = array(
            array('id' => '1','name' => 'superadmin', 'description' => 'All permission and access are enabled for this role', 'guard_name' => 'web'),
            array('id' => '2','name' => 'admin', 'description' => 'Admin can observe everything without role', 'guard_name' => 'web')
        );
        foreach($roles as $role)
        {
            Role::create($role);
        }
    }
}
