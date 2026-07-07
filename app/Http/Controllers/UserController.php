<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Auth;
class UserController extends Controller
{   
    public function index(Request $request){
        if ($request->ajax()) {
            /** @var User|null $auth_user */
            $auth_user = Auth::user();
            if ($auth_user->hasRole('superadmin')) {
                $users = User::all();
            } else {
                $users = User::whereHas('roles', function ($query) {
                    return $query->where('name','!=', 'superadmin');
                })->where('id','!=',$auth_user->id)->get();
            }
            return DataTables::of($users)
                ->addIndexColumn()
                ->addColumn('email', function($row){
                    return $row->email;
                })
                ->addColumn('action-btn', function($row) {
                    return $row->id;
                })
                ->rawColumns(['action-btn'])
                ->make(true);
        }
        return view('admin.users.index');
    }


    public function create(){
        return view('admin.users.create');
    }


    public function store(Request $request){
        $this->validate($request, [
            'email' => 'required|max:100|unique:users',
        ], [
            'email.required' => 'your email is required',
            'email.max' =>  'your email should be less than 100 characters',
            'email.unique' =>  'your email should be unique',
        ]);
        $data = $request->all();

        if (!empty($request->password) && (isset($request->password))) {
            $this->validate($request, [
                'password' => [
                    'min:8',
                    'regex:/[a-z]/',
                    'regex:/[A-Z]/',
                    'regex:/[0-9]/',
                    'confirmed',
                ],
            ], [
                'password.min' =>  'min char 8',
                'password.regex' =>  'password must contain at least one uppercase letter, one lowercase letter, and one number',
            ]);
            $data['password'] = bcrypt($request->password);
        }

        $workspace = Workspace::create([
            'name' => $request->workspace_name,
            'description' => $request->workspace_description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::create([
            'workspace_id' => $workspace->id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => NULL,
            'avatar' => NULL,
            'email_verified_at' => now(),
            'password' => $data['password'] ?? bcrypt('password'),
            'is_active' => 1,
            'last_login_at' => NULL,
            'last_login_ip' => NULL,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // $user->assignRole($request->input('roles'));
        $user->assignRole('admin');

        return redirect()->route('users')->with('success','User created successfully');
    }




    public function edit($id){
        $auth_user = Auth::user();
        $user = User::with('contactBook')->find($id);
        if ($user->hasRole('superadmin') && $auth_user->id != $user->id) {
            return redirect()->route('users');
        }

        return view('admin.users.edit',[
            'user'=>$user,
        ]);
    }


    public function update(Request $request, $id){
        $this->validate($request, [
            'email' => 'required|max:100|unique:users,email,'.$id,
        ], [
            'email.required' => 'your email is required',
            'email.max' =>  'your email should be less than 100 characters',
            'email.unique' =>  'your email should be unique',
        ]);
        $data = $request->all();
        $old_data = User::find($id);

        if($data['cover_image_data'] != ""){
            $image = $request->file('image');
            $destinationPath = 'upload/all-user/';
            $imageValue = $destinationPath . date('YmdHis') . "." . $image->getClientOriginalExtension();
            $image->move($destinationPath, $imageValue);
            $data['image'] = $imageValue;
            if($old_data->image){
                if (file_exists(public_path($old_data->image)) && $old_data->image != null) {
                    unlink(public_path($old_data->image));
                }
            }
        }

        $old_data->contactBook->update($data);

        $user = User::find($id);
        $user->update([
            'email' => $data['email']
        ]);

        return redirect()->route('users')->with('success','User updated successfully');
    }

    public function delete($id){
        $old_data = User::find($id);
        $old_data->delete();
        return redirect()->route('users')->with('success','User deleted successfully');
    }
}