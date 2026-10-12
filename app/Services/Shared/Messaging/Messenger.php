<?php

namespace App\Services\Shared\Messaging;

use App\Helpers\PhoneNumber;
use App\Jobs\Central\SendSmsJob;
use App\Mail\PoachyMail;
use App\Models\BusinessDetail;
use App\Models\MessageLog;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one place Poachy sends email and SMS from. Every send gets a `message_logs` row
 * (queued → sent / failed), so support and the admin panel can see what went out.
 *
 *   $messenger->email('owner@shop.co.ke', new LowBalanceMail(...), tenantId: $tenant->id);
 *   $messenger->sms('0712345678', 'Your Poachy balance is low…', 'low_balance', $tenant->id);
 *   $messenger->toShop($tenant, new LowBalanceMail(...), 'low_balance', 'Your Poachy balance…');
 */
class Messenger
{
    /** Mail header that links a sent email back to its log row (see MarkEmailSent). */
    public const LOG_HEADER = 'X-Poachy-Message-Log';

    /**
     * Queue (or send) an email and log it. Only {@see PoachyMail}s are marked "sent" later;
     * other mailables stay "queued" in the log.
     */
    public function email(string $to, Mailable $mail, ?string $tenantId = null): MessageLog
    {
        $log = MessageLog::create([
            'channel' => 'email',
            'template' => class_basename($mail),
            'recipient' => Str::lower(trim($to)),
            'subject' => $mail instanceof PoachyMail ? $mail->subjectForLog() : null,
            'tenant_id' => $tenantId,
            'status' => 'queued',
        ]);

        if ($mail instanceof PoachyMail) {
            $mail->messageLogId = $log->id;
        }

        try {
            $mail instanceof ShouldQueue ? Mail::to($to)->queue($mail) : Mail::to($to)->send($mail);
        } catch (Throwable $e) {
            $this->markFailed($log, $e);
        }

        return $log;
    }

    /**
     * Queue an SMS and log it. `$template` is a short key for the send log (e.g. "signup_code").
     */
    public function sms(string $to, string $message, string $template, ?string $tenantId = null): MessageLog
    {
        try {
            $phone = '+'.PhoneNumber::normalize($to);
        } catch (Throwable $e) {
            return $this->markFailed(MessageLog::create([
                'channel' => 'sms', 'template' => $template, 'recipient' => mb_substr($to, 0, 191),
                'subject' => Str::limit($message, 120), 'tenant_id' => $tenantId, 'status' => 'queued',
            ]), $e);
        }

        $log = MessageLog::create([
            'channel' => 'sms',
            'template' => $template,
            'recipient' => $phone,
            'subject' => Str::limit($message, 120),
            'tenant_id' => $tenantId,
            'status' => 'queued',
        ]);

        SendSmsJob::dispatch($log->id, $phone, $message);

        return $log;
    }

    /**
     * Message a shop at its business email and/or phone (from its central business details).
     *
     * @return array{email: MessageLog|null, sms: MessageLog|null}
     */
    public function toShop(Tenant|string $tenant, ?Mailable $mail = null, ?string $smsTemplate = null, ?string $smsText = null): array
    {
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : $tenant;
        $details = BusinessDetail::query()->where('tenant_id', $tenantId)->first();
        $sent = ['email' => null, 'sms' => null];

        if ($details === null) {
            Log::warning('Messenger::toShop: no business details for tenant', ['tenant_id' => $tenantId]);

            return $sent;
        }

        if ($mail !== null && filled($details->business_email)) {
            $sent['email'] = $this->email($details->business_email, $mail, $tenantId);
        }

        if ($smsTemplate !== null && $smsText !== null && filled($details->business_phone)) {
            $sent['sms'] = $this->sms($details->business_phone, $smsText, $smsTemplate, $tenantId);
        }

        return $sent;
    }

    private function markFailed(MessageLog $log, Throwable $e): MessageLog
    {
        $log->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        Log::error('Message send failed', ['message_log_id' => $log->id, 'error' => $e->getMessage()]);

        return $log;
    }
}
