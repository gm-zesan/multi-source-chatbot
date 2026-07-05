<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // role
            ['name' => 'role-list', 'display_name' => 'Role list', 'module' => 'role'],
            ['name' => 'role-create', 'display_name' => 'Role create', 'module' => 'role'],
            ['name' => 'role-edit', 'display_name' => 'Role edit', 'module' => 'role'],
            ['name' => 'role-delete', 'display_name' => 'Role delete', 'module' => 'role'],

            // user
            ['name' => 'user-list', 'display_name' => 'User list', 'module' => 'user'],
            ['name' => 'user-create', 'display_name' => 'User create', 'module' => 'user'],
            ['name' => 'user-edit', 'display_name' => 'User edit', 'module' => 'user'],
            ['name' => 'user-delete', 'display_name' => 'User delete', 'module' => 'user'],

            // permission
            ['name' => 'permission-list', 'display_name' => 'Permission list', 'module' => 'permission'],
            ['name' => 'permission-create', 'display_name' => 'Permission create', 'module' => 'permission'],
            ['name' => 'permission-edit', 'display_name' => 'Permission edit', 'module' => 'permission'],
            ['name' => 'permission-delete', 'display_name' => 'Permission delete', 'module' => 'permission'],

            // workspace
            ['name' => 'workspace-list', 'display_name' => 'Workspace list', 'module' => 'workspace'],
            ['name' => 'workspace-create', 'display_name' => 'Workspace create', 'module' => 'workspace'],
            ['name' => 'workspace-edit', 'display_name' => 'Workspace edit', 'module' => 'workspace'],
            ['name' => 'workspace-delete', 'display_name' => 'Workspace delete', 'module' => 'workspace'],

            // workspace user
            ['name' => 'workspace-user-list', 'display_name' => 'Workspace user list', 'module' => 'workspace-user'],
            ['name' => 'workspace-user-create', 'display_name' => 'Workspace user create', 'module' => 'workspace-user'],
            ['name' => 'workspace-user-edit', 'display_name' => 'Workspace user edit', 'module' => 'workspace-user'],
            ['name' => 'workspace-user-delete', 'display_name' => 'Workspace user delete', 'module' => 'workspace-user'],

            // conversation
            ['name' => 'conversation-list', 'display_name' => 'Conversation list', 'module' => 'conversation'],
            ['name' => 'conversation-create', 'display_name' => 'Conversation create', 'module' => 'conversation'],
            ['name' => 'conversation-edit', 'display_name' => 'Conversation edit', 'module' => 'conversation'],
            ['name' => 'conversation-delete', 'display_name' => 'Conversation delete', 'module' => 'conversation'],

            // message
            ['name' => 'message-list', 'display_name' => 'Message list', 'module' => 'message'],
            ['name' => 'message-create', 'display_name' => 'Message create', 'module' => 'message'],
            ['name' => 'message-edit', 'display_name' => 'Message edit', 'module' => 'message'],
            ['name' => 'message-delete', 'display_name' => 'Message delete', 'module' => 'message'],

            // channel
            ['name' => 'channel-list', 'display_name' => 'Channel list', 'module' => 'channel'],
            ['name' => 'channel-create', 'display_name' => 'Channel create', 'module' => 'channel'],
            ['name' => 'channel-edit', 'display_name' => 'Channel edit', 'module' => 'channel'],
            ['name' => 'channel-delete', 'display_name' => 'Channel delete', 'module' => 'channel'],

        ];
        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
