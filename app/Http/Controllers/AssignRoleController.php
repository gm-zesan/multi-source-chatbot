<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class AssignRoleController extends Controller
{
    public function index(Request $request)
    {
        /** @var User|null $auth_user */
        $auth_user = Auth::user();
        if ($auth_user->hasRole(RoleEnum::SUPERADMIN->value)) {
            $users = User::get()->all();
            $roles = Role::pluck('name')->all();
        } else {
            $users = User::whereHas('roles', function ($query) {
                return $query->where('name', '!=', RoleEnum::SUPERADMIN->value);
            })->with('roles')->get();
            $roles = Role::where('name', '!=', RoleEnum::SUPERADMIN->value)->pluck('name')->all();
        }
        if ($request->ajax()) {
            return DataTables::of($users)
                ->addIndexColumn()
                ->addColumn('role', function($row) {
                    if($row->roles->first() == null){
                        return '- - -';
                    }
                    return $row->roles->first()->name;
                })
                ->addColumn('action-btn', function($row) {
                    $user_info_ara = [
                        'id' => $row->id,
                        'name' => $row->name,
                        'email' => $row->email,
                        'role' => $row->roles->first()->name ?? null,
                    ];
                    return $user_info_ara;
                })
                ->rawColumns(['action-btn'])
                ->make(true);
        }
        return view('admin.assign-role.index',['roles'=>$roles]);
    }

    public function store(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if(!$user){
            return back()->with('error', 'User Email not found');
        }

        // syncRoles replaces all existing roles with the new one
        // This is the recommended Spatie approach instead of detach()+assignRole()
        $user->syncRoles($request->role);

        return back()->with('success', 'Role assigned successfully');
    }

}
