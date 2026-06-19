<?php

namespace App\Http\Requests\Tenant\Audit;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only owners and managers can view audit logs
        return $this->user()->hasAnyRole(['owner', 'manager']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Pagination
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],

            // Date Range Filters
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],

            // Model Filters
            'model_type' => ['nullable', 'string', 'max:100'],
            'model_id' => ['nullable', 'integer', 'min:1'],

            // Action Filters
            'action' => ['nullable', 'string', 'in:created,updated,deleted,restored,approved,rejected,cancelled,completed,soft_deleted'],
            'actions' => ['nullable', 'array'],
            'actions.*' => ['string', 'in:created,updated,deleted,restored,approved,rejected,cancelled,completed,soft_deleted'],

            // User Filters
            'user_id' => ['nullable', 'integer', 'exists:users,id'],

            // Tag Filters
            'tag' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'tag_match' => ['nullable', 'string', 'in:any,all'], // Match any tag or all tags

            // Category Filters (based on tags)
            'category' => ['nullable', 'string', 'in:financial,inventory,customer,configuration,sale,purchase_order,expense,product,supplier'],

            // Priority Filters
            'critical_only' => ['nullable', 'boolean'],
            'financial_only' => ['nullable', 'boolean'],
            'bulk_only' => ['nullable', 'boolean'],

            // Search
            'search' => ['nullable', 'string', 'max:255'],

            // Sorting
            'sort_by' => ['nullable', 'string', 'in:created_at,user_name,action,model_type'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],

            // Grouping
            'group_by' => ['nullable', 'string', 'in:date,model,user,action,tag'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'date_from' => 'start date',
            'date_to' => 'end date',
            'per_page' => 'items per page',
            'model_type' => 'model',
            'tag_match' => 'tag matching mode',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'date_from.before_or_equal' => 'Start date must be before or equal to end date.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'actions.*.in' => 'Invalid action type provided.',
            'tags.*.max' => 'Tag name must not exceed 50 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert single action to actions array
        if ($this->has('action') && !$this->has('actions')) {
            $this->merge([
                'actions' => [$this->action],
            ]);
        }

        // Convert single tag to tags array
        if ($this->has('tag') && !$this->has('tags')) {
            $this->merge([
                'tags' => [$this->tag],
            ]);
        }

        // Set defaults
        $this->merge([
            'per_page' => $this->per_page ?? 20,
            'sort_by' => $this->sort_by ?? 'created_at',
            'sort_order' => $this->sort_order ?? 'desc',
            'tag_match' => $this->tag_match ?? 'any',
        ]);
    }

    /**
     * Get validated filters in a structured format
     */
    public function getFilters(): array
    {
        $validated = $this->validated();

        return [
            'pagination' => [
                'per_page' => $validated['per_page'],
                'page' => $validated['page'] ?? 1,
            ],
            'date_range' => [
                'from' => $validated['date_from'] ?? null,
                'to' => $validated['date_to'] ?? null,
            ],
            'model' => [
                'type' => $validated['model_type'] ?? null,
                'id' => $validated['model_id'] ?? null,
            ],
            'actions' => $validated['actions'] ?? [],
            'user_id' => $validated['user_id'] ?? null,
            'tags' => [
                'values' => $validated['tags'] ?? [],
                'match' => $validated['tag_match'],
            ],
            'category' => $validated['category'] ?? null,
            'flags' => [
                'critical_only' => $validated['critical_only'] ?? false,
                'financial_only' => $validated['financial_only'] ?? false,
                'bulk_only' => $validated['bulk_only'] ?? false,
            ],
            'search' => $validated['search'] ?? null,
            'sort' => [
                'by' => $validated['sort_by'],
                'order' => $validated['sort_order'],
            ],
            'group_by' => $validated['group_by'] ?? null,
        ];
    }
}
