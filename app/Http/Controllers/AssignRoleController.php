<?php

namespace App\Http\Controllers;

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
        if ($auth_user->hasRole('superadmin')) {
            $users = User::get()->all();
            $roles = Role::pluck('name')->all();
        } else {
            $users = User::whereHas('roles', function ($query) {
                return $query->where('name','!=', 'superadmin');
            })->with('roles')->get();
            $roles = Role::where('name','!=', 'superadmin')->pluck('name')->all();
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
        $user->roles()->detach();
        $user->assignRole($request->role);
        return back()->with('success', 'Role assigned successfully');
    }

}
