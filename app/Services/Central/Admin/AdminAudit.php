<?php

namespace App\Services\Central\Admin;

use App\Models\AdminAuditLog;
use App\Support\ActingAdmin;
use Illuminate\Http\Request;

/** Writes the admin audit log for actions taken through the private admin API. */
class AdminAudit
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        Request $request,
        string $action,
        ?string $tenantId,
        ?string $subjectType = null,
        string|int|null $subjectId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
    ): AdminAuditLog {
        $admin = ActingAdmin::from($request);

        return AdminAuditLog::query()->create([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_role' => $admin->role,
            'action' => $action,
            'tenant_id' => $tenantId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId === null ? null : (string) $subjectId,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'ip' => $request->server('REMOTE_ADDR'),
            'request_id' => $request->headers->get('X-Request-ID'),
        ]);
    }
}
