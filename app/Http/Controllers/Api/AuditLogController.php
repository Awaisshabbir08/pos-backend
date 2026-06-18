<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Super-admin sees cross-tenant when no tenant picked; tenant admin sees only own.
        $query = AuditLog::with(['user', 'tenant']);
        if (!TenantContext::isSuperAdmin()) {
            $query->where('tenant_id', $request->user()->tenant_id);
        } elseif ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('action')) $query->where('action', $request->action);
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);

        $perPage = $request->get('per_page', 25);

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved',
            'data'    => $query->orderByDesc('created_at')->paginate($perPage),
        ]);
    }
}
