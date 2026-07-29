<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $connection = 'central';

    protected $table = 'system_notifications';

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'type',
        'title',
        'message',
        'data',
        'action_url',
        'action_label',
        'is_read',
        'read_at',
        'sent_via_email',
        'email_sent_at',
        'sent_via_sms',
        'sms_sent_at',
        'sent_via_push',
        'push_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'recipient_id' => 'integer',
            'data' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'sent_via_email' => 'boolean',
            'email_sent_at' => 'datetime',
            'sent_via_sms' => 'boolean',
            'sms_sent_at' => 'datetime',
            'sent_via_push' => 'boolean',
            'push_sent_at' => 'datetime',
        ];
    }

    // Scopes

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeForRecipient(Builder $query, string $recipientType, int $recipientId): Builder
    {
        return $query->where('recipient_type', $recipientType)->where('recipient_id', $recipientId);
    }

    // Helpers

    public function markAsRead(): bool
    {
        return $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
