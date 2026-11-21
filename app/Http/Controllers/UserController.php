<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;

class UserController extends Controller
{
    public function index(Request $request)
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);
        $isBusinessAdmin = $authUser && $authUser->hasRole(RoleEnum::ADMIN->value);

        if ($request->ajax()) {
            if ($isSuperAdmin) {
                $users = User::with(['roles', 'workspace'])->get();
            } else {
                $workspaceId = $authUser?->workspace_id;
                $users = User::with(['roles', 'workspace'])
                    ->where('workspace_id', $workspaceId)
                    ->whereHas('roles', function ($query) {
                        return $query->where('name', '!=', RoleEnum::SUPERADMIN->value);
                    })
                    ->get();
            }

            return DataTables::of($users)
                ->addIndexColumn()
                ->addColumn('workspace', function ($row) {
                    return htmlspecialchars($row->workspace?->name ?? 'N/A');
                })
                ->addColumn('role', function ($row) {
                    $role = $row->roles->first();
                    if (! $role) {
                        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-mono font-medium bg-slate-500/10 text-slate-500">No Role</span>';
                    }

                    $badgeClass = match($role->name) {
                        RoleEnum::SUPERADMIN->value        => 'bg-rose-500/15 text-rose-500 border border-rose-500/30',
                        RoleEnum::PLATFORM_SUPPORT->value  => 'bg-amber-500/15 text-amber-500 border border-amber-500/30',
                        RoleEnum::ADMIN->value             => 'bg-indigo-500/15 text-indigo-500 border border-indigo-500/30',
                        RoleEnum::WORKSPACE_MANAGER->value => 'bg-teal-500/15 text-teal-500 border border-teal-500/30',
                        RoleEnum::WORKSPACE_AGENT->value   => 'bg-emerald-500/15 text-emerald-500 border border-emerald-500/30',
                        default                            => 'bg-brand-500/15 text-brand-500 border border-brand-500/30',
                    };

                    $label = match($role->name) {
                        RoleEnum::SUPERADMIN->value        => 'Super Admin',
                        RoleEnum::PLATFORM_SUPPORT->value  => 'Platform Support',
                        RoleEnum::ADMIN->value             => 'Business Owner (Admin)',
                        RoleEnum::WORKSPACE_MANAGER->value => 'Store Manager',
                        RoleEnum::WORKSPACE_AGENT->value   => 'Support Agent',
                        default                            => ucfirst($role->name),
                    };

                    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold ' . $badgeClass . '">' . htmlspecialchars($label) . '</span>';
                })
                ->addColumn('action-btn', function ($row) use ($authUser, $isSuperAdmin) {
                    $roleName = $row->roles->first()?->name ?? '';
                    $isSelf = $authUser && $authUser->id === $row->id;
                    $canManage = $isSuperAdmin || ($row->workspace_id === $authUser?->workspace_id && ! $isSelf && $roleName !== RoleEnum::ADMIN->value && $roleName !== RoleEnum::SUPERADMIN->value);

                    return [
                        'id'         => $row->id,
                        'name'       => $row->name,
                        'email'      => $row->email,
                        'role'       => $roleName,
                        'is_self'    => $isSelf,
                        'can_manage' => $canManage,
                    ];
                })
                ->rawColumns(['role', 'action-btn'])
                ->make(true);
        }

        if ($isSuperAdmin) {
            $roles = Role::pluck('name')->all();
        } elseif ($isBusinessAdmin) {
            $roles = array_keys(RoleEnum::assignableByBusinessAdmin());
        } else {
            $roles = [];
        }

        return view('admin.users.index', compact('roles', 'isSuperAdmin', 'isBusinessAdmin'));
    }

    /**
     * Assign or update role for user.
     */
    public function assignRole(Request $request, User $user)
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);
        $isBusinessAdmin = $authUser && $authUser->hasRole(RoleEnum::ADMIN->value);

        if (! $isSuperAdmin && ! $isBusinessAdmin) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        if (! $isSuperAdmin) {
            if ($user->workspace_id !== $authUser?->workspace_id) {
                abort(403, 'Cannot modify users outside your workspace.');
            }
            if ($user->id === $authUser?->id) {
                abort(403, 'You cannot change your own role.');
            }
            if ($user->hasRole(RoleEnum::ADMIN->value) || $user->hasRole(RoleEnum::SUPERADMIN->value)) {
                abort(403, 'Cannot change business owner or superadmin role.');
            }

            $allowedRoles = array_keys(RoleEnum::assignableByBusinessAdmin());
            if (! in_array($request->role, $allowedRoles, true)) {
                abort(403, 'Unauthorized role assignment. Business owners can only assign Store Manager or Support Agent.');
            }
        }

        $user->syncRoles([$request->role]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Role updated successfully to '{$request->role}' for {$user->name}.",
            ]);
        }

        return back()->with('success', "Role updated successfully to '{$request->role}' for {$user->name}.");
    }

    public function create()
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);
        $isBusinessAdmin = $authUser && $authUser->hasRole(RoleEnum::ADMIN->value);

        if (! $isSuperAdmin && ! $isBusinessAdmin) {
            abort(403, 'Unauthorized. Only workspace owners and superadmins can add team members.');
        }

        if ($isSuperAdmin) {
            $roles = Role::whereNotIn('name', [RoleEnum::SUPERADMIN->value])->get();
        } else {
            $roles = Role::whereIn('name', array_keys(RoleEnum::assignableByBusinessAdmin()))->get();
        }

        return view('admin.users.create', [
            'roles'           => $roles,
            'isSuperAdmin'    => $isSuperAdmin,
            'isBusinessAdmin' => $isBusinessAdmin,
        ]);
    }

    public function store(Request $request)
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);
        $isBusinessAdmin = $authUser && $authUser->hasRole(RoleEnum::ADMIN->value);

        if (! $isSuperAdmin && ! $isBusinessAdmin) {
            abort(403, 'Unauthorized. Only workspace owners and superadmins can add team members.');
        }

        $allowedRoles = $isSuperAdmin
            ? Role::where('name', '!=', RoleEnum::SUPERADMIN->value)->pluck('name')->all()
            : array_keys(RoleEnum::assignableByBusinessAdmin());

        $this->validate($request, [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'required|string|in:' . implode(',', $allowedRoles),
            'phone'    => 'nullable|string|max:20',
        ]);

        if ($isSuperAdmin && $request->filled('workspace_name')) {
            $workspace = Workspace::create([
                'name'      => $request->workspace_name,
                'slug'      => Str::slug((string) $request->workspace_name) . '-' . Str::random(4),
                'is_active' => true,
            ]);
            $workspaceId = $workspace->id;
        } else {
            $workspaceId = $authUser?->workspace_id;
        }

        $user = User::create([
            'workspace_id'      => $workspaceId,
            'name'              => $request->name,
            'email'             => $request->email,
            'phone'             => $request->phone ?? null,
            'avatar'            => null,
            'email_verified_at' => now(),
            'password'          => bcrypt($request->password),
            'is_active'         => true,
        ]);

        $user->assignRole($request->role);

        $roleLabel = match($request->role) {
            RoleEnum::WORKSPACE_MANAGER->value => 'Store Manager',
            RoleEnum::WORKSPACE_AGENT->value   => 'Support Agent',
            default                            => $request->role,
        };

        return redirect()->route('users.index')->with('success', "Team member '{$user->name}' added as {$roleLabel} successfully.");
    }

    public function edit(string|int $id)
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $user = User::findOrFail($id);
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);
        $isBusinessAdmin = $authUser && $authUser->hasRole(RoleEnum::ADMIN->value);

        if (! $isSuperAdmin) {
            if ($user->workspace_id !== $authUser?->workspace_id) {
                abort(403, 'Unauthorized access to user outside your workspace.');
            }
            if ($user->hasRole(RoleEnum::ADMIN->value) && $user->id !== $authUser?->id) {
                abort(403, 'Unauthorized. Cannot modify other workspace owners.');
            }
            if ($user->hasRole(RoleEnum::SUPERADMIN->value)) {
                abort(403, 'Unauthorized.');
            }
        }

        if ($isSuperAdmin) {
            $roles = Role::whereNotIn('name', [RoleEnum::SUPERADMIN->value])->get();
        } else {
            $roles = Role::whereIn('name', array_keys(RoleEnum::assignableByBusinessAdmin()))->get();
        }

        return view('admin.users.edit', [
            'user'            => $user,
            'roles'           => $roles,
            'isSuperAdmin'    => $isSuperAdmin,
            'isBusinessAdmin' => $isBusinessAdmin,
        ]);
    }

    public function update(Request $request, string|int $id)
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $user = User::findOrFail($id);
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);
        $isBusinessAdmin = $authUser && $authUser->hasRole(RoleEnum::ADMIN->value);

        if (! $isSuperAdmin) {
            if ($user->workspace_id !== $authUser?->workspace_id) {
                abort(403, 'Unauthorized access to user outside your workspace.');
            }
            if ($user->hasRole(RoleEnum::ADMIN->value) && $user->id !== $authUser?->id) {
                abort(403, 'Unauthorized. Cannot modify other workspace owners.');
            }
            if ($user->hasRole(RoleEnum::SUPERADMIN->value)) {
                abort(403, 'Unauthorized.');
            }
        }

        $allowedRoles = $isSuperAdmin
            ? Role::where('name', '!=', RoleEnum::SUPERADMIN->value)->pluck('name')->all()
            : array_keys(RoleEnum::assignableByBusinessAdmin());

        $rules = [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:100|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        if ($user->id !== $authUser?->id && ! $user->hasRole(RoleEnum::ADMIN->value)) {
            $rules['role'] = 'required|string|in:' . implode(',', $allowedRoles);
        }

        $this->validate($request, $rules);

        $updateData = [
            'name'  => $request->name,
            'email' => $request->email,
            'phone' => $request->phone ?? $user->phone,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = bcrypt($request->password);
        }

        $user->update($updateData);

        if ($user->id !== $authUser?->id && $request->filled('role')) {
            $user->syncRoles([$request->role]);
        }

        return redirect()->route('users.index')->with('success', "Team member '{$user->name}' updated successfully.");
    }

    public function destroy(string|int $id)
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $user = User::findOrFail($id);
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);

        if (! $isSuperAdmin) {
            if ($user->workspace_id !== $authUser?->workspace_id) {
                abort(403, 'Unauthorized access to user outside your workspace.');
            }
            if ($user->hasRole(RoleEnum::ADMIN->value)) {
                abort(403, 'Cannot delete the Business Owner account.');
            }
            if ($user->hasRole(RoleEnum::SUPERADMIN->value)) {
                abort(403, 'Unauthorized.');
            }
        }

        if ($user->id === $authUser?->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User removed successfully.');
    }

    /**
     * Backward-compatible alias for destroy.
     */
    public function delete(string|int $id)
    {
        return $this->destroy($id);
    }
}
