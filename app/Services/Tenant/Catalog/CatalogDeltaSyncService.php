<?php

namespace App\Services\Tenant\Catalog;

use App\Models\Tenant\Coupon;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBarcode;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\ProductScaleBarcodeFormat;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Promotion;
use App\Models\Tenant\StoreProduct;
use App\Models\Tenant\TaxRate;
use App\Models\Tenant\UnitOfMeasure;
use App\Services\Tenant\Product\ProductBarcodeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class CatalogDeltaSyncService
{
    /**
     * @return array<string, mixed>
     */
    public function sync(?string $updatedSince = null, bool $includeDeleted = false): array
    {
        $startedAt = now()->toImmutable();
        $since = $updatedSince ? CarbonImmutable::parse($updatedSince) : null;

        $entities = [
            'categories' => $this->fetch(ProductCategory::class, $since, $startedAt, $includeDeleted, fn (ProductCategory $category) => $this->category($category)),
            'brands' => $this->fetch(ProductBrand::class, $since, $startedAt, $includeDeleted, fn (ProductBrand $brand) => $this->brand($brand)),
            'products' => $this->fetch(Product::class, $since, $startedAt, $includeDeleted, fn (Product $product) => $this->product($product)),
            'variants' => $this->fetch(ProductVariant::class, $since, $startedAt, $includeDeleted, fn (ProductVariant $variant) => $this->variant($variant)),
            'barcodes' => $this->fetch(ProductBarcode::class, $since, $startedAt, $includeDeleted, fn (ProductBarcode $barcode) => $this->barcode($barcode)),
            'scale_barcode_formats' => $this->fetch(ProductScaleBarcodeFormat::class, $since, $startedAt, $includeDeleted, fn (ProductScaleBarcodeFormat $format) => $this->scaleBarcodeFormat($format)),
            'prices' => $this->fetch(StoreProduct::class, $since, $startedAt, $includeDeleted, fn (StoreProduct $price) => $this->price($price)),
            'product_uoms' => $this->fetch(ProductUom::class, $since, $startedAt, $includeDeleted, fn (ProductUom $productUom) => $this->productUom($productUom)),
            'uoms' => $this->fetch(UnitOfMeasure::class, $since, $startedAt, $includeDeleted, fn (UnitOfMeasure $uom) => $this->uom($uom)),
            'tax_rates' => $this->fetch(TaxRate::class, $since, $startedAt, $includeDeleted, fn (TaxRate $taxRate) => $this->taxRate($taxRate)),
            'customers' => $this->fetch(Customer::class, $since, $startedAt, $includeDeleted, fn (Customer $customer) => $this->customer($customer)),
            'promotions' => $this->fetch(Promotion::class, $since, $startedAt, $includeDeleted, fn (Promotion $promotion) => $this->promotion($promotion)),
            'coupons' => $this->fetch(Coupon::class, $since, $startedAt, $includeDeleted, fn (Coupon $coupon) => $this->coupon($coupon)),
        ];

        $entityCounts = [];

        foreach ($entities as $key => $records) {
            $entityCounts[$key] = count($records);
        }

        return [
            'sync_started_at' => $startedAt->toISOString(),
            'updated_since' => $since?->toISOString(),
            'next_cursor' => $startedAt->toISOString(),
            'include_deleted' => $includeDeleted,
            'entity_counts' => $entityCounts,
            'entities' => $entities,
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  callable(TModel): array<string, mixed>  $serializer
     * @return array<int, array<string, mixed>>
     */
    protected function fetch(
        string $modelClass,
        ?CarbonImmutable $since,
        CarbonImmutable $startedAt,
        bool $includeDeleted,
        callable $serializer
    ): array {
        /** @var TModel $model */
        $model = new $modelClass;
        $timestampColumn = $this->timestampColumn($model);
        $hasSoftDeletes = $this->usesSoftDeletes($modelClass);

        /** @var Builder<TModel> $query */
        $query = $modelClass::query();

        if ($includeDeleted && $hasSoftDeletes) {
            $query->withTrashed();
        }

        $query->where($timestampColumn, '<=', $startedAt);

        if ($since) {
            $query->where(function (Builder $query) use ($timestampColumn, $since, $includeDeleted, $hasSoftDeletes) {
                $query->where($timestampColumn, '>', $since);

                if ($includeDeleted && $hasSoftDeletes) {
                    $query->orWhere('deleted_at', '>', $since);
                }
            });
        }

        $records = $query
            ->orderBy($timestampColumn)
            ->orderBy($model->getKeyName())
            ->get();

        return $records
            ->map($serializer)
            ->all();
    }

    protected function timestampColumn(Model $model): string
    {
        return $model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'updated_at')
            ? 'updated_at'
            : 'created_at';
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    protected function usesSoftDeletes(string $modelClass): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function category(ProductCategory $category): array
    {
        return $this->base($category) + [
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'display_order' => $category->display_order,
            'is_active' => $category->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function brand(ProductBrand $brand): array
    {
        return $this->base($brand) + [
            'name' => $brand->name,
            'slug' => $brand->slug,
            'description' => $brand->description,
            'logo_url' => $brand->logo_url,
            'is_active' => $brand->is_active,
            'is_featured' => $brand->is_featured,
            'display_order' => $brand->display_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function product(Product $product): array
    {
        return $this->base($product) + [
            'uuid' => $product->uuid,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'supplier_id' => $product->supplier_id,
            'product_type' => $this->enumValue($product->product_type),
            'stock_status' => $this->enumValue($product->stock_status),
            'is_weighed' => $product->is_weighed,
            'requires_batch_tracking' => $product->requires_batch_tracking,
            'requires_serial_tracking' => $product->requires_serial_tracking,
            'base_selling_price' => $this->decimal($product->base_selling_price),
            'online_price' => $this->decimal($product->online_price),
            'tax_rate_id' => $product->tax_rate_id,
            'base_uom_id' => $product->base_uom_id,
            'reorder_level' => $this->decimal($product->reorder_level),
            'shelf_life_days' => $product->shelf_life_days,
            'primary_image' => $product->primary_image,
            'secondary_images' => $product->secondary_images ?? [],
            'is_active' => $product->is_active,
            'is_featured' => $product->is_featured,
            'is_available_online' => $product->is_available_online,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function variant(ProductVariant $variant): array
    {
        return $this->base($variant) + [
            'uuid' => $variant->uuid,
            'product_id' => $variant->product_id,
            'variant_name' => $variant->variant_name,
            'sku' => $variant->sku,
            'attributes' => $variant->attributes ?? [],
            'uom_id' => $variant->uom_id,
            'uom_quantity' => $this->decimal($variant->uom_quantity),
            'quantity_in_base_uom' => $this->decimal($variant->quantity_in_base_uom),
            'base_selling_price_adjustment' => $this->decimal($variant->base_selling_price_adjustment),
            'variant_price' => $this->decimal($variant->variant_price),
            'online_price' => $this->decimal($variant->online_price),
            'stock_status' => $this->enumValue($variant->stock_status),
            'reorder_level' => $this->decimal($variant->reorder_level),
            'shelf_life_days' => $variant->shelf_life_days,
            'is_active' => $variant->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function barcode(ProductBarcode $barcode): array
    {
        $barcode->loadMissing('barcodeable');

        return $this->base($barcode) + [
            'barcodeable_type' => $barcode->barcodeable_type,
            'barcodeable_id' => $barcode->barcodeable_id,
            'barcode' => $barcode->barcode,
            'barcode_type' => $barcode->barcode_type,
            'is_primary' => $barcode->is_primary,
            'is_active' => $barcode->is_active,
            'supplier_id' => $barcode->supplier_id,
            'region' => $barcode->region,
            'store_id' => $barcode->store_id,
            'valid_from' => $barcode->valid_from?->format('Y-m-d'),
            'valid_until' => $barcode->valid_until?->format('Y-m-d'),
            'source' => $barcode->source,
            'metadata' => $barcode->metadata ?? [],
            'notes' => $barcode->notes,
            'sale_line' => app(ProductBarcodeService::class)->saleLine($barcode),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function price(StoreProduct $price): array
    {
        return $this->base($price) + [
            'store_id' => $price->store_id,
            'product_id' => $price->product_id,
            'product_variant_id' => $price->product_variant_id,
            'store_selling_price' => $this->decimal($price->store_selling_price),
            'is_available' => $price->is_available,
            'min_stock_level' => $price->min_stock_level,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function scaleBarcodeFormat(ProductScaleBarcodeFormat $format): array
    {
        return $this->base($format) + [
            'name' => $format->name,
            'prefix' => $format->prefix,
            'length' => $format->length,
            'product_code_start' => $format->product_code_start,
            'product_code_length' => $format->product_code_length,
            'value_start' => $format->value_start,
            'value_length' => $format->value_length,
            'value_type' => $format->value_type,
            'decimal_places' => $format->decimal_places,
            'checksum' => $format->checksum,
            'store_id' => $format->store_id,
            'is_active' => $format->is_active,
            'priority' => $format->priority,
            'metadata' => $format->metadata ?? [],
            'notes' => $format->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function productUom(ProductUom $productUom): array
    {
        return $this->base($productUom) + [
            'product_id' => $productUom->product_id,
            'uom_id' => $productUom->uom_id,
            'is_base_uom' => $productUom->is_base_uom,
            'is_purchase_uom' => $productUom->is_purchase_uom,
            'is_sales_uom' => $productUom->is_sales_uom,
            'is_inventory_uom' => $productUom->is_inventory_uom,
            'conversion_to_base' => $this->decimal($productUom->conversion_to_base),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function uom(UnitOfMeasure $uom): array
    {
        return $this->base($uom) + [
            'code' => $uom->code,
            'name' => $uom->name,
            'type' => $uom->type,
            'source_type' => $this->enumValue($uom->source_type),
            'is_base_unit' => $uom->is_base_unit,
            'is_active' => $uom->is_active,
            'description' => $uom->description,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function taxRate(TaxRate $taxRate): array
    {
        return $this->base($taxRate) + [
            'tax_name' => $taxRate->tax_name,
            'rate' => $this->decimal($taxRate->rate),
            'effective_from' => $taxRate->effective_from?->format('Y-m-d'),
            'effective_until' => $taxRate->effective_until?->format('Y-m-d'),
            'is_active' => $taxRate->is_active,
            'is_default' => $taxRate->is_default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function customer(Customer $customer): array
    {
        return $this->base($customer) + [
            'customer_number' => $customer->customer_number,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'customer_type' => $this->enumValue($customer->customer_type),
            'loyalty_points' => $this->decimal($customer->loyalty_points),
            'credit_limit' => $this->decimal($customer->credit_limit),
            'current_debt' => $this->decimal($customer->current_debt),
            'store_credit_balance' => $this->decimal($customer->store_credit_balance),
            'preferred_store_id' => $customer->preferred_store_id,
            'is_active' => $customer->is_active,
            'accepts_marketing' => $customer->accepts_marketing,
            'registered_at' => $customer->registered_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function promotion(Promotion $promotion): array
    {
        return $this->base($promotion) + [
            'name' => $promotion->name,
            'code' => $promotion->code,
            'description' => $promotion->description,
            'promotion_type' => $this->enumValue($promotion->promotion_type),
            'discount_value' => $this->decimal($promotion->discount_value),
            'buy_quantity' => $promotion->buy_quantity,
            'get_quantity' => $promotion->get_quantity,
            'get_items_free' => $promotion->get_items_free,
            'get_items_discount_percentage' => $this->decimal($promotion->get_items_discount_percentage),
            'min_purchase_amount' => $this->decimal($promotion->min_purchase_amount),
            'max_discount_amount' => $this->decimal($promotion->max_discount_amount),
            'max_uses_per_customer' => $promotion->max_uses_per_customer,
            'total_usage_limit' => $promotion->total_usage_limit,
            'total_usage_count' => $promotion->total_usage_count,
            'start_date' => $promotion->start_date?->toISOString(),
            'end_date' => $promotion->end_date?->toISOString(),
            'active_days' => $promotion->active_days ?? [],
            'active_time_start' => $promotion->active_time_start?->format('H:i:s'),
            'active_time_end' => $promotion->active_time_end?->format('H:i:s'),
            'applicable_store_ids' => $promotion->applicable_store_ids ?? [],
            'applicable_customer_group_ids' => $promotion->applicable_customer_group_ids ?? [],
            'applicable_to' => $this->enumValue($promotion->applicable_to),
            'show_on_website' => $promotion->show_on_website,
            'show_in_pos' => $promotion->show_in_pos,
            'display_priority' => $promotion->display_priority,
            'is_active' => $promotion->is_active,
            'auto_apply' => $promotion->auto_apply,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function coupon(Coupon $coupon): array
    {
        return $this->base($coupon) + [
            'code' => $coupon->code,
            'description' => $coupon->description,
            'discount_type' => $this->enumValue($coupon->discount_type),
            'discount_value' => $this->decimal($coupon->discount_value),
            'min_purchase_amount' => $this->decimal($coupon->min_purchase_amount),
            'max_discount_amount' => $this->decimal($coupon->max_discount_amount),
            'usage_limit' => $coupon->usage_limit,
            'usage_count' => $coupon->usage_count,
            'usage_limit_per_customer' => $coupon->usage_limit_per_customer,
            'valid_from' => $coupon->valid_from?->format('Y-m-d'),
            'valid_until' => $coupon->valid_until?->format('Y-m-d'),
            'applicable_to' => $this->enumValue($coupon->applicable_to),
            'is_active' => $coupon->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function base(Model $model): array
    {
        $updatedAt = $model->getAttribute('updated_at') ?? $model->getAttribute('created_at');
        $deletedAt = $model->getAttribute('deleted_at');
        $syncedAt = Collection::make([$updatedAt, $deletedAt])->filter()->max();

        return [
            'id' => $model->getKey(),
            'deleted' => $deletedAt !== null,
            'synced_at' => $syncedAt?->toISOString(),
            'created_at' => $model->getAttribute('created_at')?->toISOString(),
            'updated_at' => $updatedAt?->toISOString(),
            'deleted_at' => $deletedAt?->toISOString(),
        ];
    }

    protected function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    protected function decimal(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
