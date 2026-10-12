<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A verification check for a shop: ID photo, KRA PIN, permit (optional), GPS pin, shop photo.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $check
 * @property string|null $file_path
 * @property string|null $file_mime
 * @property string|null $value
 * @property string $status
 */
class TenantVerification extends Model
{
    public const CHECKS = ['id' => 'ID photo', 'kra_pin' => 'KRA PIN', 'permit' => 'Business permit', 'location' => 'GPS pin', 'photo' => 'Shop photo'];

    /** Needed before a shop can be verified (the permit is optional). */
    public const REQUIRED = ['id', 'kra_pin', 'location', 'photo'];

    /** Checks that are a file; KRA PIN and location are values (a KRA certificate may be attached). */
    public const FILE_CHECKS = ['id', 'permit', 'photo'];

    public const CURRENT = ['pending', 'verified', 'rejected'];

    protected $connection = 'central';

    protected $fillable = ['tenant_id', 'check', 'file_path', 'file_mime', 'file_size', 'value', 'status', 'uploaded_by', 'reviewed_by', 'reviewed_at', 'note'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'check' => $this->check,
            'label' => self::CHECKS[$this->check] ?? $this->check,
            'value' => $this->value,
            'has_file' => $this->file_path !== null,
            'file_mime' => $this->file_mime,
            'status' => $this->status,
            'note' => $this->note,
            'uploaded_by' => $this->uploaded_by,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
