<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\TenantConfiguration;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TenantConfigurationObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the TenantConfiguration "created" event.
     */
    public function created(TenantConfiguration $config): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $config,
                action: 'created',
                oldValues: null,
                newValues: $this->sanitizeConfigValue($config->toArray()),
                description: $this->generateCreationDescription($config),
                tags: ['tenant_config', 'configuration', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create tenant configuration audit log', [
                'tenant_id' => tenant()?->id,
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the TenantConfiguration "updated" event.
     */
    public function updated(TenantConfiguration $config): void
    {
        $this->clearCache();

        try {
            // Full audit mode - always log updates
            if ($config->wasChanged()) {
                $oldValues = $config->getOriginal();
                $changes = $config->getChanges();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($config, $changes);

                // Add specific tags based on config type
                $tags = ['tenant_config', 'configuration', 'critical'];
                $tags[] = $config->config_type; // e.g., 'general', 'payment', 'notification'
                $tags[] = $config->config_group ?? 'ungrouped';

                // Add sensitivity tag if config contains sensitive data
                if ($this->isSensitiveConfig($config->config_key)) {
                    $tags[] = 'sensitive';
                }

                $this->auditService->createAudit(
                    model: $config,
                    action: 'updated',
                    oldValues: $this->sanitizeConfigValue($oldValues),
                    newValues: $this->sanitizeConfigValue($changes),
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create tenant configuration update audit log', [
                'tenant_id' => tenant()?->id,
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the TenantConfiguration "deleted" event.
     */
    public function deleted(TenantConfiguration $config): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $config,
                action: 'deleted',
                oldValues: $this->sanitizeConfigValue($config->toArray()),
                newValues: null,
                description: $this->generateDeletionDescription($config),
                tags: ['tenant_config', 'configuration', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create tenant configuration deletion audit log', [
                'tenant_id' => tenant()?->id,
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear tenant configuration cache
     */
    protected function clearCache(): void
    {
        try {
            if (tenant()) {
                Cache::tags(['tenant', tenant()->id, 'config'])->flush();

                Log::debug('Tenant configuration cache cleared', [
                    'tenant_id' => tenant()->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear tenant configuration cache', [
                'tenant_id' => tenant()?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sanitize sensitive config values for audit logs
     */
    private function sanitizeConfigValue(array $data): array
    {
        if (!isset($data['config_key'], $data['config_value'])) {
            return $data;
        }

        $configKey = $data['config_key'];

        // List of sensitive config keys that should be masked
        $sensitiveKeys = [
            'api_key',
            'api_secret',
            'payment_secret',
            'payment_key',
            'mpesa_consumer_key',
            'mpesa_consumer_secret',
            'mpesa_passkey',
            'smtp_password',
            'database_password',
            'encryption_key',
            'jwt_secret',
            'webhook_secret',
        ];

        // Check if this is a sensitive config
        foreach ($sensitiveKeys as $sensitivePattern) {
            if (str_contains(strtolower($configKey), $sensitivePattern)) {
                $data['config_value'] = '[REDACTED]';
                break;
            }
        }

        return $data;
    }

    /**
     * Check if a config key is sensitive
     */
    private function isSensitiveConfig(string $configKey): bool
    {
        $sensitivePatterns = [
            'api_key',
            'api_secret',
            'secret',
            'password',
            'token',
            'key',
            'passkey',
        ];

        $lowerKey = strtolower($configKey);

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($lowerKey, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate description for configuration creation
     */
    private function generateCreationDescription(TenantConfiguration $config): string
    {
        $user = Auth::user()?->name ?? 'System';
        $configType = $config->config_type;
        $configGroup = $config->config_group ? " in {$config->config_group} group" : '';

        // Don't show value if sensitive
        $valueInfo = '';
        if (!$this->isSensitiveConfig($config->config_key)) {
            $value = is_array($config->config_value)
                ? json_encode($config->config_value)
                : $config->config_value;

            // Truncate long values
            if (strlen($value) > 50) {
                $value = substr($value, 0, 47) . '...';
            }

            $valueInfo = " with value: {$value}";
        }

        return "{$user} created {$configType} configuration '{$config->config_key}'{$configGroup}{$valueInfo}";
    }

    /**
     * Generate description for configuration update
     */
    private function generateUpdateDescription(TenantConfiguration $config, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Config value change
        if (isset($changes['config_value'])) {
            if ($this->isSensitiveConfig($config->config_key)) {
                return "{$user} updated sensitive configuration '{$config->config_key}'";
            }

            $oldValue = $config->getOriginal('config_value');
            $newValue = $changes['config_value'];

            // Handle array values
            $oldValueStr = is_array($oldValue) ? json_encode($oldValue) : $oldValue;
            $newValueStr = is_array($newValue) ? json_encode($newValue) : $newValue;

            // Truncate long values
            if (strlen($oldValueStr) > 50) {
                $oldValueStr = substr($oldValueStr, 0, 47) . '...';
            }
            if (strlen($newValueStr) > 50) {
                $newValueStr = substr($newValueStr, 0, 47) . '...';
            }

            return "{$user} changed configuration '{$config->config_key}' from '{$oldValueStr}' to '{$newValueStr}'";
        }

        // Active status change
        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'enabled' : 'disabled';
            return "{$user} {$status} configuration '{$config->config_key}'";
        }

        // Config type change
        if (isset($changes['config_type'])) {
            $oldType = $config->getOriginal('config_type');
            $newType = $changes['config_type'];
            return "{$user} changed configuration '{$config->config_key}' type from {$oldType} to {$newType}";
        }

        // Config group change
        if (isset($changes['config_group'])) {
            $oldGroup = $config->getOriginal('config_group') ?? 'none';
            $newGroup = $changes['config_group'] ?? 'none';
            return "{$user} moved configuration '{$config->config_key}' from {$oldGroup} to {$newGroup} group";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated configuration '{$config->config_key}' - {$changedFields}";
    }

    /**
     * Generate description for configuration deletion
     */
    private function generateDeletionDescription(TenantConfiguration $config): string
    {
        $user = Auth::user()?->name ?? 'System';
        $configType = $config->config_type;
        $configGroup = $config->config_group ? " from {$config->config_group} group" : '';

        return "{$user} deleted {$configType} configuration '{$config->config_key}'{$configGroup}";
    }
}
