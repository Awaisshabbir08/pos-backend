<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adds a tenant_id global scope so every query is automatically restricted
 * to the current tenant. Super-admin requests bypass the scope and see all
 * tenants' data (the controller can still narrow via ?tenant_id=).
 *
 * On create, if no tenant_id is provided we stamp the current one.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Filter every query by current tenant.
        // - Regular users: pinned to their own tenant (TenantContext::id()).
        // - Super-admin with no tenant picked: no filter (true cross-tenant view).
        // - Super-admin with a tenant picked: filter to that tenant ("view as").
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = TenantContext::id();
            if (TenantContext::isSuperAdmin() && $tenantId === null) {
                return;
            }
            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        // Auto-stamp tenant_id on insert. Super-admin without a tenant picked
        // would otherwise create orphan rows (legacy tables with nullable tenant_id)
        // or 500 with an SQL integrity error (newer tables that are NOT NULL).
        // Reject the create cleanly with a 422 instead.
        static::creating(function ($model) {
            if (!$model->tenant_id) {
                $tenantId = TenantContext::id();
                if ($tenantId === null && TenantContext::isSuperAdmin()) {
                    throw new \Illuminate\Http\Exceptions\HttpResponseException(
                        response()->json([
                            'success' => false,
                            'message' => 'Pick a tenant first. Super-admin must scope to a specific tenant before creating ' . class_basename($model) . ' records — add ?tenant_id={id} to the request URL.',
                            'data'    => null,
                        ], 422)
                    );
                }
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Build a query that ignores the tenant scope (use sparingly). */
    public static function allTenants(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }
}
