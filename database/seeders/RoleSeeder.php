<?php

namespace Database\Seeders;

use App\Enums\Permissions\ChannelPermission;
use App\Enums\Permissions\ConversationPermission;
use App\Enums\Permissions\FAQPermission;
use App\Enums\Permissions\KnowledgeSearchLogPermission;
use App\Enums\Permissions\MessagePermission;
use App\Enums\Permissions\UnansweredQuestionPermission;
use App\Enums\Permissions\UserPermission;
use App\Enums\Permissions\WorkspaceUserPermission;
use App\Enums\Permissions\KnowledgeDocumentPermission;
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

        $platformSupport = Role::updateOrCreate(
            ['name' => RoleEnum::PLATFORM_SUPPORT->value, 'guard_name' => 'web'],
            ['description' => RoleEnum::PLATFORM_SUPPORT->label() . ' - Technical platform support and health diagnostics'],
        );

        $admin = Role::updateOrCreate(
            ['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web'],
            ['description' => RoleEnum::ADMIN->label() . ' - Business Owner, manages own workspace channels, bot, staff & billing'],
        );

        $workspaceManager = Role::updateOrCreate(
            ['name' => RoleEnum::WORKSPACE_MANAGER->value, 'guard_name' => 'web'],
            ['description' => RoleEnum::WORKSPACE_MANAGER->label() . ' - Store Manager, handles conversations, orders and knowledge grounding'],
        );

        $workspaceAgent = Role::updateOrCreate(
            ['name' => RoleEnum::WORKSPACE_AGENT->value, 'guard_name' => 'web'],
            ['description' => RoleEnum::WORKSPACE_AGENT->label() . ' - Support & Sales Agent, handles customer live chat & order capture'],
        );

        // ── 2. Assign permissions ────────────────────────────────────────

        // Superadmin: Gets ALL permissions via Gate::before() in AppServiceProvider,
        // so no explicit permission assignment is needed here.
        // However, we sync all permissions for data consistency.
        $superadmin->syncPermissions(Permission::all()->pluck('name'));

        // Platform Support: Platform governance visibility & diagnostics only (no private merchant conversations)
        $platformSupport->syncPermissions([
            'workspace.view',
            'channel.view',
        ]);

        // Business Admin: Full operational control of own workspace (no platform-level governance)
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

            FAQPermission::VIEW->value,
            FAQPermission::CREATE->value,
            FAQPermission::UPDATE->value,
            FAQPermission::DELETE->value,

            KnowledgeDocumentPermission::VIEW->value,
            KnowledgeDocumentPermission::CREATE->value,
            KnowledgeDocumentPermission::UPDATE->value,
            KnowledgeDocumentPermission::DELETE->value,

            KnowledgeSearchLogPermission::VIEW->value,
            KnowledgeSearchLogPermission::DELETE->value,

            UnansweredQuestionPermission::VIEW->value,
            UnansweredQuestionPermission::UPDATE->value,
            UnansweredQuestionPermission::DELETE->value,
        ]);

        // Workspace Manager: Operational chats, orders & knowledge base (no channel delete, no billing, no staff management)
        $workspaceManager->syncPermissions([
            ConversationPermission::VIEW->value,
            ConversationPermission::CREATE->value,
            ConversationPermission::UPDATE->value,

            MessagePermission::VIEW->value,
            MessagePermission::CREATE->value,
            MessagePermission::UPDATE->value,

            FAQPermission::VIEW->value,
            FAQPermission::CREATE->value,
            FAQPermission::UPDATE->value,

            KnowledgeDocumentPermission::VIEW->value,
            KnowledgeDocumentPermission::CREATE->value,
            KnowledgeDocumentPermission::UPDATE->value,

            KnowledgeSearchLogPermission::VIEW->value,
            UnansweredQuestionPermission::VIEW->value,
            UnansweredQuestionPermission::UPDATE->value,
        ]);

        // Workspace Agent: Live chat and message formulate/reply only
        $workspaceAgent->syncPermissions([
            ConversationPermission::VIEW->value,
            ConversationPermission::CREATE->value,
            ConversationPermission::UPDATE->value,

            MessagePermission::VIEW->value,
            MessagePermission::CREATE->value,
            MessagePermission::UPDATE->value,
        ]);
    }
}
