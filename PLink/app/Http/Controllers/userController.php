<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ActivityLog;
use App\Models\EmailChangeOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:255',
            'school' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            "name" => $validated['name'],
            "email" => $validated['email'],
            "password" => Hash::make($validated['password']),
            "phone" => $validated['phone'] ?? null,
            "school" => $validated['school'] ?? null,
            "role" => $validated['role'] ?? null,
        ]);

        ActivityLog::record(
            'CREATE_USER',
            "Admin user account created: {$user->name} ({$user->email}).",
            'Users',
            auth()->id()
        );

        return response()->json(["id" => $user->id, "name" => $user->name, "email" => $user->email], 201);
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

        $oldName = $user->name;
        $oldEmail = $user->email;
        $oldPhone = $user->phone;
        $oldSchool = $user->school;
        $oldRole = $user->role;
        
        $user->name = $request->fullname ?? $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
             $user->password = Hash::make($request->password);
        }

        if ($request->has('phone'))  $user->phone = $request->phone;
        if ($request->has('school')) $user->school = $request->school;
        if ($request->has('role'))   $user->role = $request->role;
        
        $user->save();

        $changes = [];
        if ($oldName !== $user->name) $changes[] = "name from '{$oldName}' to '{$user->name}'";
        if ($oldEmail !== $user->email) $changes[] = "email from '{$oldEmail}' to '{$user->email}'";
        if ($oldPhone !== $user->phone) $changes[] = "phone from '{$oldPhone}' to '{$user->phone}'";
        if ($oldSchool !== $user->school) $changes[] = "school from '{$oldSchool}' to '{$user->school}'";
        if ($oldRole !== $user->role) $changes[] = "role from '{$oldRole}' to '{$user->role}'";

        if (!empty($changes)) {
            ActivityLog::record(
                'UPDATE_USER',
                "Admin user account updated ({$user->name}): " . implode(', ', $changes) . ".",
                'Users',
                auth()->id() ?? $user->id,
                null,
                ['changes' => $changes]
            );
        }

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
            'newPass' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current, $user->password)) {
            return response()->json([
                "success" => false,
                "message" => "Current password does not match"
            ], 400);
        }

        $user->password = Hash::make($request->newPass);
        $user->save();

        ActivityLog::record(
            'PASSWORD_UPDATED',
            "Admin user account password updated: {$user->name} ({$user->email}).",
            'Users',
            auth()->id() ?? $user->id
        );

        return response()->json([
            "success" => true,
            "message" => "Password Security Updated Successfully."
        ]);
    }

    /**
     * STAGE 1: Generate OTP Pin and send it via email
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

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Delete any existing OTP for this user
        EmailChangeOtp::where('user_id', $id)->delete();

        // Create new OTP record (expires in 15 minutes)
        EmailChangeOtp::create([
            'user_id' => $id,
            'new_email' => $request->email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(15)
        ]);

        // Send OTP via email
        try {
            Mail::raw("Your OTP for email change is: {$otp}. It will expire in 15 minutes.", function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('Email Change OTP');
            });
        } catch (\Exception $e) {
            Log::error('Failed to send OTP: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }

        Log::info("--- SECURITY OTP DISPATCH ---");
        Log::info("User ID: {$id} requested email shift to: {$request->email}");
        Log::info("Verification Pin: {$otp}");
        Log::info("--------------------------------");

        return response()->json([
            "success" => true, 
            "message" => "OTP sent successfully to your new email.",
            "debug_otp" => $otp
        ]);
    }

    /**
     * STAGE 2: Validate code matching and update email
     * POST api/user/{id}/verify-email-change
     */
    public function verifyEmailChange(Request $request, string $id)
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'newPassword' => [
                'required',
                'string',
                Password::min(12)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        // Find the OTP record
        $otpRecord = EmailChangeOtp::where('user_id', $id)
            ->where('otp', $request->code)
            ->first();

        if (!$otpRecord) {
            return response()->json([
                "success" => false, 
                "message" => "Invalid or expired verification code."
            ], 422);
        }

        // Check if OTP has expired
        if ($otpRecord->expires_at < now()) {
            return response()->json([
                "success" => false, 
                "message" => "OTP has expired. Please request a new one."
            ], 422);
        }

        // Update user's email and password
        $user = User::find($id);
        if (!$user) {
            return response()->json(["message" => "User not found"], 404);
        }

        $user->email = $otpRecord->new_email;
        $user->password = Hash::make($request->newPassword);
        $user->save();

        // Delete used OTP
        $otpRecord->delete();

        return response()->json([
            "success" => true,
            "message" => "Email updated successfully! Redirecting to login..."
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::find($id);
        if ($user) {
            $name = $user->name;
            $email = $user->email;
            User::destroy($id);
            ActivityLog::record(
                'DELETE_USER',
                "Admin user account deleted: {$name} ({$email}).",
                'Users',
                auth()->id()
            );
        } else {
            User::destroy($id);
        }
        return response()->json(["message" => "User deleted successfully"]);
    }
}