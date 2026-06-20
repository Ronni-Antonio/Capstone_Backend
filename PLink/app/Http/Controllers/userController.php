<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class userController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::select(["id", "name", "email"])->get();
        return response()->json($users);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = User::create([
            "name" => $request->name,
            "email" => $request->email,
            "password" => $request->password,
        ]);

        return response()->json(["id" => $user->id, "name" => $user->name, "email" => $user->email, "password" => $user->password]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(["message" => "User not found"], 404);
        }
        
        return response()->json([
            // original keys
            "id" => $user->id,
            "name" => $user->name,
            "email" => $user->email,
            "password" => $user->password,

            // additional for the profile
            "phone" => $user->phone ?? '',
            "school" => $user->school ?? '',
            "role" => $user->role ?? 'Eco Coordinator',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(["message" => "User not found"], 404);
        }
        
        $user->name = $request->fullname ?? $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
              $user->password = $request->password;
        }

        // additional for the profile
        if ($request->has('phone'))  $user->phone = $request->phone;
        if ($request->has('school')) $user->school = $request->school;
        if ($request->has('role'))   $user->role = $request->role;
        
        $user->save();

        // for editing in the profile
        return response()->json([
            "success" => true,
            "message" => "Profile updated Successfully"
        ]);
    }

    /**
     * profile: handle the password card
     */
    public function updatePassword(Request $request, string $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(["message" => "User not found"], 404);
        }

        // validation of fields TO SEND IN THE REACT
        $request->validate([
            'current' => 'required',
            'newPass' => 'required|string|min:8',
        ]);

        // verify current pass
       if (!\Illuminate\Support\Facades\Hash::check($request->current, $user->password) && $request->current !== $user->password) {
            return response()->json([
                "success" => false,
                "message" => "Current password does not match"
            ], 400);
        }

        // FIXED: Hash the new password so it stays secure and works with your login system
        $user->password = \Illuminate\Support\Facades\Hash::make($request->newPass);
        $user->save();

        return response()->json([
            "success" => true,
            "message" => "Password Security Updated Successfully."
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        User::destroy($id);
        return response()->json(["message" => "User deleted successfully"]);
    }
}