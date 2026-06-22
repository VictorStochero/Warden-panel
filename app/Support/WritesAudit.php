<?php

namespace App\Support;

use Throwable;
use VictorStochero\Warden\Models\AuditLog;

trait WritesAudit
{
    /**
     * Record a panel management action into wdn_audit_log. Best-effort: the
     * audit trail must never break the action it describes. Never pass a
     * token/secret in $meta.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function audit(string $action, ?string $target, array $meta = []): void
    {
        try {
            AuditLog::create([
                'actor' => auth()->user()?->email ?? 'local',
                'action' => $action,
                'target' => $target,
                'method' => 'PANEL',
                'ip' => request()->ip(),
                'meta' => $meta !== [] ? $meta : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit is best-effort — never break the action.
        }
    }
}
