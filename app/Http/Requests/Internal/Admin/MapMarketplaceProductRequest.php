<?php

namespace App\Http\Requests\Internal\Admin;

/** Put a listing under an existing item, or under a new item with this name. */
class MapMarketplaceProductRequest extends InternalAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item_id' => ['required_without:item_name', 'nullable', 'integer', 'exists:central.marketplace_items,id'],
            'item_name' => ['required_without:item_id', 'nullable', 'string', 'min:2', 'max:160'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
