<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        // We have to bypass the tenant global scope during login because the
        // tenant context isn't known until AFTER we look up the user.
        TenantContext::set(null, true);
        try {
            if (!Auth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'data'    => null,
                ], 401);
            }

            /** @var User $user */
            $user = Auth::user();

            if ($user->status === 'inactive') {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been deactivated. Please contact an administrator.',
                    'data'    => null,
                ], 403);
            }

            // Tenant gate (super-admin bypasses)
            if (!$user->hasRole('super-admin') && $user->tenant_id) {
                $tenant = \App\Models\Tenant::find($user->tenant_id);
                if (!$tenant || !$tenant->isActive()) {
                    Auth::logout();
                    return response()->json([
                        'success' => false,
                        'message' => $tenant?->statusReason() ?? 'Your subscription is inactive. Please contact support.',
                        'data'    => null,
                    ], 403);
                }
            }

            $token = $user->createToken('api-token')->plainTextToken;
            $user->load(['branch', 'tenant']);
        } finally {
            TenantContext::reset();
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'user'        => $this->formatUser($user),
                'token'       => $token,
                'roles'       => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
            'data'    => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['branch', 'tenant']);

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data'    => array_merge($this->formatUser($user), [
                'roles'       => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ]),
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'status'         => $user->status,
            'branch_id'      => $user->branch_id,
            'branch'         => $user->branch,
            'tenant_id'      => $user->tenant_id,
            'tenant'         => $user->tenant,
            'is_super_admin' => $user->hasRole('super-admin'),
        ];
    }
}
