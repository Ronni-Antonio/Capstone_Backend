<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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
            "id" => $user->id,
            "name" => $user->name,
            "email" => $user->email,
            "password" => $user->password,
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

        if ($request->has('phone'))  $user->phone = $request->phone;
        if ($request->has('school')) $user->school = $request->school;
        if ($request->has('role'))   $user->role = $request->role;
        
        $user->save();

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

        $request->validate([
            'current' => 'required',
            'newPass' => 'required|string|min:8',
        ]);

        if (!\Illuminate\Support\Facades\Hash::check($request->current, $user->password) && $request->current !== $user->password) {
            return response()->json([
                "success" => false,
                "message" => "Current password does not match"
            ], 400);
        }

        $user->password = \Illuminate\Support\Facades\Hash::make($request->newPass);
        $user->save();

        return response()->json([
            "success" => true,
            "message" => "Password Security Updated Successfully."
        ]);
    }

    /**
     * STAGE 1: Generate OTP Pin and Send it via Mail
     * POST api/user/{id}/request-email-change
     */
    public function requestEmailChange(Request $request, string $id)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email'
        ]);

        $user = User::find($id);
        if (!$user) {
            return response()->json(["message" => "User record fallback missed"], 404);
        }

        // Generate dynamic 6 digit code string
        $verificationCode = rand(100000, 999999);

        // Store configuration locally within cache session memory
        session([
            "email_change_id_{$id}"      => $id,
            "email_change_target_{$id}"  => $request->email,
            "email_change_code_{$id}"    => $verificationCode,
            "email_change_expiry_{$id}"  => now()->addMinutes(15)
        ]);

        // Dispatch raw text content delivery to destination inbox
        try {
            Mail::raw("Your Plink custom system validation change code pin is: {$verificationCode}", function ($message) use ($request) {
                $message->to($request->email)
                        ->subject("Plink Security: Verify Email Update");
            });
        } catch (\Exception $e) {
            // Log fallback if email config is missing locally during offline test setups
            logger("Mail dispatch failed, debug code: " . $verificationCode);
        }

        return response()->json([
            "success" => true, 
            "message" => "Verification verification pin dispatched successfully."
        ]);
    }

    /**
     * STAGE 2: Validate code matching and run target email rewrite
     * POST api/user/{id}/verify-email-change
     */
    public function verifyEmailChange(Request $request, string $id)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $cachedCode   = session("email_change_code_{$id}");
        $targetEmail  = session("email_change_target_{$id}");
        $expiryTime   = session("email_change_expiry_{$id}");

        // Evaluate entry security requirements
        if (!$cachedCode || $cachedCode != $request->code || now()->greaterThan($expiryTime)) {
            return response()->json([
                "success" => false, 
                "message" => "Incorrect or expired verification parameters validation token code."
            ], 422);
        }

        // Apply properties to DB row safely
        $user = User::find($id);
        if ($user) {
            $user->email = $targetEmail;
            $user->save();
        }

        // Clean out used properties tracking tokens
        session()->forget([
            "email_change_id_{$id}",
            "email_change_target_{$id}",
            "email_change_code_{$id}",
            "email_change_expiry_{$id}"
        ]);

        return response()->json([
            "success" => true,
            "message" => "Account baseline routing updated cleanly!"
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