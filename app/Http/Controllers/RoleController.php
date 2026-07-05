<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            /** @var User|null $auth_user */
            $auth_user = Auth::user();
            if ($auth_user && $auth_user->hasRole('superadmin')) {
                $roles = Role::get()->all();
            } else {
                $roles = Role::where('name','!=', 'superadmin')->get()->all();
            }
            return DataTables::of($roles)
                ->addIndexColumn()
                ->addColumn('action-btn', function ($row) {
                    /** @var User|null $auth_user */
                    $auth_user = Auth::user();
                    if ($auth_user && $auth_user->hasRole('superadmin')) {
                        return [
                            'id' => $row->id,
                            'role' => $auth_user->roles->first()->name ?? null,
                        ];
                    }

                    return ['id' => $row->id];
                })
                ->rawColumns(['action-btn'])
                ->make(true);
        }

        $permission = Permission::get();
        $modules = Permission::select('module')->distinct()->get();

        return view('admin.roles.index', compact('permission','modules'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create(): View
    {
        /** @var User|null $auth_user */
        $auth_user = Auth::user();
        if ($auth_user && $auth_user->hasRole('superadmin')) {
            $permission = Permission::get();
        } else {
            $permission = Permission::whereNotIn('name', [
                'page-list',
                'page-create',
                'page-edit',
                'page-delete',
                'page-content-create',
                'page-content-delete',
            ])->get();
        }
        $modules = Permission::select('module')->distinct()->get();

        return view('admin.roles.create', compact('permission','modules'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required|unique:roles,name',
            'permission' => 'required',
        ]);

        Role::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        $newRole = Role::findByName($request->input('name'));
        foreach ($request->input('permission') as $permissionId) {
            $permission = Permission::findById($permissionId);
            $newRole->givePermissionTo($permission->name);
        }

        return redirect()
            ->route('role.index')
            ->with('success','Role created successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return View
     */
    public function show($id): View
    {
        $role = Role::find($id);
        $rolePermissions = Permission::join('role_has_permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->where('role_has_permissions.role_id', $id)
            ->get();

        return view('admin.roles.show', compact('role','rolePermissions'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return View
     */
    public function edit($id): View
    {
        /** @var User|null $auth_user */
        $auth_user = Auth::user();
        $role = Role::find($id);
        if ($role->name === 'superadmin' && (! $auth_user || ! $auth_user->hasRole('superadmin'))) {
            return view('admin.roles.index');
        }

        if ($auth_user && $auth_user->hasRole('superadmin')) {
            $permission = Permission::get();
        } else {
            $permission = Permission::whereNotIn('name', [
                'page-list',
                'page-create',
                'page-edit',
                'page-delete',
                'page-content-create',
                'page-content-delete',
            ])->get();
        }

        $modules = Permission::select('module')->distinct()->get();
        $rolePermissions = DB::table('role_has_permissions')
            ->where('role_has_permissions.role_id', $id)
            ->pluck('role_has_permissions.permission_id','role_has_permissions.permission_id')
            ->all();

        return view('admin.roles.edit', compact('role','permission','rolePermissions','modules'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required',
            'permission' => 'required',
        ]);

        $role = Role::find($id);
        $role->name = $request->input('name');
        $role->description = $request->input('description');
        $role->save();

        DB::table('role_has_permissions')->where('role_id', $id)->delete();

        $newRole = Role::findByName($request->input('name'));
        foreach ($request->input('permission') as $permissionId) {
            $permission = Permission::findById($permissionId);
            $newRole->givePermissionTo($permission->name);
        }

        return redirect()->route('role.index')
            ->with('success','Role updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function destroy($id): RedirectResponse
    {
        $role = Role::find($id);
        /** @var User|null $auth_user */
        $auth_user = Auth::user();
        if ($role->name === 'superadmin' && (! $auth_user || ! $auth_user->hasRole('superadmin'))) {
            return redirect()->route('role.index');
        }

        $role->delete();

        return redirect()->route('role.index')
            ->with('success', 'Role deleted successfully');
    }
}
