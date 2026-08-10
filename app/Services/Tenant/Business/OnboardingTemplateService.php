<?php

namespace App\Services\Tenant\Business;

use App\Models\BusinessCategory;
use App\Models\BusinessDetail;
use App\Models\BusinessType;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class OnboardingTemplateService
{
    public function forCurrentTenant(): array
    {
        $tenantId = tenancy()->initialized ? tenant()->id : null;

        return $tenantId ? $this->forTenant((string) $tenantId) : $this->fallbackTemplate();
    }

    public function forTenant(string $tenantId): array
    {
        $businessDetail = BusinessDetail::on('central')
            ->with(['businessType', 'businessCategory'])
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $businessDetail) {
            return $this->fallbackTemplate();
        }

        return $this->forSlugs(
            businessCategorySlug: $businessDetail->businessCategory?->slug,
            businessTypeSlug: $businessDetail->businessType?->slug,
            businessCategory: $businessDetail->businessCategory ? [
                'id' => $businessDetail->businessCategory->id,
                'name' => $businessDetail->businessCategory->name,
                'slug' => $businessDetail->businessCategory->slug,
            ] : null,
            businessType: $businessDetail->businessType ? [
                'id' => $businessDetail->businessType->id,
                'name' => $businessDetail->businessType->name,
                'slug' => $businessDetail->businessType->slug,
            ] : null,
        );
    }

    public function forBusinessSelection(?int $businessCategoryId, ?int $businessTypeId): array
    {
        $category = $businessCategoryId
            ? BusinessCategory::on('central')->with('businessType')->find($businessCategoryId)
            : null;

        $type = $category?->businessType
            ?? ($businessTypeId ? BusinessType::on('central')->find($businessTypeId) : null);

        return $this->forSlugs(
            businessCategorySlug: $category?->slug,
            businessTypeSlug: $type?->slug,
            businessCategory: $category ? [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ] : null,
            businessType: $type ? [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
            ] : null,
        );
    }

    public function forSlugs(
        ?string $businessCategorySlug,
        ?string $businessTypeSlug = null,
        ?array $businessCategory = null,
        ?array $businessType = null,
    ): array {
        $templateKey = $this->templateKeyForSlugs($businessCategorySlug, $businessTypeSlug);
        $template = $this->template($templateKey);

        return $this->normalizeTemplate(
            templateKey: $templateKey,
            template: $template,
            source: $this->sourceForSlugs($businessCategorySlug, $businessTypeSlug),
            businessCategory: $businessCategory,
            businessType: $businessType,
        );
    }

    public function fallbackTemplate(): array
    {
        $templateKey = (string) config('onboarding_templates.default', 'general-retail');

        return $this->normalizeTemplate(
            templateKey: $templateKey,
            template: $this->template($templateKey),
            source: 'default',
            businessCategory: null,
            businessType: null,
        );
    }

    private function templateKeyForSlugs(?string $businessCategorySlug, ?string $businessTypeSlug): string
    {
        $categoryTemplates = config('onboarding_templates.business_categories', []);
        $typeTemplates = config('onboarding_templates.business_types', []);

        return ($businessCategorySlug ? Arr::get($categoryTemplates, $businessCategorySlug) : null)
            ?? ($businessTypeSlug ? Arr::get($typeTemplates, $businessTypeSlug) : null)
            ?? (string) config('onboarding_templates.default', 'general-retail');
    }

    private function sourceForSlugs(?string $businessCategorySlug, ?string $businessTypeSlug): string
    {
        if ($businessCategorySlug && Arr::has(config('onboarding_templates.business_categories', []), $businessCategorySlug)) {
            return 'business_category';
        }

        if ($businessTypeSlug && Arr::has(config('onboarding_templates.business_types', []), $businessTypeSlug)) {
            return 'business_type';
        }

        return 'default';
    }

    private function template(string $templateKey): array
    {
        $template = config("onboarding_templates.templates.{$templateKey}", []);
        $parentKey = Arr::get($template, 'extends');

        if (! $parentKey) {
            return $template;
        }

        $parent = $this->template($parentKey);

        return array_replace_recursive($parent, Arr::except($template, ['extends']));
    }

    private function normalizeTemplate(
        string $templateKey,
        array $template,
        string $source,
        ?array $businessCategory,
        ?array $businessType,
    ): array {
        return [
            'template_key' => $templateKey,
            'template_name' => Arr::get($template, 'name', Str::headline($templateKey)),
            'description' => Arr::get($template, 'description'),
            'source' => $source,
            'business_type' => $businessType,
            'business_category' => $businessCategory,
            'categories' => $this->normalizeCategories(Arr::get($template, 'categories', [])),
            'units_of_measure' => $this->normalizeUnits(Arr::get($template, 'units_of_measure', [])),
        ];
    }

    private function normalizeCategories(array $categories): array
    {
        return array_values(array_map(function (array $category): array {
            $slug = Arr::get($category, 'slug') ?: Arr::get($category, 'key') ?: Str::slug($category['name']);

            return [
                'key' => Arr::get($category, 'key', $slug),
                'name' => $category['name'],
                'slug' => $slug,
                'description' => Arr::get($category, 'description'),
                'display_order' => (int) Arr::get($category, 'display_order', 0),
                'children' => array_values(array_map(function (array $child): array {
                    return [
                        'name' => $child['name'],
                        'slug' => Arr::get($child, 'slug', Str::slug($child['name'])),
                        'description' => Arr::get($child, 'description'),
                        'display_order' => (int) Arr::get($child, 'display_order', 0),
                    ];
                }, Arr::get($category, 'children', []))),
            ];
        }, $categories));
    }

    private function normalizeUnits(array $units): array
    {
        return array_values(array_map(fn (array $unit): array => [
            'code' => $unit['code'],
            'name' => $unit['name'],
            'type' => $unit['type'],
            'source_type' => Arr::get($unit, 'source_type', 'system'),
            'is_base_unit' => (bool) Arr::get($unit, 'is_base_unit', false),
            'is_active' => (bool) Arr::get($unit, 'is_active', true),
            'description' => Arr::get($unit, 'description'),
        ], $units));
    }
}
