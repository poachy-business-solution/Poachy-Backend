<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One action taken through the private admin API. Append-only.
 *
 * @property int $id
 * @property string $admin_id
 * @property string|null $admin_name
 * @property string $admin_role
 * @property string $action
 * @property string|null $tenant_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $reason
 */
class AdminAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'central';

    protected $fillable = [
        'admin_id', 'admin_name', 'admin_role', 'action', 'tenant_id', 'subject_type', 'subject_id',
        'before', 'after', 'reason', 'ip', 'request_id',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }
}
