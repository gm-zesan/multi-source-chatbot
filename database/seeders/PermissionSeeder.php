<?php

namespace Database\Seeders;

use App\Enums\Permissions\ChannelPermission;
use App\Enums\Permissions\ConversationPermission;
use App\Enums\Permissions\MessagePermission;
use App\Enums\Permissions\PermissionPermission;
use App\Enums\Permissions\RolePermission;
use App\Enums\Permissions\UserPermission;
use App\Enums\Permissions\WorkspacePermission;
use App\Enums\Permissions\WorkspaceUserPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * All registered permission enum classes.
     * When a new module is added, register its permission enum here.
     */
    private const PERMISSION_ENUMS = [
        RolePermission::class,
        UserPermission::class,
        PermissionPermission::class,
        WorkspacePermission::class,
        WorkspaceUserPermission::class,
        ConversationPermission::class,
        MessagePermission::class,
        ChannelPermission::class,
    ];

    /**
     * Run the database seeds.
     *
     * Seeds permissions from all registered module-based permission enums.
     * To add new permissions:
     *   1. Create a new Permission enum in App\Enums\Permissions\
     *   2. Add it to PERMISSION_ENUMS above
     *   3. Run: php artisan db:seed --class=PermissionSeeder
     */
    public function run(): void
    {
        foreach (self::PERMISSION_ENUMS as $enumClass) {
            foreach ($enumClass::seederData() as $permission) {
                Permission::create($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
