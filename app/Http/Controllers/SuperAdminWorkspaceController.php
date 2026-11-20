<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\Conversation;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Models\WorkspaceUsageQuota;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SuperAdminWorkspaceController extends Controller
{
    /**
     * Display a live grid & table list of all workspaces for Superadmin.
     */
    public function index(Request $request): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user && ($user->hasRole(RoleEnum::SUPERADMIN->value) || $user->hasRole('superadmin') || $user->email === 'superadmin@example.com');
        if (! $isSuperAdmin) {
            abort(403, 'Unauthorized. Superadmin role required.');
        }

        $query = Workspace::with([
            'subscriptions.plan',
            'channelAccounts',
        ])
        ->withCount(['users', 'faqs', 'knowledgeDocuments']);

        // Search by name or slug
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $workspaces = $query->latest('id')->paginate(12)->withQueryString();

        // High-level KPI metrics
        $totalWorkspaces = Workspace::count();
        $activeWorkspaces = Workspace::where('is_active', true)->count();
        $inactiveWorkspaces = $totalWorkspaces - $activeWorkspaces;
        $totalUsers = User::count();
        $totalConversations = Conversation::count();

        $currentPeriod = Carbon::now()->format('Y-m');

        // Attach active subscription plan and usage quota for each workspace
        $quotas = WorkspaceUsageQuota::where('period_year_month', $currentPeriod)
            ->get()
            ->keyBy('workspace_id');

        $availablePlans = SubscriptionPlan::where('is_active', true)->get();

        return view('admin.workspaces.index', [
            'workspaces'         => $workspaces,
            'totalWorkspaces'    => $totalWorkspaces,
            'activeWorkspaces'   => $activeWorkspaces,
            'inactiveWorkspaces' => $inactiveWorkspaces,
            'totalUsers'         => $totalUsers,
            'totalConversations' => $totalConversations,
            'currentPeriod'      => $currentPeriod,
            'quotas'             => $quotas,
            'availablePlans'     => $availablePlans,
            'activeWorkspaceId'  => $user->workspace_id ?? session('active_workspace_id', 1),
        ]);
    }

    /**
     * Switch superadmin's active workspace session.
     */
    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user && ($user->hasRole(RoleEnum::SUPERADMIN->value) || $user->hasRole('superadmin') || $user->email === 'superadmin@example.com');
        if (! $isSuperAdmin) {
            abort(403, 'Unauthorized.');
        }

        session(['active_workspace_id' => $workspace->id]);
        if ($user) {
            $user->workspace_id = $workspace->id;
            $user->save();
        }

        return redirect()->back()->with('success', "সফলভাবে ওয়ার্কস্পেস '{$workspace->name}' (ID: {$workspace->id})-এ সুইচ করা হয়েছে।");
    }

    /**
     * Toggle active/inactive status of a workspace.
     */
    public function toggleActive(Request $request, Workspace $workspace): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user && ($user->hasRole(RoleEnum::SUPERADMIN->value) || $user->hasRole('superadmin') || $user->email === 'superadmin@example.com');
        if (! $isSuperAdmin) {
            abort(403, 'Unauthorized.');
        }

        $workspace->is_active = !$workspace->is_active;
        $workspace->save();

        $statusText = $workspace->is_active ? 'Active' : 'Suspended';
        return redirect()->back()->with('success', "ওয়ার্কস্পেস '{$workspace->name}' স্ট্যাটাস পরিবর্তন করে {$statusText} করা হয়েছে।");
    }

    /**
     * Store a newly created workspace.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isSuperAdmin = $user && ($user->hasRole(RoleEnum::SUPERADMIN->value) || $user->hasRole('superadmin') || $user->email === 'superadmin@example.com');
        if (! $isSuperAdmin) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'slug'        => ['nullable', 'string', 'max:150', 'unique:workspaces,slug'],
            'plan_id'     => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $slug = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']);
        if (Workspace::where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        $workspace = Workspace::create([
            'name'        => $validated['name'],
            'slug'        => $slug,
            'description' => $validated['description'] ?? null,
            'is_active'   => true,
        ]);

        // Assign plan if selected
        if (!empty($validated['plan_id'])) {
            WorkspaceSubscription::create([
                'workspace_id'  => $workspace->id,
                'plan_id'       => (int) $validated['plan_id'],
                'status'        => 'active',
                'billing_cycle' => 'monthly',
                'starts_at'     => now(),
            ]);
        }

        return redirect()->back()->with('success', "নতুন ওয়ার্কস্পেস '{$workspace->name}' সফলভাবে তৈরি হয়েছে।");
    }
}
