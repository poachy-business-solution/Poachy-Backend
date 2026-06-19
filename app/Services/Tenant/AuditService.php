<?php

namespace App\Services\Tenant;

use App\Models\Tenant\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;


class AuditService
{
    /**
     * Create an audit log entry
     *
     * @param Model $model The model being audited
     * @param string $action The action performed (created, updated, deleted, etc.)
     * @param array|null $oldValues Values before the change
     * @param array|null $newValues Values after the change
     * @param string|null $description Custom description (auto-generated if null)
     * @param array|null $tags Custom tags (auto-generated if null)
     * @return AuditLog|null
     */
    public function createAudit(
        Model $model,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?array $tags = null
    ): ?AuditLog {
        // Check if auditing is globally enabled
        if (!config('audit.enabled', true)) {
            return null;
        }

        // Check if this model/action should be audited
        if (!$this->shouldAudit($model, $action)) {
            return null;
        }

        // Sanitize values (remove sensitive/excluded fields)
        $oldValues = $this->sanitizeValues($oldValues ?? []);
        $newValues = $this->sanitizeValues($newValues ?? []);

        // Auto-generate description if not provided
        $description = $description ?? $this->formatDescription($model, $action);

        // Auto-generate tags if not provided
        $tags = $tags ?? $this->generateTags($model);

        // Check if async auditing is enabled
        if (config('audit.async_enabled', false)) {
            return $this->queueAudit($model, $action, $oldValues, $newValues, $description, $tags);
        }

        // Create audit log synchronously
        return $this->createAuditLog($model, $action, $oldValues, $newValues, $description, $tags);
    }

    /**
     * Create audit log with aggregated children
     *
     * @param Model $model Parent model
     * @param string $action Action performed
     * @param array $aggregatedData Data including children relations
     * @param string|null $description Custom description
     * @param array|null $tags Custom tags
     * @return AuditLog|null
     */
    public function createAggregatedAudit(
        Model $model,
        string $action,
        array $aggregatedData,
        ?string $description = null,
        ?array $tags = null
    ): ?AuditLog {
        if (!config('audit.enabled', true) || !$this->shouldAudit($model, $action)) {
            return null;
        }

        $aggregatedData = $this->sanitizeValues($aggregatedData);
        $description = $description ?? $this->formatDescription($model, $action);
        $tags = $tags ?? array_merge($this->generateTags($model), ['aggregated']);

        return $this->createAuditLog($model, $action, null, $aggregatedData, $description, $tags);
    }

    /**
     * Create bulk operation audit summary
     *
     * @param string $modelClass Model class being affected
     * @param string $action Action performed
     * @param int $affectedCount Number of records affected
     * @param array $criteria Bulk operation criteria
     * @param array $fieldsChanged Fields that were changed
     * @param string|null $description Custom description
     * @return AuditLog|null
     */
    public function createBulkAudit(
        string $modelClass,
        string $action,
        int $affectedCount,
        array $criteria,
        array $fieldsChanged,
        ?string $description = null
    ): ?AuditLog {
        if (!config('audit.enabled', true)) {
            return null;
        }

        // Use first model instance for reference
        $referenceModel = new $modelClass();

        $bulkData = [
            'affected_count' => $affectedCount,
            'fields_changed' => $fieldsChanged,
            'criteria' => $criteria,
        ];

        $description = $description ?? $this->formatBulkDescription(
            $referenceModel,
            $action,
            $affectedCount,
            $fieldsChanged
        );

        $tags = array_merge($this->generateTags($referenceModel), ['bulk']);

        return AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()?->name ?? 'System',
            'ip_address' => request()->ip(),
            'action' => "bulk_{$action}",
            'model_type' => $modelClass,
            'model_id' => 0, // No specific model ID for bulk
            'old_values' => null,
            'new_values' => $bulkData,
            'description' => $description,
            'tags' => implode(',', $tags),
        ]);
    }

    /**
     * Determine if model/action should be audited
     *
     * @param Model $model
     * @param string $action
     * @return bool
     */
    public function shouldAudit(Model $model, string $action): bool
    {
        $modelClass = get_class($model);
        $config = config("audit.models.{$modelClass}");

        // No config or audit_mode is 'none'
        if (!$config || ($config['audit_mode'] ?? 'none') === 'none') {
            return false;
        }

        // For 'full' mode, always audit
        if ($config['audit_mode'] === 'full') {
            return true;
        }

        // For 'critical_only' mode, check if critical fields changed
        if ($config['audit_mode'] === 'critical_only') {
            // Always audit create/delete
            if (in_array($action, ['created', 'deleted', 'restored'])) {
                return true;
            }

            // For updates, check if critical fields changed
            if ($action === 'updated') {
                return $this->hasCriticalChanges($model);
            }
        }

        return true;
    }

    /**
     * Check if model has critical field changes
     *
     * @param Model $model
     * @return bool
     */
    public function hasCriticalChanges(Model $model): bool
    {
        if (!$model->wasChanged()) {
            return false;
        }

        $modelClass = get_class($model);
        $criticalFields = config("audit.models.{$modelClass}.critical_fields", []);

        if (empty($criticalFields)) {
            return true; // No critical fields defined, audit all changes
        }

        $changes = array_keys($model->getChanges());
        return !empty(array_intersect($changes, $criticalFields));
    }

    /**
     * Format auto-generated description
     *
     * @param Model $model
     * @param string $action
     * @return string
     */
    private function formatDescription(Model $model, string $action): string
    {
        $user = Auth::user()?->name ?? 'System';
        $modelName = $this->getReadableModelName($model);
        $identifier = $this->getModelIdentifier($model);

        // Use template if available
        $template = config("audit.description_templates.{$action}");
        if ($template) {
            return str_replace(
                ['{user}', '{model}', '{identifier}', '{action}'],
                [$user, $modelName, $identifier, $action],
                $template
            );
        }

        // Fallback to default format
        return match ($action) {
            'created' => "{$user} created {$modelName} {$identifier}",
            'updated' => "{$user} updated {$modelName} {$identifier}",
            'deleted' => "{$user} deleted {$modelName} {$identifier}",
            'restored' => "{$user} restored {$modelName} {$identifier}",
            'approved' => "{$user} approved {$modelName} {$identifier}",
            'rejected' => "{$user} rejected {$modelName} {$identifier}",
            'cancelled' => "{$user} cancelled {$modelName} {$identifier}",
            'completed' => "{$user} completed {$modelName} {$identifier}",
            default => "{$user} performed {$action} on {$modelName} {$identifier}",
        };
    }

    /**
     * Format bulk operation description
     *
     * @param Model $model
     * @param string $action
     * @param int $count
     * @param array $fields
     * @return string
     */
    private function formatBulkDescription(
        Model $model,
        string $action,
        int $count,
        array $fields
    ): string {
        $user = Auth::user()?->name ?? 'System';
        $modelName = $this->getReadableModelName($model);
        $fieldsStr = implode(', ', $fields);

        return "{$user} bulk {$action} {$count} {$modelName} records (fields: {$fieldsStr})";
    }

    /**
     * Get human-readable model name
     *
     * @param Model $model
     * @return string
     */
    private function getReadableModelName(Model $model): string
    {
        $className = class_basename($model);
        // Convert PascalCase to readable format
        return strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $className));
    }

    /**
     * Get human-readable model identifier
     *
     * @param Model $model
     * @return string
     */
    private function getModelIdentifier(Model $model): string
    {
        // Try common identifier fields in order of preference
        $identifierFields = [
            'name',
            'title',
            'sale_number',
            'order_number',
            'expense_number',
            'transfer_number',
            'refund_number',
            'po_number',
            'customer_number',
            'sku',
            'code',
            'email',
        ];

        foreach ($identifierFields as $field) {
            if (isset($model->$field) && !empty($model->$field)) {
                return $model->$field;
            }
        }

        // Fallback to ID
        return "#{$model->id}";
    }

    /**
     * Generate audit tags
     *
     * @param Model $model
     * @return array
     */
    private function generateTags(Model $model): array
    {
        $modelClass = get_class($model);
        $config = config("audit.models.{$modelClass}");

        $tags = [strtolower(class_basename($model))];

        if (isset($config['default_tags'])) {
            $tags = array_merge($tags, $config['default_tags']);
        }

        return array_unique($tags);
    }

    /**
     * Remove sensitive/excluded fields from audit values
     *
     * @param array $values
     * @return array
     */
    private function sanitizeValues(array $values): array
    {
        if (empty($values)) {
            return [];
        }

        $excludedFields = config('audit.excluded_fields', []);

        // Remove excluded fields
        $sanitized = array_diff_key($values, array_flip($excludedFields));

        // Recursively sanitize nested arrays
        foreach ($sanitized as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeValues($value);
            }
        }

        return $sanitized;
    }

    /**
     * Actually create the audit log record
     *
     * @param Model $model
     * @param string $action
     * @param array|null $oldValues
     * @param array|null $newValues
     * @param string $description
     * @param array $tags
     * @return AuditLog
     */
    private function createAuditLog(
        Model $model,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        string $description,
        array $tags
    ): AuditLog {
        try {
            $auditLog = AuditLog::create([
                'user_id' => Auth::id(),
                'user_name' => Auth::user()?->name ?? 'System',
                'ip_address' => request()->ip(),
                'action' => $action,
                'model_type' => get_class($model),
                'model_id' => $model->id ?? 0,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'description' => $description,
                'tags' => implode(',', $tags),
            ]);

            Log::debug('Audit log created', [
                'tenant_id' => tenant()?->id,
                'audit_id' => $auditLog->id,
                'model' => get_class($model),
                'model_id' => $model->id,
                'action' => $action,
            ]);

            return $auditLog;
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            Log::error('Failed to create audit log', [
                'tenant_id' => tenant()?->id,
                'model' => get_class($model),
                'model_id' => $model->id ?? 0,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Queue audit log creation (async)
     *
     * @param Model $model
     * @param string $action
     * @param array|null $oldValues
     * @param array|null $newValues
     * @param string $description
     * @param array $tags
     * @return null
     */
    private function queueAudit(
        Model $model,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        string $description,
        array $tags
    ): null {
        try {
            \App\Jobs\Tenant\CreateAuditLogJob::dispatch(
                tenantId: tenant()->id,
                userId: Auth::id(),
                userName: Auth::user()?->name,
                ipAddress: request()->ip(),
                action: $action,
                modelType: get_class($model),
                modelId: $model->id ?? 0,
                oldValues: $oldValues,
                newValues: $newValues,
                description: $description,
                tags: implode(',', $tags)
            );

            Log::debug('Audit log queued', [
                'tenant_id' => tenant()->id,
                'model' => get_class($model),
                'model_id' => $model->id ?? 0,
                'action' => $action,
            ]);
        } catch (\Exception $e) {
            // If queueing fails, fallback to synchronous creation
            Log::warning('Failed to queue audit log, falling back to sync', [
                'tenant_id' => tenant()->id,
                'model' => get_class($model),
                'error' => $e->getMessage(),
            ]);

            $this->createAuditLog($model, $action, $oldValues, $newValues, $description, $tags);
        }

        return null;
    }

    /**
     * Get aggregate data for parent model with children
     *
     * @param Model $model
     * @return array
     */
    public function getAggregatedData(Model $model): array
    {
        $modelClass = get_class($model);
        $config = config("audit.models.{$modelClass}");

        $data = ['parent' => $model->toArray()];

        // Include configured child relations
        if (isset($config['aggregate_children'])) {
            foreach ($config['aggregate_children'] as $relation) {
                if (method_exists($model, $relation)) {
                    $data[$relation] = $model->$relation()->get()->toArray();
                }
            }
        }

        return $data;
    }

    /**
     * Check if bulk operation threshold is reached
     *
     * @param int $affectedCount
     * @return bool
     */
    public function shouldUseBulkAudit(int $affectedCount): bool
    {
        $threshold = config('audit.bulk_operations.summary_threshold', 10);
        return $affectedCount >= $threshold;
    }


    // Log Queries

    /**
     * Get paginated audit logs with filters
     */
    public function getPaginatedAudits(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query();

        // Apply all filters
        $this->applyDateRangeFilter($query, $filters['date_range']);
        $this->applyModelFilter($query, $filters['model']);
        $this->applyActionFilter($query, $filters['actions']);
        $this->applyUserFilter($query, $filters['user_id']);
        $this->applyTagFilter($query, $filters['tags']);
        $this->applyCategoryFilter($query, $filters['category']);
        $this->applyFlagFilters($query, $filters['flags']);
        $this->applySearchFilter($query, $filters['search']);

        // Apply sorting
        $query->orderBy(
            $filters['sort']['by'],
            $filters['sort']['order']
        );

        // Paginate
        return $query->paginate(
            $filters['pagination']['per_page'],
            ['*'],
            'page',
            $filters['pagination']['page']
        );
    }

    /**
     * Get grouped audit summary
     */
    public function getGroupedSummary(array $filters, string $groupBy): array
    {
        $query = AuditLog::query();

        // Apply filters (excluding pagination)
        $this->applyDateRangeFilter($query, $filters['date_range']);
        $this->applyModelFilter($query, $filters['model']);
        $this->applyActionFilter($query, $filters['actions']);
        $this->applyUserFilter($query, $filters['user_id']);
        $this->applyTagFilter($query, $filters['tags']);
        $this->applyCategoryFilter($query, $filters['category']);
        $this->applyFlagFilters($query, $filters['flags']);
        $this->applySearchFilter($query, $filters['search']);

        // Group and aggregate
        return match ($groupBy) {
            'date' => $this->groupByDate($query),
            'model' => $this->groupByModel($query),
            'user' => $this->groupByUser($query),
            'action' => $this->groupByAction($query),
            'tag' => $this->groupByTag($query),
            default => [],
        };
    }

    /**
     * Get audit statistics
     */
    public function getStatistics(array $filters): array
    {
        $query = AuditLog::query();

        // Apply filters
        $this->applyDateRangeFilter($query, $filters['date_range']);
        $this->applyModelFilter($query, $filters['model']);
        $this->applyActionFilter($query, $filters['actions']);
        $this->applyUserFilter($query, $filters['user_id']);
        $this->applyTagFilter($query, $filters['tags']);
        $this->applyCategoryFilter($query, $filters['category']);
        $this->applyFlagFilters($query, $filters['flags']);
        $this->applySearchFilter($query, $filters['search']);

        // Get base statistics
        $totalCount = (clone $query)->count();

        return [
            'total_count' => $totalCount,
            'by_action' => $this->getActionBreakdown($query),
            'by_model' => $this->getModelBreakdown($query),
            'by_user' => $this->getUserBreakdown($query),
            'by_date' => $this->getDateBreakdown($query),
            'critical_count' => (clone $query)->withTag('critical')->count(),
            'financial_count' => (clone $query)->financial()->count(),
            'bulk_count' => (clone $query)->bulkOperations()->count(),
        ];
    }

    /**
     * Get recent activity summary
     */
    public function getRecentActivity(int $days = 7, int $limit = 10): array
    {
        $query = AuditLog::query()
            ->recent($days)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        return $query->get()->map(function ($audit) {
            return [
                'id' => $audit->id,
                'action' => $audit->action,
                'model' => $audit->model_name,
                'user' => $audit->user_name,
                'description' => $audit->description,
                'is_critical' => $audit->hasTag('critical'),
                'is_financial' => $audit->isFinancial(),
                'created_at' => $audit->created_at->toISOString(),
                'created_at_human' => $audit->created_at->diffForHumans(),
            ];
        })->toArray();
    }

    /**
     * Apply date range filter
     */
    private function applyDateRangeFilter(Builder $query, array $dateRange): void
    {
        if (!empty($dateRange['from'])) {
            $query->whereDate('created_at', '>=', $dateRange['from']);
        }

        if (!empty($dateRange['to'])) {
            $query->whereDate('created_at', '<=', $dateRange['to']);
        }
    }

    /**
     * Apply model filter
     */
    private function applyModelFilter(Builder $query, array $model): void
    {
        if (!empty($model['type'])) {
            $query->forModel($model['type']);
        }

        if (!empty($model['id'])) {
            $query->where('model_id', $model['id']);
        }
    }

    /**
     * Apply action filter
     */
    private function applyActionFilter(Builder $query, array $actions): void
    {
        if (!empty($actions)) {
            $query->actions($actions);
        }
    }

    /**
     * Apply user filter
     */
    private function applyUserFilter(Builder $query, ?int $userId): void
    {
        if ($userId) {
            $query->byUser($userId);
        }
    }

    /**
     * Apply tag filter
     */
    private function applyTagFilter(Builder $query, array $tagFilter): void
    {
        if (empty($tagFilter['values'])) {
            return;
        }

        if ($tagFilter['match'] === 'all') {
            $query->withAllTags($tagFilter['values']);
        } else {
            $query->withAnyTag($tagFilter['values']);
        }
    }

    /**
     * Apply category filter (maps to tag groups)
     */
    private function applyCategoryFilter(Builder $query, ?string $category): void
    {
        if (!$category) {
            return;
        }

        // Map categories to related tags
        $categoryTags = [
            'financial' => ['sale', 'transaction', 'financial', 'purchase_order', 'expense', 'credit', 'supplier_payment'],
            'inventory' => ['product', 'inventory', 'batch', 'stock_transfer', 'waste'],
            'customer' => ['customer', 'loyalty', 'credit'],
            'configuration' => ['store', 'tax', 'configuration', 'uom', 'category', 'brand'],
            'sale' => ['sale', 'transaction', 'refund'],
            'purchase_order' => ['purchase_order', 'procurement'],
            'expense' => ['expense', 'financial'],
            'product' => ['product', 'product_variant', 'inventory'],
            'supplier' => ['supplier', 'supplier_payment', 'procurement'],
        ];

        if (isset($categoryTags[$category])) {
            $query->withAnyTag($categoryTags[$category]);
        }
    }

    /**
     * Apply flag filters
     */
    private function applyFlagFilters(Builder $query, array $flags): void
    {
        if ($flags['critical_only']) {
            $query->critical();
        }

        if ($flags['financial_only']) {
            $query->financial();
        }

        if ($flags['bulk_only']) {
            $query->bulkOperations();
        }
    }

    /**
     * Apply search filter
     */
    private function applySearchFilter(Builder $query, ?string $search): void
    {
        if (empty($search)) {
            return;
        }

        $query->where(function ($q) use ($search) {
            $q->where('description', 'like', "%{$search}%")
                ->orWhere('user_name', 'like', "%{$search}%")
                ->orWhere('model_type', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%");
        });
    }

    /**
     * Group by date
     */
    private function groupByDate(Builder $query): array
    {
        return $query
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($item) => [
                'date' => \Carbon\Carbon::parse($item->date)->format('M d, Y'),
                'count' => $item->count,
            ])
            ->toArray();
    }

    /**
     * Group by model
     */
    private function groupByModel(Builder $query): array
    {
        return $query
            ->select('model_type', DB::raw('COUNT(*) as count'))
            ->groupBy('model_type')
            ->orderBy('count', 'desc')
            ->get()
            ->map(fn($item) => [
                'model' => class_basename($item->model_type),
                'full_model' => $item->model_type,
                'count' => $item->count,
            ])
            ->toArray();
    }

    /**
     * Group by user
     */
    private function groupByUser(Builder $query): array
    {
        return $query
            ->select('user_id', 'user_name', DB::raw('COUNT(*) as count'))
            ->groupBy('user_id', 'user_name')
            ->orderBy('count', 'desc')
            ->get()
            ->map(fn($item) => [
                'user_id' => $item->user_id,
                'user_name' => $item->user_name,
                'count' => $item->count,
            ])
            ->toArray();
    }

    /**
     * Group by action
     */
    private function groupByAction(Builder $query): array
    {
        return $query
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get()
            ->map(fn($item) => [
                'action' => $item->action,
                'count' => $item->count,
            ])
            ->toArray();
    }

    /**
     * Group by tag
     */
    private function groupByTag(Builder $query): array
    {
        // Get all audits with tags
        $audits = $query->whereNotNull('tags')->where('tags', '!=', '')->get();

        // Count tag occurrences
        $tagCounts = [];
        foreach ($audits as $audit) {
            foreach ($audit->tags_array as $tag) {
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }
        }

        // Sort by count
        arsort($tagCounts);

        return collect($tagCounts)
            ->map(fn($count, $tag) => [
                'tag' => $tag,
                'count' => $count,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get action breakdown
     */
    private function getActionBreakdown(Builder $query): array
    {
        return (clone $query)
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get()
            ->pluck('count', 'action')
            ->toArray();
    }

    /**
     * Get model breakdown
     */
    private function getModelBreakdown(Builder $query): array
    {
        return (clone $query)
            ->select('model_type', DB::raw('COUNT(*) as count'))
            ->groupBy('model_type')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->mapWithKeys(fn($item) => [
                class_basename($item->model_type) => $item->count
            ])
            ->toArray();
    }

    /**
     * Get user breakdown
     */
    private function getUserBreakdown(Builder $query): array
    {
        return (clone $query)
            ->select('user_name', DB::raw('COUNT(*) as count'))
            ->groupBy('user_name')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->pluck('count', 'user_name')
            ->toArray();
    }

    /**
     * Get date breakdown (last 7 days)
     */
    private function getDateBreakdown(Builder $query): array
    {
        return (clone $query)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->mapWithKeys(fn($item) => [
                \Carbon\Carbon::parse($item->date)->format('M d') => $item->count
            ])
            ->toArray();
    }

    /**
     * Generate cache key for statistics
     */
    public function getStatisticsCacheKey(array $filters): string
    {
        return 'audit_stats_' . md5(json_encode($filters));
    }
}
