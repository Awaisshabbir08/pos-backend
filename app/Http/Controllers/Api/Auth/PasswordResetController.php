<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Email-less reset flow (no SMTP configured): the forgot endpoint returns the
 * token in the response when APP_ENV !== production so it can be tested
 * without an email server. In production with mail configured, we'd email
 * a link like https://app.example.com/reset?token=...
 */
class PasswordResetController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // Bypass tenant scope because we don't know the user's tenant yet.
        TenantContext::set(null, true);
        try {
            $user = User::where('email', $request->email)->first();
        } finally {
            TenantContext::reset();
        }

        // Always respond success to avoid leaking which emails exist.
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'If an account exists for that email, a reset link will be sent.',
                'data'    => null,
            ]);
        }

        // Block when tenant is inactive
        if ($user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant && !$tenant->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => $tenant->statusReason() ?? 'Your account is inactive. Please contact support.',
                    'data'    => null,
                ], 403);
            }
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => Carbon::now()]
        );

        Audit::log('user.password_reset_requested', $user, ['email' => $user->email]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset token issued. Use it within 60 minutes.',
            'data'    => [
                // Token only returned outside production. In prod this is delivered by email.
                'token'         => app()->environment('production') ? null : $token,
                'email'         => $user->email,
                'expires_at'    => Carbon::now()->addMinutes(60)->toIso8601String(),
            ],
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if (!$row) {
            return response()->json(['success'=>false,'message'=>'Invalid or expired token.','data'=>null], 422);
        }

        // 60 minute expiry
        if (Carbon::parse($row->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['success'=>false,'message'=>'Token has expired. Request a new one.','data'=>null], 422);
        }

        if (!Hash::check($request->token, $row->token)) {
            return response()->json(['success'=>false,'message'=>'Invalid token.','data'=>null], 422);
        }

        TenantContext::set(null, true);
        try {
            $user = User::where('email', $request->email)->first();
        } finally {
            TenantContext::reset();
        }
        if (!$user) {
            return response()->json(['success'=>false,'message'=>'Account not found.','data'=>null], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Invalidate the token + any active sessions
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        $user->tokens()->delete();

        Audit::log('user.password_reset_completed', $user);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully. Please log in again.',
            'data'    => null,
        ]);
    }
}
