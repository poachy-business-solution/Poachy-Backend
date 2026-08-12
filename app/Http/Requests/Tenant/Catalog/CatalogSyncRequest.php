<?php

namespace App\Http\Requests\Tenant\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class CatalogSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'updated_since' => ['nullable', 'date'],
            'include_deleted' => ['nullable', 'boolean'],
        ];
    }

    public function updatedSince(): ?string
    {
        return $this->validated('updated_since');
    }

    public function includeDeleted(): bool
    {
        return $this->boolean('include_deleted', false);
    }
}
