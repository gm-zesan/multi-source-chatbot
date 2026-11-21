<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceWalletService;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates default users across distinct roles:
     * - superadmin (Platform Owner)
     * - platform_support (Platform Technical Support)
     * - admin (Business Owner / Merchant Admin)
     * - workspace_manager (Store Manager)
     * - workspace_agent (Support Agent)
     */
    public function run(): void
    {
        // 1. Superadmin (Platform Owner)
        $superAdmin = User::updateOrCreate(
            ['email' => 'zesan@gmail.com'],
            [
                'workspace_id'      => 1,
                'name'              => 'Zesan (Platform Owner)',
                'phone'             => '+8801700000001',
                'avatar'            => null,
                'email_verified_at' => now(),
                'password'          => bcrypt('password'),
                'is_active'         => 1,
            ]
        );
        $superAdmin->syncRoles([RoleEnum::SUPERADMIN->value]);

        // 2. Platform Support Staff (Hired by Superadmin)
        $platformSupport = User::updateOrCreate(
            ['email' => 'support@platform.com'],
            [
                'workspace_id'      => 1,
                'name'              => 'Shuvo (Platform Support)',
                'phone'             => '+8801700000002',
                'avatar'            => null,
                'email_verified_at' => now(),
                'password'          => bcrypt('Password123!'),
                'is_active'         => 1,
            ]
        );
        $platformSupport->syncRoles([RoleEnum::PLATFORM_SUPPORT->value]);

        // 3. Workspace 2: Ensure Apex Solutions exists and has a wallet
        $workspace2 = Workspace::firstOrCreate(
            ['id' => 2],
            [
                'name'      => 'Apex Solutions',
                'slug'      => 'apex-solutions',
                'is_active' => true,
            ]
        );

        app(WorkspaceWalletService::class)->getOrCreateWallet($workspace2);

        // 4. Businessman (Business Owner / Admin of Apex Solutions)
        $merchant = User::updateOrCreate(
            ['email' => 'merchant@apex.com'],
            [
                'workspace_id'      => $workspace2->id,
                'name'              => 'Rahim Ahmed (Apex Owner)',
                'phone'             => '+8801811111111',
                'avatar'            => null,
                'email_verified_at' => now(),
                'password'          => bcrypt('Password123!'),
                'is_active'         => 1,
            ]
        );
        $merchant->syncRoles([RoleEnum::ADMIN->value]);

        // 5. Store Manager (Employee of Apex Solutions)
        $manager = User::updateOrCreate(
            ['email' => 'manager@apex.com'],
            [
                'workspace_id'      => $workspace2->id,
                'name'              => 'Farhan (Apex Store Manager)',
                'phone'             => '+8801822222222',
                'avatar'            => null,
                'email_verified_at' => now(),
                'password'          => bcrypt('Password123!'),
                'is_active'         => 1,
            ]
        );
        $manager->syncRoles([RoleEnum::WORKSPACE_MANAGER->value]);

        // 6. Support & Sales Agent (Employee of Apex Solutions)
        $agent = User::updateOrCreate(
            ['email' => 'agent@apex.com'],
            [
                'workspace_id'      => $workspace2->id,
                'name'              => 'Sumon (Apex Support Agent)',
                'phone'             => '+8801833333333',
                'avatar'            => null,
                'email_verified_at' => now(),
                'password'          => bcrypt('Password123!'),
                'is_active'         => 1,
            ]
        );
        $agent->syncRoles([RoleEnum::WORKSPACE_AGENT->value]);
    }
}
