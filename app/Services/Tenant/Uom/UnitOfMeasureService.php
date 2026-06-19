<?php

namespace App\Services\Tenant\Uom;

use App\Enums\Tenant\UomSourceType;
use App\Models\Tenant\UnitOfMeasure;
use App\Models\Tenant\UomConversion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitOfMeasureService
{
    /**
     * Get paginated list of units of measure with optional filters.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getList(array $filters = []): LengthAwarePaginator
    {
        $query = UnitOfMeasure::query();

        // Apply filters
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (isset($filters['is_base_unit'])) {
            $query->where('is_base_unit', $filters['is_base_unit']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Search by code or name
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Order by source type (system first), then by type, then by name
        $query->orderByRaw("FIELD(source_type, 'system', 'custom')")
            ->orderBy('type')
            ->orderBy('name');

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Get all UOMs grouped by type.
     *
     * @return array
     */
    public function getGroupedByType(): array
    {
        $uoms = UnitOfMeasure::active()
            ->orderBy('name')
            ->get()
            ->groupBy('type');

        return $uoms->toArray();
    }

    /**
     * Get a specific unit of measure with conversions.
     *
     * @param int $id
     * @return UnitOfMeasure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getById(int $id): UnitOfMeasure
    {
        return UnitOfMeasure::with([
            'conversionsFrom.toUom',
            'conversionsTo.fromUom',
        ])->findOrFail($id);
    }

    /**
     * Create a new custom unit of measure.
     *
     * @param array $data
     * @return UnitOfMeasure
     * @throws \Exception
     */
    public function create(array $data): UnitOfMeasure
    {
        try {
            DB::beginTransaction();

            // Ensure source_type is set to custom
            $data['source_type'] = UomSourceType::CUSTOM;
            $data['is_active'] = true;

            // If creating as base unit, validate no other base unit exists for this type
            if ($data['is_base_unit'] ?? false) {
                $existingBaseUnit = UnitOfMeasure::where('type', $data['type'])
                    ->where('is_base_unit', true)
                    ->exists();

                if ($existingBaseUnit) {
                    throw new \Exception("A base unit already exists for type: {$data['type']}");
                }
            }

            $uom = UnitOfMeasure::create($data);

            DB::commit();

            Log::info('Custom UOM created', [
                'tenant_id' => tenant()->id,
                'uom_id' => $uom->id,
                'code' => $uom->code,
            ]);

            return $uom->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create custom UOM', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Update a custom unit of measure.
     * Note: System UOMs cannot be updated.
     *
     * @param int $id
     * @param array $data
     * @return UnitOfMeasure
     * @throws \Exception
     */
    public function update(int $id, array $data): UnitOfMeasure
    {
        try {
            DB::beginTransaction();

            $uom = UnitOfMeasure::findOrFail($id);

            // Prevent updating system UOMs
            if ($uom->isSystem()) {
                throw new \Exception('System-defined units of measure cannot be updated.');
            }

            // If changing to base unit, validate
            if (isset($data['is_base_unit']) && $data['is_base_unit']) {
                $existingBaseUnit = UnitOfMeasure::where('type', $uom->type)
                    ->where('is_base_unit', true)
                    ->where('id', '!=', $id)
                    ->exists();

                if ($existingBaseUnit) {
                    throw new \Exception("A base unit already exists for type: {$uom->type}");
                }
            }

            // Don't allow changing source_type
            unset($data['source_type']);

            $uom->update($data);

            DB::commit();

            Log::info('Custom UOM updated', [
                'tenant_id' => tenant()->id,
                'uom_id' => $uom->id,
                'code' => $uom->code,
                'changes' => $data,
            ]);

            return $uom->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update custom UOM', [
                'tenant_id' => tenant()->id,
                'uom_id' => $id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Get conversion options for a specific UOM.
     *
     * @param int $uomId
     * @return Collection
     */
    public function getConversionOptions(int $uomId): Collection
    {
        $uom = UnitOfMeasure::findOrFail($uomId);

        // Get all UOMs of the same type (can only convert within same type)
        return UnitOfMeasure::where('type', $uom->type)
            ->where('id', '!=', $uomId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if a UOM can be safely deleted.
     * (Not implemented  but useful for future)
     *
     * @param int $id
     * @return array
     */
    public function canDelete(int $id): array
    {
        $uom = UnitOfMeasure::findOrFail($id);

        // System UOMs cannot be deleted
        if ($uom->isSystem()) {
            return [
                'can_delete' => false,
                'reason' => 'System-defined units cannot be deleted.',
            ];
        }

        // Check if UOM is used in products
        $usedInProducts = DB::table('products')
            ->where('base_uom_id', $id)
            ->exists();

        if ($usedInProducts) {
            return [
                'can_delete' => false,
                'reason' => 'This unit is currently used in products.',
            ];
        }

        // Check if UOM is used in product_uoms
        $usedInProductUoms = DB::table('product_uoms')
            ->where('uom_id', $id)
            ->exists();

        if ($usedInProductUoms) {
            return [
                'can_delete' => false,
                'reason' => 'This unit is currently configured in product UOMs.',
            ];
        }

        // Check if UOM is used in conversions
        $hasConversions = $uom->conversionsFrom()->exists() ||
            $uom->conversionsTo()->exists();

        if ($hasConversions) {
            return [
                'can_delete' => false,
                'reason' => 'This unit has existing conversions. Please remove conversions first.',
            ];
        }

        return [
            'can_delete' => true,
            'reason' => null,
        ];
    }

    /**
     * Set a UOM as base unit for its type.
     * Ensures only one base unit exists per type by removing base flag from others.
     *
     * @param int $id
     * @return UnitOfMeasure
     * @throws \Exception
     */
    public function setBaseUnit(int $id): UnitOfMeasure
    {
        try {
            DB::beginTransaction();

            $uom = UnitOfMeasure::findOrFail($id);

            // Check if it's already a base unit
            if ($uom->is_base_unit) {
                throw new \Exception('This unit is already a base unit.');
            }

            // Check if another base unit exists for this type
            $existingBaseUnit = UnitOfMeasure::where('type', $uom->type)
                ->where('is_base_unit', true)
                ->where('id', '!=', $id)
                ->first();

            // If another base unit exists, remove its base flag
            if ($existingBaseUnit) {
                $existingBaseUnit->update(['is_base_unit' => false]);
            }

            // Set this unit as base unit
            $uom->update(['is_base_unit' => true]);

            DB::commit();

            return $uom->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to set base unit flag', [
                'tenant_id' => tenant()->id,
                'uom_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Remove base unit flag from a UOM.
     *
     * @param int $id
     * @return UnitOfMeasure
     * @throws \Exception
     */
    public function removeBaseUnit(int $id): UnitOfMeasure
    {
        try {
            DB::beginTransaction();

            $uom = UnitOfMeasure::findOrFail($id);

            // Check if it's a base unit
            if (!$uom->is_base_unit) {
                throw new \Exception('This unit is not a base unit.');
            }

            // Remove base unit flag
            $uom->update(['is_base_unit' => false]);

            DB::commit();

            Log::info('Base unit flag removed from UOM', [
                'tenant_id' => tenant()->id,
                'uom_id' => $uom->id,
                'code' => $uom->code,
            ]);

            return $uom->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to remove base unit flag', [
                'tenant_id' => tenant()->id,
                'uom_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }


    /**
     * Create a new UOM conversion.
     *
     * @param array $data
     * @return UomConversion
     * @throws \Exception
     */
    public function createUomConversion(array $data): UomConversion
    {
        try {
            DB::beginTransaction();

            // Load the UOMs to validate
            $fromUom = UnitOfMeasure::findOrFail($data['from_uom_id']);
            $toUom = UnitOfMeasure::findOrFail($data['to_uom_id']);

            // Validate that UOMs are of the same type
            if ($fromUom->type !== $toUom->type) {
                throw new \Exception(
                    "Cannot create conversion between different types: {$fromUom->type} and {$toUom->type}"
                );
            }

            // Create the conversion
            $conversion = UomConversion::create($data);

            DB::commit();

            Log::info('UOM conversion created', [
                'tenant_id' => tenant()->id,
                'conversion_id' => $conversion->id,
                'from' => $fromUom->code,
                'to' => $toUom->code,
                'factor' => $data['conversion_factor'],
            ]);

            return $conversion->load(['fromUom', 'toUom']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create UOM conversion', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Update an existing UOM conversion.
     *
     * @param int $id
     * @param array $data
     * @return UomConversion
     * @throws \Exception
     */
    public function updateUomConversion(int $id, array $data): UomConversion
    {
        try {
            DB::beginTransaction();

            $conversion = UomConversion::with(['fromUom', 'toUom'])->findOrFail($id);

            $oldFactor = $conversion->conversion_factor;

            $conversion->update($data);

            DB::commit();

            Log::info('UOM conversion updated', [
                'tenant_id' => tenant()->id,
                'conversion_id' => $conversion->id,
                'from' => $conversion->fromUom->code,
                'to' => $conversion->toUom->code,
                'old_factor' => $oldFactor,
                'new_factor' => $data['conversion_factor'],
            ]);

            return $conversion->fresh(['fromUom', 'toUom']);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update UOM conversion', [
                'tenant_id' => tenant()->id,
                'conversion_id' => $id,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Delete a UOM conversion.
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteUomConversion(int $id): bool
    {
        try {
            DB::beginTransaction();

            $conversion = UomConversion::with(['fromUom', 'toUom'])->findOrFail($id);

            $conversion->delete();

            DB::commit();

            Log::info('UOM conversion deleted', [
                'tenant_id' => tenant()->id,
                'conversion_id' => $id,
                'from' => $conversion->fromUom->code,
                'to' => $conversion->toUom->code,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete UOM conversion', [
                'tenant_id' => tenant()->id,
                'conversion_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get conversion between two UOMs.
     *
     * @param int $fromUomId
     * @param int $toUomId
     * @return UomConversion|null
     */
    public function getConversion(int $fromUomId, int $toUomId): ?UomConversion
    {
        return UomConversion::with(['fromUom', 'toUom'])
            ->where(function ($query) use ($fromUomId, $toUomId) {
                $query->where('from_uom_id', $fromUomId)
                    ->where('to_uom_id', $toUomId);
            })
            ->orWhere(function ($query) use ($fromUomId, $toUomId) {
                $query->where('from_uom_id', $toUomId)
                    ->where('to_uom_id', $fromUomId);
            })
            ->first();
    }

    /**
     * Convert a quantity between two UOMs.
     *
     * @param float $quantity
     * @param int $fromUomId
     * @param int $toUomId
     * @return array
     * @throws \Exception
     */
    public function convert(float $quantity, int $fromUomId, int $toUomId): array
    {
        $fromUom = UnitOfMeasure::findOrFail($fromUomId);
        $toUom = UnitOfMeasure::findOrFail($toUomId);

        // Same UOM
        if ($fromUomId === $toUomId) {
            return [
                'original_quantity' => $quantity,
                'converted_quantity' => $quantity,
                'from_uom' => $fromUom,
                'to_uom' => $toUom,
                'conversion_factor' => 1,
            ];
        }

        // Use the model's convert method
        $convertedQuantity = $fromUom->convertTo($quantity, $toUom);

        // Get the conversion for factor display
        $conversion = $this->getConversion($fromUomId, $toUomId);
        $factor = null;

        if ($conversion) {
            $factor = $conversion->from_uom_id === $fromUomId
                ? $conversion->conversion_factor
                : $conversion->reverse_factor;
        }

        return [
            'original_quantity' => $quantity,
            'converted_quantity' => round($convertedQuantity, 6),
            'from_uom' => $fromUom,
            'to_uom' => $toUom,
            'conversion_factor' => $factor,
        ];
    }
}
