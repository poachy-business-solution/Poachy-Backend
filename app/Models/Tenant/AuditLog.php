<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    public $timestamps = false; // We only use created_at

    protected $fillable = [
        'user_id',
        'user_name',
        'ip_address',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'description',
        'tags',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // ========================================
    // Relationships
    // ========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditable()
    {
        return $this->morphTo('auditable', 'model_type', 'model_id');
    }

    // ========================================
    // Scopes
    // ========================================

    public function scopeForModel($query, string $modelClass)
    {
        return $query->where('model_type', $modelClass);
    }

    public function scopeForModelInstance($query, Model $model)
    {
        return $query->where('model_type', get_class($model))
            ->where('model_id', $model->id);
    }

    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeActions($query, array $actions)
    {
        return $query->whereIn('action', $actions);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithTag($query, string $tag)
    {
        return $query->whereRaw("FIND_IN_SET(?, tags) > 0", [$tag]);
    }

    public function scopeWithAnyTag($query, array $tags)
    {
        return $query->where(function ($q) use ($tags) {
            foreach ($tags as $tag) {
                $q->orWhereRaw("FIND_IN_SET(?, tags) > 0", [$tag]);
            }
        });
    }

    public function scopeWithAllTags($query, array $tags)
    {
        foreach ($tags as $tag) {
            $query->whereRaw("FIND_IN_SET(?, tags) > 0", [$tag]);
        }
        return $query;
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeFinancial($query)
    {
        return $query->withAnyTag(['financial', 'transaction', 'sale', 'purchase_order', 'expense']);
    }

    public function scopeCritical($query)
    {
        return $query->withTag('critical');
    }

    public function scopeBulkOperations($query)
    {
        return $query->withTag('bulk');
    }

    // ========================================
    // Accessors
    // ========================================

    public function getTagsArrayAttribute(): array
    {
        if (empty($this->tags)) {
            return [];
        }

        return explode(',', $this->tags);
    }

    public function getModelNameAttribute(): string
    {
        return class_basename($this->model_type);
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags_array);
    }

    public function getChangedFieldsAttribute(): array
    {
        if (empty($this->old_values) || empty($this->new_values)) {
            return [];
        }

        $oldKeys = array_keys($this->old_values);
        $newKeys = array_keys($this->new_values);

        return array_unique(array_merge($oldKeys, $newKeys));
    }

    public function getValueDifferencesAttribute(): array
    {
        if (empty($this->old_values) || empty($this->new_values)) {
            return [];
        }

        $differences = [];

        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;

            if ($oldValue !== $newValue) {
                $differences[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $differences;
    }

    // ========================================
    // Helper Methods
    // ========================================

    public function isCreation(): bool
    {
        return $this->action === 'created';
    }

    public function isUpdate(): bool
    {
        return $this->action === 'updated';
    }

    public function isDeletion(): bool
    {
        return in_array($this->action, ['deleted', 'soft_deleted']);
    }

    public function isRestoration(): bool
    {
        return $this->action === 'restored';
    }

    public function isBulkOperation(): bool
    {
        return $this->hasTag('bulk') || str_starts_with($this->action, 'bulk_');
    }

    public function isAggregated(): bool
    {
        return $this->hasTag('aggregated');
    }

    public function isFinancial(): bool
    {
        return $this->hasTag('financial') || $this->hasTag('transaction');
    }

    public function toDisplayArray(): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user_name,
            'action' => $this->action,
            'model' => $this->model_name,
            'model_id' => $this->model_id,
            'description' => $this->description,
            'tags' => $this->tags_array,
            'created_at' => $this->created_at->toISOString(),
            'ip_address' => $this->ip_address,
        ];
    }
}
