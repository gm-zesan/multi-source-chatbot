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
     * Show workspace settings directory (for superadmin) or workspace settings edit form (for owner/admin).
     */
    public function edit(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->canManageWorkspace($user)) {
            abort(403, 'Unauthorized. Only workspace owners and administrators can manage workspace settings.');
        }

        $isSuperAdmin = $user->hasRole(RoleEnum::SUPERADMIN->value);

        // If Superadmin and no specific workspace_id is selected, redirect to the single canonical Workspaces directory
        if ($isSuperAdmin && ! $request->filled('workspace_id')) {
            return redirect()->route('workspaces.index');
        }

        // Specific workspace edit page (for Superadmin with workspace_id or Tenant Owner)
        $workspaceId = ($isSuperAdmin && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: Workspace::first()?->id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);

        return view('admin.workspace.settings', [
            'workspace'    => $workspace,
            'isSuperAdmin' => $isSuperAdmin,
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
            'workspace_id'              => 'nullable|integer|exists:workspaces,id',
            'name'                      => 'required|string|max:128',
            'currency'                  => 'required|string|max:8',
            'support_phone'             => 'nullable|string|max:32',
            'support_email'             => 'nullable|email|max:128',
            'business_address'          => 'nullable|string|max:500',
            'operating_hours'           => 'nullable|array',
            'operating_hours.weekdays'  => 'nullable|string|max:64',
            'operating_hours.weekend'   => 'nullable|string|max:64',
            'settings'                  => 'nullable|array',
            'settings.*'                => 'nullable',
        ]);

        $isSuperAdmin = $user->hasRole(RoleEnum::SUPERADMIN->value);
        $workspaceId = ($isSuperAdmin && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: Workspace::first()?->id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);
        
        $existingSettings = $workspace->settings ?? [];
        if (isset($validated['settings'])) {
            if (isset($validated['settings']['fallback_message']) && !isset($validated['settings']['unanswerable_message'])) {
                $validated['settings']['unanswerable_message'] = $validated['settings']['fallback_message'];
            }

            if (isset($validated['settings']['custom_synonyms_text'])) {
                $synonymsText = (string) $validated['settings']['custom_synonyms_text'];
                $parsed = [];
                foreach (explode("\n", $synonymsText) as $line) {
                    $line = trim($line);
                    if (str_contains($line, ':')) {
                        [$key, $values] = explode(':', $line, 2);
                        $key = mb_strtolower(trim($key));
                        if ($key !== '') {
                            $parsed[$key] = array_values(array_unique(array_filter(array_map('trim', explode(',', $values)))));
                        }
                    }
                }
                $validated['settings']['custom_synonyms'] = $parsed;
            }

            $validated['settings'] = array_merge($existingSettings, $validated['settings']);

            \Illuminate\Support\Facades\Cache::forget("ws_{$workspaceId}_custom_synonyms");
            \Illuminate\Support\Facades\Cache::forget("ws_{$workspaceId}_ood_msg");
            \Illuminate\Support\Facades\Cache::forget("ws_{$workspaceId}_model");
        }

        $workspace->update($validated);

        $redirectParams = $isSuperAdmin ? ['workspace_id' => $workspaceId] : [];

        return redirect()->route('workspace.settings.edit', $redirectParams)
            ->with('success', "Settings for business '{$workspace->name}' updated successfully.");
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
