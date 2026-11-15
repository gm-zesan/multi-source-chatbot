<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceSettingController extends Controller
{
    /**
     * Show workspace settings page.
     */
    public function edit(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->canManageWorkspace($user)) {
            abort(403, 'Unauthorized. Only workspace owners and administrators can manage workspace settings.');
        }

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: Workspace::first()?->id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);

        return view('admin.workspace.settings', [
            'workspace' => $workspace,
        ]);
    }

    /**
     * Update workspace settings.
     */
    public function update(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->canManageWorkspace($user)) {
            abort(403, 'Unauthorized. Only workspace owners and administrators can manage workspace settings.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'currency' => 'required|string|max:8',
            'support_phone' => 'nullable|string|max:32',
            'support_email' => 'nullable|email|max:128',
            'business_address' => 'nullable|string|max:500',
            'operating_hours' => 'nullable|array',
            'operating_hours.weekdays' => 'nullable|string|max:64',
            'operating_hours.weekend' => 'nullable|string|max:64',
            'settings' => 'nullable|array',
            'settings.*' => 'nullable',
        ]);

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: Workspace::first()?->id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);
        $workspace->update($validated);

        return redirect()->route('workspace.settings.edit', $user->hasRole(RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id') ? ['workspace_id' => $workspaceId] : [])
            ->with('success', 'Workspace settings updated successfully.');
    }

    private function canManageWorkspace(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(RoleEnum::SUPERADMIN->value)
            || $user->hasRole(RoleEnum::ADMIN->value)
            || $user->hasRole('owner');
    }
}
