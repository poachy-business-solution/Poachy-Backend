<?php

namespace App\Services\Tenant\Sync;

use App\Mail\Central\Sync\SyncFailureMail;
use App\Models\Tenant\SyncQueueOutbound;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SyncFailureNotificationService
{
    public function notifyOwners(SyncQueueOutbound $syncQueue, string $syncType): void
    {
        try {
            $metadata = $syncQueue->metadata ?? [];

            if (isset($metadata['sync_failure_email_sent_at'])) {
                Log::info('Sync failure email already sent', [
                    'tenant_id' => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                    'sync_type' => $syncType,
                ]);

                return;
            }

            $owners = User::role('owner')
                ->whereNotNull('email')
                ->get();

            if ($owners->isEmpty()) {
                Log::warning('No tenant owners found for sync failure email', [
                    'tenant_id' => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                    'sync_type' => $syncType,
                ]);

                return;
            }

            foreach ($owners as $owner) {
                Mail::to($owner->email)->send(new SyncFailureMail(
                    syncType: $syncType,
                    syncQueueId: $syncQueue->id,
                    errorMessage: $syncQueue->error_message ?? 'Unknown sync error',
                    details: [
                        'tenant_id' => $syncQueue->tenant_id,
                        'action' => $syncQueue->action,
                        'syncable_type' => $syncQueue->syncable_type,
                        'syncable_id' => $syncQueue->syncable_id,
                        'error_code' => $syncQueue->error_code,
                    ],
                ));
            }

            $syncQueue->update([
                'metadata' => array_merge($metadata, [
                    'sync_failure_email_sent_at' => now()->toISOString(),
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send sync failure email', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'sync_type' => $syncType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
