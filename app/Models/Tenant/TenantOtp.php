<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantOtp extends Model
{
    use HasFactory;

    protected $table = 'tenant_otps';

    public const OTP_TYPE = ['login', 'password_reset'];

    protected $fillable = [
        'user_id',
        'otp_code',
        'type',
        'expires_at',
        'is_used',
        'used_at',
        'ip_address',
        'user_agent',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'is_used' => 'boolean',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return !$this->is_used
            && $this->expires_at->isFuture()
            && $this->attempts < 3;
    }

    public function markAsUsed(): void
    {
        $this->update([
            'is_used' => true,
            'used_at' => now(),
        ]);
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }
}
