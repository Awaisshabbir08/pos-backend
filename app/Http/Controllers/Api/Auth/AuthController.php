<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

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

        $token = $user->createToken('api-token')->plainTextToken;
        $user->load('branch');

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'user'        => [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'status'    => $user->status,
                    'branch_id' => $user->branch_id,
                    'branch'    => $user->branch,
                ],
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
        $user->load('branch');

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data'    => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'status'      => $user->status,
                'branch_id'   => $user->branch_id,
                'branch'      => $user->branch,
                'roles'       => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }
}
