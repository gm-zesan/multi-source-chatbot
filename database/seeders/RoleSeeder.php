<?php

namespace Database\Seeders;

use App\Enums\Permissions\ChannelPermission;
use App\Enums\Permissions\ConversationPermission;
use App\Enums\Permissions\MessagePermission;
use App\Enums\Permissions\UserPermission;
use App\Enums\Permissions\WorkspaceUserPermission;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates roles and assigns permissions using RoleEnum and module-based Permission enums.
     * To add a new role:
     *   1. Add a case to App\Enums\RoleEnum
     *   2. Define its permissions in this seeder
     *   3. Run: php artisan db:seed --class=RoleSeeder
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── 1. Create roles ──────────────────────────────────────────────
        $superadmin = Role::updateOrCreate(
            ['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web'],
            ['description' => RoleEnum::SUPERADMIN->label() . ' - Full access to all features and permissions'],
        );

        $admin = Role::updateOrCreate(
            ['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web'],
            ['description' => RoleEnum::ADMIN->label() . ' - Can manage users, conversations, and channels'],
        );

        // ── 2. Assign permissions ────────────────────────────────────────

        // Superadmin: Gets ALL permissions via Gate::before() in AppServiceProvider,
        // so no explicit permission assignment is needed here.
        // However, we sync all permissions for data consistency.
        $superadmin->syncPermissions(Permission::all()->pluck('name'));

        // Admin: Operational permissions only (no role/permission/workspace management)
        $admin->syncPermissions([
            UserPermission::VIEW->value,
            UserPermission::CREATE->value,
            UserPermission::UPDATE->value,
            UserPermission::DELETE->value,

            WorkspaceUserPermission::VIEW->value,
            WorkspaceUserPermission::CREATE->value,
            WorkspaceUserPermission::UPDATE->value,
            WorkspaceUserPermission::DELETE->value,

            ConversationPermission::VIEW->value,
            ConversationPermission::CREATE->value,
            ConversationPermission::UPDATE->value,
            ConversationPermission::DELETE->value,

            MessagePermission::VIEW->value,
            MessagePermission::CREATE->value,
            MessagePermission::UPDATE->value,
            MessagePermission::DELETE->value,

            ChannelPermission::VIEW->value,
            ChannelPermission::CREATE->value,
            ChannelPermission::UPDATE->value,
            ChannelPermission::DELETE->value,
        ]);
    }
}
