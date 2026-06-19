<?php

namespace App\Services\Tenant\Business;

use App\Models\BusinessCategory;
use App\Models\BusinessType;

class BusinessHelperService
{
    /**
     * Get all active business types with their categories.
     */
    public function getBusinessTypesWithCategories()
    {
        return BusinessType::with('activeCategories')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                    'description' => $type->description,
                    'categories' => $type->activeCategories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'slug' => $category->slug,
                            'description' => $category->description,
                        ];
                    }),
                ];
            });
    }

    /**
     * Get all active business types.
     */
    public function getBusinessTypes()
    {
        return BusinessType::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'slug' => $type->slug,
                    'description' => $type->description,
                ];
            });
    }

    /**
     * Get categories for a specific business type.
     */
    public function getCategoriesForType(int $businessTypeId)
    {
        return BusinessCategory::where('business_type_id', $businessTypeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                ];
            });
    }
}
