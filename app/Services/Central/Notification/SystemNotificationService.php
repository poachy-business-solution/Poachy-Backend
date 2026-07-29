<?php

namespace App\Services\Central\Notification;

use App\Models\SystemNotification;

class SystemNotificationService
{
    /**
     * Create an in-app notification for a tenant (business owner).
     *
     * @param  int  $businessDetailId  `business_details.id` — the central-side record for the tenant.
     */
    public function notifyTenant(
        int $businessDetailId,
        string $type,
        string $title,
        string $message,
        ?array $data = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): SystemNotification {
        return SystemNotification::on('central')->create([
            'recipient_type' => 'tenant',
            'recipient_id' => $businessDetailId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'action_url' => $actionUrl,
            'action_label' => $actionLabel,
        ]);
    }
}
