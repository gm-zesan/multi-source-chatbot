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
        if ($request->ajax()) {
            /** @var User|null $authUser */
            $authUser = Auth::user();
            if ($authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value)) {
                $users = User::all();
            } else {
                $workspaceId = $authUser?->workspace_id;
                $users = User::where('workspace_id', $workspaceId)
                    ->whereHas('roles', function ($query) {
                        return $query->where('name', '!=', RoleEnum::SUPERADMIN->value);
                    })
                    ->where('id', '!=', $authUser?->id)
                    ->get();
            }

            return DataTables::of($users)
                ->addIndexColumn()
                ->addColumn('email', function ($row) {
                    return $row->email;
                })
                ->addColumn('action-btn', function ($row) {
                    return $row->id;
                })
                ->rawColumns(['action-btn'])
                ->make(true);
        }

        return view('admin.users.index');
    }

    public function create()
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);

        $rolesQuery = Role::query();
        if (! $isSuperAdmin) {
            $rolesQuery->where('name', '!=', RoleEnum::SUPERADMIN->value);
        }
        $roles = $rolesQuery->get();

        return view('admin.users.create', [
            'roles' => $roles,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    public function store(Request $request)
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);

        $this->validate($request, [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:100|unique:users,email',
            'password' => 'nullable|string|min:8',
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

        $password = ! empty($request->password) ? bcrypt($request->password) : bcrypt('password');

        $user = User::create([
            'workspace_id'      => $workspaceId,
            'name'              => $request->name,
            'email'             => $request->email,
            'phone'             => $request->phone ?? null,
            'avatar'            => null,
            'email_verified_at' => now(),
            'password'          => $password,
            'is_active'         => true,
        ]);

        $roleInput = $request->input('role', RoleEnum::ADMIN->value);
        if (! $isSuperAdmin && $roleInput === RoleEnum::SUPERADMIN->value) {
            $roleInput = RoleEnum::ADMIN->value;
        }

        if (! empty($roleInput)) {
            $user->assignRole($roleInput);
        }

        return redirect()->route('users.index')->with('success', 'User created successfully');
    }

    public function edit(string|int $id)
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $user = User::findOrFail($id);
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);

        if (! $isSuperAdmin && $user->workspace_id !== $authUser?->workspace_id) {
            abort(403, 'Unauthorized access to user outside your workspace.');
        }

        if ($user->hasRole(RoleEnum::SUPERADMIN->value) && ! $isSuperAdmin && $authUser?->id !== $user->id) {
            return redirect()->route('users.index')->with('error', 'Unauthorized.');
        }

        return view('admin.users.edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request, string|int $id)
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $user = User::findOrFail($id);
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);

        if (! $isSuperAdmin && $user->workspace_id !== $authUser?->workspace_id) {
            abort(403, 'Unauthorized access to user outside your workspace.');
        }

        $this->validate($request, [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:100|unique:users,email,' . $id,
        ]);

        $updateData = [
            'name'  => $request->name,
            'email' => $request->email,
        ];

        if (! empty($request->password)) {
            $updateData['password'] = bcrypt($request->password);
        }

        $user->update($updateData);

        return redirect()->route('users.index')->with('success', 'User updated successfully');
    }

    public function destroy(string|int $id)
    {
        /** @var User|null $authUser */
        $authUser = Auth::user();
        $user = User::findOrFail($id);
        $isSuperAdmin = $authUser && $authUser->hasRole(RoleEnum::SUPERADMIN->value);

        if (! $isSuperAdmin && $user->workspace_id !== $authUser?->workspace_id) {
            abort(403, 'Unauthorized access to user outside your workspace.');
        }

        if ($user->id === $authUser?->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully');
    }

    /**
     * Backward-compatible alias for destroy.
     */
    public function delete(string|int $id)
    {
        return $this->destroy($id);
    }
}
