<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PasswordResetOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    /**
     * Send OTP to user's email
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Delete any existing OTP for this email
        PasswordResetOtp::where('email', $request->email)->delete();
        
        // Create new OTP record (expires in 15 minutes)
        PasswordResetOtp::create([
            'email' => $request->email,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(15)
        ]);

        // Send OTP via email (we'll use a simple text email for now)
        try {
            Mail::raw("Your OTP for password reset is: {$otp}. It will expire in 15 minutes.", function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('Password Reset OTP');
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send OTP. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent successfully to your email.'
        ], 200);
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6'
        ]);

        // Find the OTP record
        $otpRecord = PasswordResetOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'message' => 'Invalid OTP.'
            ], 400);
        }

        // Check if OTP has expired
        if ($otpRecord->expires_at < now()) {
            return response()->json([
                'message' => 'OTP has expired. Please request a new one.'
            ], 400);
        }

        // OTP is valid
        return response()->json([
            'message' => 'OTP verified successfully.',
            'verified' => true
        ], 200);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed'
        ]);

        // Verify OTP again
        $otpRecord = PasswordResetOtp::where('email', $request->email)
            ->where('otp', $request->otp)
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'message' => 'Invalid OTP.'
            ], 400);
        }

        if ($otpRecord->expires_at < now()) {
            return response()->json([
                'message' => 'OTP has expired. Please request a new one.'
            ], 400);
        }

        // Update user's password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the used OTP
        $otpRecord->delete();

        return response()->json([
            'message' => 'Password reset successfully!'
        ], 200);
    }
}
