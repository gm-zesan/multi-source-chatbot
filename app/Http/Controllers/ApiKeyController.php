<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    /**
     * Display the API Key management page for the current workspace.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);

        $apiKeys = ApiKey::where('workspace_id', $workspace->id)
            ->latest('id')
            ->get();

        return view('admin.developer.api-keys', [
            'workspace'       => $workspace,
            'apiKeys'         => $apiKeys,
            'newPlainToken'   => session('new_plain_token'),
            'newApiKeyName'   => session('new_api_key_name'),
            'chatEndpoint'    => url('/api/v1/chat'),
            'docsEndpoint'    => url('/api/v1/knowledge/faqs'),
        ]);
    }

    /**
     * Generate a new API Key for the active workspace.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
        ]);

        $workspaceId = ($user->hasRole(RoleEnum::SUPERADMIN->value) && !empty($validated['workspace_id']))
            ? (int) $validated['workspace_id']
            : (int) ($user->workspace_id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);

        $result = ApiKey::generate(
            workspace: $workspace,
            user: $user,
            name: $validated['name'],
        );

        return redirect()->route('api-keys.index', $user->hasRole(RoleEnum::SUPERADMIN->value) ? ['workspace_id' => $workspace->id] : [])
            ->with('success', "API Key '{$validated['name']}' successfully generated.")
            ->with('new_plain_token', $result['plain_token'])
            ->with('new_api_key_name', $validated['name']);
    }

    /**
     * Revoke / Delete an API key.
     */
    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Enforce workspace tenant check
        if (! $user->hasRole(RoleEnum::SUPERADMIN->value) && $apiKey->workspace_id !== $user->workspace_id) {
            abort(403, 'Unauthorized to revoke API key from another workspace.');
        }

        $apiKey->delete();

        return redirect()->back()->with('success', "API Key '{$apiKey->name}' has been permanently revoked.");
    }
}
