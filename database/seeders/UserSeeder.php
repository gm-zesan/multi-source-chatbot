<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = array(
            array('id' => '1','workspace_id' => '1','name' => 'Zesan','email' => 'zesan@gmail.com','phone' => NULL,'avatar' => NULL,'email_verified_at' => '2026-07-01 19:15:45','password' => bcrypt('password'),'remember_token' => NULL,'is_active' => '1','last_login_at' => NULL,'last_login_ip' => NULL,'created_at' => '2026-07-01 19:15:45','updated_at' => '2026-07-01 19:15:45','deleted_at' => NULL),
        );
        foreach ($users as $user) {
            User::create($user);
        }
    }
}
