<?php

namespace App\Support;

/**
 * Holds the "current tenant id" used by the BelongsToTenant scope and by
 * controllers when persisting new tenant-owned rows.
 *
 * Set by SetTenantContext middleware on each request:
 *   - Logged-in user: their `users.tenant_id`
 *   - Super-admin: null (no global scope), or whatever they explicitly pick
 *     via the ?tenant_id= query for "view as".
 */
class TenantContext
{
    private static ?int $tenantId = null;
    private static bool $isSuperAdmin = false;

    public static function set(?int $tenantId, bool $isSuperAdmin = false): void
    {
        self::$tenantId    = $tenantId;
        self::$isSuperAdmin = $isSuperAdmin;
    }

    public static function reset(): void
    {
        self::$tenantId    = null;
        self::$isSuperAdmin = false;
    }

    public static function id(): ?int          { return self::$tenantId; }
    public static function isSuperAdmin(): bool { return self::$isSuperAdmin; }
}
