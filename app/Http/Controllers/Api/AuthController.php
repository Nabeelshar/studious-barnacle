<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

        // Signup bonus: 100 coins granted on first registration
        $bonusCoins = 100;

        $user  = User::create([
            'name'                => $data['name'],
            'email'               => $data['email'],
            'password'            => Hash::make($data['password']),
            'coin_balance'        => $bonusCoins,
            'bonus_coins_claimed' => true,
        ]);

        \Illuminate\Support\Facades\DB::table('coin_transactions')->insert([
            'user_id'       => $user->id,
            'type'          => 'bonus',
            'amount'        => $bonusCoins,
            'balance_after' => $bonusCoins,
            'description'   => 'Welcome bonus',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'user'         => $user,
            'token'        => $token,
            'bonus_coins'  => $bonusCoins,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        $token = $user->createToken('app')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Send a 6-digit reset code to the user's email.
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            // Don't reveal whether email exists
            return response()->json(['message' => 'If that email is registered, a reset code has been sent.']);
        }

        // Generate a 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store in password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $data['email']],
            [
                'token'      => Hash::make($code),
                'created_at' => Carbon::now(),
            ]
        );

        // Send email with the code
        Mail::raw(
            "Your Novelia password reset code is: {$code}\n\nThis code expires in 60 minutes.\n\nIf you didn't request this, please ignore this email.",
            function ($message) use ($data) {
                $message->to($data['email'])
                        ->subject('Novelia — Password Reset Code');
            }
        );

        return response()->json(['message' => 'If that email is registered, a reset code has been sent.']);
    }

    /**
     * Verify the 6-digit code and set a new password.
     */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'code'     => 'required|string|size:6',
            'password' => 'required|string|min:8',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Invalid or expired reset code.'], 422);
        }

        // Check expiry (60 minutes)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            return response()->json(['message' => 'Reset code has expired. Please request a new one.'], 422);
        }

        // Verify code
        if (! Hash::check($data['code'], $record->token)) {
            return response()->json(['message' => 'Invalid reset code.'], 422);
        }

        // Update password
        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        // Clean up
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        // Revoke all existing tokens so user must re-login
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset successfully. Please sign in with your new password.']);
    }
}
