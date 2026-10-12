<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment page link (poachy.com/pay/<token>). Only the SHA-256 of the token is stored;
 * look links up with {@see PaymentLink::findByToken()}.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $token_hash
 * @property string|null $amount
 * @property string $purpose
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property string|null $created_by
 */
class PaymentLink extends Model
{
    use HasFactory;

    protected $connection = 'central';

    protected $fillable = ['tenant_id', 'token_hash', 'amount', 'purpose', 'expires_at', 'used_at', 'created_by'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        return static::query()->where('token_hash', self::hashToken($token))->first();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
