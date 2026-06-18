<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Audit
{
    /**
     * Record an audit event. Safe to call without an active request (falls back to nulls).
     *
     *   Audit::log('order.void', $order, ['reason' => $reason])
     */
    public static function log(string $action, ?Model $subject = null, array $metadata = []): ?AuditLog
    {
        try {
            $request = request();
            $user = $request?->user();

            return AuditLog::create([
                'tenant_id'    => $user?->tenant_id,
                'user_id'      => $user?->id,
                'action'       => $action,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'ip_address'   => $request?->ip(),
                'metadata'     => $metadata,
            ]);
        } catch (\Throwable $e) {
            // Never let audit logging break the user-facing request
            return null;
        }
    }
}
