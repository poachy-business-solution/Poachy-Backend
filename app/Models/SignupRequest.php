<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A self sign-up from poachy.com/signup, from the first form to a ready workspace.
 *
 * @property string $id
 * @property string $shop_name
 * @property string $owner_name
 * @property string $phone
 * @property string $email
 * @property string $town
 * @property int $branches
 * @property int|null $business_category_id
 * @property string|null $workspace
 * @property string|null $phone_code_hash
 * @property string|null $email_code_hash
 * @property Carbon|null $codes_expire_at
 * @property int $code_attempts
 * @property int $code_sends
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $email_verified_at
 * @property string|null $password_hash
 * @property Carbon|null $terms_accepted_at
 * @property string $status
 * @property string|null $tenant_id
 * @property string|null $failure_reason
 * @property Carbon $expires_at
 */
class SignupRequest extends Model
{
    use HasUuids;

    public const PENDING = 'pending';

    public const VERIFIED = 'verified';

    public const PROVISIONING = 'provisioning';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public const SOURCE_ADMIN = 'admin';

    protected $connection = 'central';

    protected $fillable = [
        'shop_name', 'owner_name', 'phone', 'email', 'town', 'branches', 'business_category_id', 'workspace',
        'phone_code_hash', 'email_code_hash', 'codes_expire_at', 'code_attempts', 'code_sends', 'phone_verified_at',
        'email_verified_at', 'password_hash', 'terms_accepted_at', 'status', 'tenant_id', 'failure_reason', 'ip', 'expires_at', 'source', 'created_by_admin',
    ];

    protected $hidden = ['phone_code_hash', 'email_code_hash', 'password_hash'];

    protected function casts(): array
    {
        return [
            'branches' => 'integer',
            'code_attempts' => 'integer',
            'code_sends' => 'integer',
            'codes_expire_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isVerified(): bool
    {
        return $this->phone_verified_at !== null && $this->email_verified_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
