<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One email or SMS Poachy sent or tried to send. Append-only.
 *
 * @property int $id
 * @property string $channel
 * @property string $template
 * @property string $recipient
 * @property string|null $subject
 * @property string|null $tenant_id
 * @property string $status
 * @property string|null $provider_id
 * @property string|null $error
 * @property Carbon $created_at
 */
class MessageLog extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'central';

    protected $fillable = ['channel', 'template', 'recipient', 'subject', 'tenant_id', 'status', 'provider_id', 'error'];
}
