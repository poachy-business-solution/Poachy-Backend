<?php

namespace App\Traits\Tenant;

use App\Services\Tenant\AuditService;

/**
 * Trait HasAuditLogging
 *
 * Provides convenient methods for models to create audit logs.
 * Models using this trait can easily log actions without directly
 * interacting with the AuditService.
 *
 * Usage:
 * class Product extends Model {
 *     use HasAuditLogging;
 * }
 *
 * Then in observers or services:
 * $product->logAudit('created');
 * $product->logAudit('updated', $oldValues, $newValues);
 */
trait HasAuditLogging
{
    /**
     * Temporary storage for old values
     *
     * @var array
     */
    protected array $auditOldValues = [];

    /**
     * Get the audit service instance
     *
     * @return AuditService
     */
    protected function getAuditService(): AuditService
    {
        return app(AuditService::class);
    }

    /**
     * Log an audit entry for this model
     *
     * @param string $action Action performed (created, updated, deleted, etc.)
     * @param array|null $oldValues Values before change
     * @param array|null $newValues Values after change
     * @param string|null $description Custom description
     * @param array|null $tags Custom tags
     * @return void
     */
    public function logAudit(
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?array $tags = null
    ): void {
        $this->getAuditService()->createAudit(
            model: $this,
            action: $action,
            oldValues: $oldValues,
            newValues: $newValues,
            description: $description,
            tags: $tags
        );
    }

    /**
     * Log audit with aggregated children data
     *
     * Useful for models like Sale, PurchaseOrder, StockTransfer
     * that need to include related items in the audit.
     *
     * @param string $action Action performed
     * @param string|null $description Custom description
     * @param array|null $tags Custom tags
     * @return void
     */
    public function logAggregatedAudit(
        string $action,
        ?string $description = null,
        ?array $tags = null
    ): void {
        $aggregatedData = $this->getAuditService()->getAggregatedData($this);

        $this->getAuditService()->createAggregatedAudit(
            model: $this,
            action: $action,
            aggregatedData: $aggregatedData,
            description: $description,
            tags: $tags
        );
    }

    /**
     * Get auditable fields for this model
     *
     * Override this method in your model to specify which fields
     * should be included in audits. If not overridden, all fields
     * will be included (subject to global excluded_fields config).
     *
     * @return array
     */
    public function getAuditableFields(): array
    {
        // Get all fillable fields by default
        return $this->getFillable();
    }

    /**
     * Get critical fields for this model
     *
     * These are fields that should always trigger an audit when changed,
     * even if audit_mode is 'critical_only'.
     *
     * This method reads from config but can be overridden in models
     * for runtime determination.
     *
     * @return array
     */
    public function getCriticalFields(): array
    {
        $modelClass = get_class($this);
        return config("audit.models.{$modelClass}.critical_fields", []);
    }

    /**
     * Check if a specific field should trigger an audit
     *
     * @param string $field Field name
     * @return bool
     */
    public function shouldAuditField(string $field): bool
    {
        $auditableFields = $this->getAuditableFields();
        $excludedFields = config('audit.excluded_fields', []);

        return in_array($field, $auditableFields)
            && !in_array($field, $excludedFields);
    }

    /**
     * Get audit tags for this model
     *
     * Override this method in your model to provide custom tags
     * based on model state or context.
     *
     * @return array
     */
    public function getAuditTags(): array
    {
        $modelClass = get_class($this);
        $config = config("audit.models.{$modelClass}");

        return $config['default_tags'] ?? [strtolower(class_basename($this))];
    }

    /**
     * Check if this model should be audited for the given action
     *
     * @param string $action Action being performed
     * @return bool
     */
    public function shouldAudit(string $action): bool
    {
        return $this->getAuditService()->shouldAudit($this, $action);
    }

    /**
     * Store old values for audit comparison
     *
     * Call this method in the 'updating' observer event to capture
     * the original values before they change.
     *
     * IMPORTANT: This stores values in memory, NOT in the database
     *
     * @return void
     */
    public function storeOldValuesForAudit(): void
    {
        $this->auditOldValues = $this->getOriginal();
    }

    /**
     * Get stored old values for audit
     *
     * @return array
     */
    public function getOldValuesForAudit(): array
    {
        return $this->auditOldValues;
    }

    /**
     * Clear stored old values (cleanup after audit)
     *
     * @return void
     */
    public function clearOldValuesForAudit(): void
    {
        $this->auditOldValues = [];
    }

    /**
     * Get only the changed critical fields
     *
     * Useful for critical_only audit mode to only log relevant changes.
     *
     * @return array
     */
    public function getCriticalChanges(): array
    {
        $changes = $this->getChanges();
        $criticalFields = $this->getCriticalFields();

        if (empty($criticalFields)) {
            return $changes;
        }

        return array_intersect_key($changes, array_flip($criticalFields));
    }

    /**
     * Scope query to auditable models
     *
     * Useful for finding all models that have auditing enabled.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAuditable($query)
    {
        $modelClass = get_class($this);
        $auditMode = config("audit.models.{$modelClass}.audit_mode", 'none');

        if ($auditMode === 'none') {
            return $query->whereRaw('1 = 0'); // Return empty
        }

        return $query;
    }
}
