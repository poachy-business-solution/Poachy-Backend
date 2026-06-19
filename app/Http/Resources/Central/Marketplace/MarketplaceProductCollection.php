<?php

namespace App\Http\Resources\Central\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Wraps a paginated collection of MarketplaceProductResource items.
*/
class MarketplaceProductCollection extends ResourceCollection
{
    public $collects = MarketplaceProductResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}