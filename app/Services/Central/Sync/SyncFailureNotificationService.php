<?php

namespace App\Services\Central\Sync;

use App\Mail\Central\Sync\SyncFailureMail;
use App\Models\SyncQueueInbound;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SyncFailureNotificationService
{
    public function notifyTenantOwners(SyncQueueInbound $syncQueue, string $syncType): void
    {
        try {
            $metadata = $syncQueue->metadata ?? [];

            if (isset($metadata['sync_failure_email_sent_at'])) {
                Log::info('Central sync failure email already sent', [
                    'tenant_id' => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                    'sync_type' => $syncType,
                ]);

                return;
            }

            $tenant = Tenant::on('central')->find($syncQueue->tenant_id);

            if (! $tenant) {
                Log::warning('Tenant not found for sync failure email', [
                    'tenant_id' => $syncQueue->tenant_id,
                    'sync_queue_id' => $syncQueue->id,
                    'sync_type' => $syncType,
                ]);

                return;
            }

            $initialized = false;
            tenancy()->initialize($tenant);
            $initialized = true;

            $owners = TenantUser::role('owner')
                ->whereNotNull('email')
                ->get();

            if ($initialized && tenancy()->initialized) {
                tenancy()->end();
            }

            if ($owners->isEmpty()) {
                Log::warning('No tenant owners found for central sync failure email', [
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
                        'tenant_syncable_id' => $syncQueue->tenant_syncable_id,
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
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            Log::error('Failed to send central sync failure email', [
                'tenant_id' => $syncQueue->tenant_id,
                'sync_queue_id' => $syncQueue->id,
                'sync_type' => $syncType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
