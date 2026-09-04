<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
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
            if ($auth_user && $auth_user->hasRole(RoleEnum::SUPERADMIN->value)) {
                $roles = Role::get()->all();
            } else {
                $roles = Role::where('name', '!=', RoleEnum::SUPERADMIN->value)->get()->all();
            }
            return DataTables::of($roles)
                ->addIndexColumn()
                ->addColumn('action-btn', function ($row) {
                    /** @var User|null $auth_user */
                    $auth_user = Auth::user();
                    if ($auth_user && $auth_user->hasRole(RoleEnum::SUPERADMIN->value)) {
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
        $permission = Permission::get();
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

        $role = Role::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
        ]);

        // Sync permissions using Spatie's built-in method
        $permissionNames = Permission::whereIn('id', $request->input('permission'))->pluck('name')->all();
        $role->syncPermissions($permissionNames);

        return redirect()
            ->route('roles.index')
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
        $rolePermissions = $role->permissions;

        return view('admin.roles.show', compact('role','rolePermissions'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return View
     */
    public function edit($id): View|RedirectResponse
    {
        /** @var \App\Models\User|null $auth_user */
        $auth_user = Auth::user();
        $role = Role::find($id);
        if ($role->name === RoleEnum::SUPERADMIN->value && (! $auth_user || ! $auth_user->hasRole(RoleEnum::SUPERADMIN->value))) {
            return redirect()->route('roles.index');
        }

        $permission = Permission::get();
        $modules = Permission::select('module')->distinct()->get();
        $rolePermissions = $role->permissions->pluck('id')->all();

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

        // Sync permissions using Spatie's built-in method
        $permissionNames = Permission::whereIn('id', $request->input('permission'))->pluck('name')->all();
        $role->syncPermissions($permissionNames);

        return redirect()->route('roles.index')
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
        if ($role->name === RoleEnum::SUPERADMIN->value && (! $auth_user || ! $auth_user->hasRole(RoleEnum::SUPERADMIN->value))) {
            return redirect()->route('roles.index');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Role deleted successfully');
    }
}
