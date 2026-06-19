<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $connection = 'central';
    protected $table = 'otps';

    public const OTP_TYPE = ['login', 'password_reset'];

    protected $fillable = [
        'user_id',
        'otp_code',
        'type', // login, password_reset
        'is_used',
        'used_at',
        'expires_at',
        'ip_address',
        'user_agent',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user()
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
