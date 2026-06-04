<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initializes the tenant context for the request lifecycle from the
 * authenticated user. Must run AFTER auth:sanctum so $request->user() exists.
 *
 * Also enforces mid-session tenant gating: if a tenant is disabled or its
 * subscription expires AFTER login, subsequent requests from its users
 * are rejected with 403.
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $isSuper = $user->hasRole('super-admin');

        if ($isSuper) {
            $viewAs = $request->query('tenant_id');
            $tenantId = ($viewAs !== null && $viewAs !== '' && $viewAs !== 'null') ? (int) $viewAs : null;

            if ($tenantId !== null && !Tenant::find($tenantId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected tenant not found.',
                    'data'    => null,
                ], 404);
            }

            TenantContext::set($tenantId, true);
        } else {
            // Regular user — pin to their tenant, but only if it's still active
            if ($user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
                if (!$tenant || !$tenant->isActive()) {
                    // Revoke this token so the user can't keep poking
                    optional($user->currentAccessToken())->delete();
                    return response()->json([
                        'success' => false,
                        'message' => $tenant?->statusReason()
                                    ?? 'Your account is associated with an inactive tenant. Please contact support.',
                        'data'    => null,
                    ], 403);
                }
            }

            TenantContext::set($user->tenant_id, false);
        }

        try {
            return $next($request);
        } finally {
            TenantContext::reset();
        }
    }
}
