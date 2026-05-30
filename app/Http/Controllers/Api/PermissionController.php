<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('name')->get(['id', 'name', 'guard_name']);

        return response()->json([
            'success' => true,
            'message' => 'Permissions retrieved successfully',
            'data'    => $permissions,
        ]);
    }
}
