<?php

namespace App\Services\Tenant\Product;

use App\Models\Tenant\ProductScaleBarcodeFormat;
use Illuminate\Database\Eloquent\Collection;

class ProductScaleBarcodeFormatService
{
    /**
     * @return Collection<int, ProductScaleBarcodeFormat>
     */
    public function list(array $filters = []): Collection
    {
        return ProductScaleBarcodeFormat::query()
            ->when(array_key_exists('is_active', $filters), fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when(array_key_exists('store_id', $filters), fn ($query) => $query->where('store_id', $filters['store_id']))
            ->orderByRaw('store_id IS NULL')
            ->orderByDesc('priority')
            ->orderBy('prefix')
            ->get();
    }

    public function create(array $data): ProductScaleBarcodeFormat
    {
        return ProductScaleBarcodeFormat::create($data);
    }

    public function update(ProductScaleBarcodeFormat $format, array $data): ProductScaleBarcodeFormat
    {
        $format->update($data);

        return $format->refresh();
    }

    public function deactivate(ProductScaleBarcodeFormat $format): ProductScaleBarcodeFormat
    {
        $format->forceFill(['is_active' => false])->save();

        return $format->refresh();
    }
}
