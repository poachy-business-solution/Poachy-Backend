<?php

namespace App\Http\Resources\Tenant\Audit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // User Information
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user_name,
            ],

            // Action Details
            'action' => $this->action,
            'description' => $this->description,

            // Model Information
            'model' => [
                'type' => $this->model_name, // Uses accessor from model
                'full_type' => $this->model_type,
                'id' => $this->model_id,
            ],

            // Changes
            'changes' => $this->getFormattedChanges(),

            // Metadata
            'tags' => $this->tags_array, // Uses accessor from model
            'ip_address' => $this->ip_address,

            // Flags
            'is_creation' => $this->isCreation(),
            'is_update' => $this->isUpdate(),
            'is_deletion' => $this->isDeletion(),
            'is_bulk' => $this->isBulkOperation(),
            'is_financial' => $this->isFinancial(),
            'is_critical' => $this->hasTag('critical'),

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'created_at_human' => $this->created_at->diffForHumans(),
            'created_at_formatted' => $this->created_at->format('M d, Y h:i A'),
        ];
    }

    /**
     * Format changes in a user-friendly way
     */
    private function getFormattedChanges(): array
    {
        if ($this->isCreation()) {
            return [
                'type' => 'creation',
                'new_values' => $this->formatValues($this->new_values),
            ];
        }

        if ($this->isDeletion()) {
            return [
                'type' => 'deletion',
                'old_values' => $this->formatValues($this->old_values),
            ];
        }

        if ($this->isUpdate() && !empty($this->old_values) && !empty($this->new_values)) {
            $differences = [];

            foreach ($this->new_values as $key => $newValue) {
                $oldValue = $this->old_values[$key] ?? null;

                if ($oldValue !== $newValue) {
                    $differences[$key] = [
                        'field' => $this->formatFieldName($key),
                        'from' => $this->formatValue($key, $oldValue),
                        'to' => $this->formatValue($key, $newValue),
                    ];
                }
            }

            return [
                'type' => 'update',
                'fields_changed' => count($differences),
                'differences' => $differences,
            ];
        }

        // For other types (aggregated, bulk, etc.)
        return [
            'type' => 'other',
            'data' => $this->formatValues($this->new_values),
        ];
    }

    /**
     * Format all values in an array
     */
    private function formatValues(?array $values): array
    {
        if (empty($values)) {
            return [];
        }

        $formatted = [];
        foreach ($values as $key => $value) {
            $formatted[$key] = [
                'field' => $this->formatFieldName($key),
                'value' => $this->formatValue($key, $value),
            ];
        }

        return $formatted;
    }

    /**
     * Format a field name to be human-readable
     */
    private function formatFieldName(string $field): string
    {
        // Convert snake_case to Title Case
        return str($field)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    /**
     * Format a value based on field type
     */
    private function formatValue(string $field, mixed $value): mixed
    {
        // Handle null
        if ($value === null) {
            return 'N/A';
        }

        // Handle boolean
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        // Handle arrays/JSON
        if (is_array($value)) {
            return $value;
        }

        // Handle dates (fields ending with _at or _date)
        if (str_ends_with($field, '_at') || str_ends_with($field, '_date')) {
            try {
                return \Carbon\Carbon::parse($value)->format('M d, Y h:i A');
            } catch (\Exception $e) {
                return $value;
            }
        }

        // Handle money fields
        if (
            str_contains($field, 'price') ||
            str_contains($field, 'amount') ||
            str_contains($field, 'cost') ||
            str_contains($field, 'total') ||
            str_contains($field, 'subtotal')
        ) {
            return 'KES ' . number_format((float) $value, 2);
        }

        // Handle quantity fields
        if (str_contains($field, 'quantity')) {
            return number_format((float) $value, 4);
        }

        // Default
        return $value;
    }

    /**
     * Additional data to include when collection is wrapped
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'timezone' => config('app.timezone'),
            ],
        ];
    }
}
