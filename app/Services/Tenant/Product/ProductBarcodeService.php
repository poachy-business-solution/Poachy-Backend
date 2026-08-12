<?php

namespace App\Services\Tenant\Product;

use App\Exceptions\Tenant\ProductBarcodeLookupConflictException;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBarcode;
use App\Models\Tenant\ProductScaleBarcodeFormat;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\StoreProduct;
use App\Models\Tenant\UnitOfMeasure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ProductBarcodeService
{
    /**
     * @return EloquentCollection<int, ProductBarcode>
     */
    public function listFor(Model $entity): EloquentCollection
    {
        return $this->barcodeRelation($entity)
            ->with(['supplier:id,name', 'store:id,name'])
            ->orderByDesc('is_primary')
            ->orderBy('barcode')
            ->get();
    }

    public function createFor(Model $entity, array $data): ProductBarcode
    {
        $data['barcode'] = ProductBarcode::normalizeBarcode($data['barcode']);
        $data['is_primary'] = $data['is_primary'] ?? false;
        $data['is_active'] = $data['is_active'] ?? true;

        $this->ensureUniqueActiveBarcode($data);

        if ($data['is_primary']) {
            $this->barcodeRelation($entity)->update(['is_primary' => false]);
        }

        return $this->barcodeRelation($entity)->create($data)->load('barcodeable');
    }

    public function createManual(array $data): ProductBarcode
    {
        return $this->createWorkflowBarcode($data, 'manual', 'manual_entry');
    }

    public function createScanned(array $data): ProductBarcode
    {
        return $this->createWorkflowBarcode($data, $data['source'] ?? 'manufacturer', 'scanned_attachment');
    }

    public function createSupplier(array $data): ProductBarcode
    {
        if (empty($data['supplier_id'])) {
            abort(422, 'Supplier barcode workflow requires supplier_id.');
        }

        return $this->createWorkflowBarcode($data, 'supplier', 'supplier_catalog');
    }

    public function createScale(array $data): ProductBarcode
    {
        $metadata = $data['metadata'] ?? [];

        if (! empty($data['scale_format'])) {
            $metadata['scale_format'] = $data['scale_format'];
        }

        $data['metadata'] = $metadata;
        $data['barcode_type'] = 'SCALE';

        return $this->createWorkflowBarcode($data, 'scale', 'scale_registration');
    }

    public function generate(array $data): ProductBarcode
    {
        $data['barcode'] = $data['barcode'] ?? $this->generateUniqueInternalBarcode();
        $data['barcode_type'] = 'INTERNAL';

        return $this->createWorkflowBarcode($data, 'generated', 'backend_generation');
    }

    /**
     * @return array{created: Collection<int, ProductBarcode>, errors: array<int, array<string, mixed>>}
     */
    public function importBatch(array $rows): array
    {
        $created = collect();
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $created->push($this->createWorkflowBarcode($row, 'imported', 'structured_import'));
            } catch (Throwable $exception) {
                $errors[] = [
                    'row' => $index,
                    'barcode' => $row['barcode'] ?? null,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'created' => $created,
            'errors' => $errors,
        ];
    }

    public function delete(ProductBarcode $barcode): void
    {
        $barcode->forceFill(['is_active' => false])->save();
        $barcode->delete();
    }

    public function lookup(string $barcode, ?int $storeId = null): ProductBarcode
    {
        return $this->lookupResult($barcode, $storeId)['barcode'];
    }

    /**
     * @return array{barcode: ProductBarcode, scale_barcode: array<string, mixed>|null}
     */
    public function lookupResult(string $barcode, ?int $storeId = null): array
    {
        try {
            return [
                'barcode' => $this->lookupExact($barcode, $storeId),
                'scale_barcode' => null,
            ];
        } catch (NotFoundHttpException $exception) {
            $scaleResult = $this->lookupScaleBarcode($barcode, $storeId);

            if ($scaleResult) {
                return $scaleResult;
            }

            throw $exception;
        }
    }

    /**
     * @return array{barcode: ProductBarcode, scale_barcode: array<string, mixed>}|null
     */
    public function lookupScaleBarcode(string $barcode, ?int $storeId = null): ?array
    {
        $rawBarcode = ProductBarcode::normalizeBarcode($barcode);

        $formats = ProductScaleBarcodeFormat::query()
            ->active()
            ->where('length', strlen($rawBarcode))
            ->where(fn ($query) => $storeId === null
                ? $query->whereNull('store_id')
                : $query->whereNull('store_id')->orWhere('store_id', $storeId)
            )
            ->orderByRaw('store_id IS NULL')
            ->orderByDesc('priority')
            ->get()
            ->filter(fn (ProductScaleBarcodeFormat $format) => str_starts_with($rawBarcode, $format->prefix));

        foreach ($formats as $format) {
            $parsed = $this->parseScaleBarcode($rawBarcode, $format);

            if (! $parsed) {
                continue;
            }

            try {
                $pluBarcode = $this->lookupExact($parsed['plu'], $storeId, true);
            } catch (NotFoundHttpException) {
                continue;
            }

            return [
                'barcode' => $pluBarcode,
                'scale_barcode' => $parsed,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseScaleBarcode(string $barcode, ProductScaleBarcodeFormat $format): ?array
    {
        if (strlen($barcode) !== $format->length || ! str_starts_with($barcode, $format->prefix)) {
            return null;
        }

        if (! $this->scaleFormatOffsetsAreValid($format)) {
            return null;
        }

        $checksum = $format->checksum ? strtolower($format->checksum) : null;

        if ($checksum === 'ean13' && ! $this->hasValidEan13Checksum($barcode)) {
            return null;
        }

        $plu = substr($barcode, $format->product_code_start, $format->product_code_length);
        $rawValue = substr($barcode, $format->value_start, $format->value_length);

        if ($plu === '' || $rawValue === '' || ! ctype_digit($rawValue)) {
            return null;
        }

        $value = ((int) $rawValue) / (10 ** $format->decimal_places);

        return [
            'format_id' => $format->id,
            'format_name' => $format->name,
            'raw_barcode' => $barcode,
            'plu' => $plu,
            'value_type' => $format->value_type,
            'raw_value' => $rawValue,
            'value' => $value,
            'decimal_places' => $format->decimal_places,
            'checksum' => $checksum,
            'store_id' => $format->store_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saleLine(ProductBarcode $barcode, ?array $scaleBarcode = null, ?int $storeId = null): array
    {
        $saleLine = $this->baseSaleLine($barcode);

        if (! $scaleBarcode || ($saleLine['sale_item_payload'] ?? null) === null) {
            return $saleLine;
        }

        $quantity = $this->scaleQuantity($barcode, $scaleBarcode, $storeId);
        $baseQuantityPerUnit = (float) ($saleLine['quantity_in_base_uom'] ?? 1.0);

        $saleLine['quantity'] = $quantity;
        $saleLine['quantity_in_base_uom'] = $quantity * $baseQuantityPerUnit;
        $saleLine['sale_item_payload']['quantity'] = $quantity;

        return $saleLine;
    }

    private function lookupExact(string $barcode, ?int $storeId = null, bool $scalePluOnly = false): ProductBarcode
    {
        $matches = ProductBarcode::query()
            ->with(['barcodeable'])
            ->forBarcode($barcode)
            ->active()
            ->currentlyValid()
            ->when($scalePluOnly, fn ($query) => $query->where('barcode_type', 'SCALE'))
            ->when(
                $storeId !== null,
                fn ($query) => $query->where(fn ($q) => $q->where('store_id', $storeId)->orWhereNull('store_id')),
                fn ($query) => $query->whereNull('store_id')
            )
            ->get()
            ->filter(fn (ProductBarcode $barcode) => $this->barcodeableIsSellable($barcode))
            ->values();

        if ($matches->isEmpty()) {
            abort(404, 'Barcode not found');
        }

        if ($storeId !== null) {
            $storeSpecific = $matches->where('store_id', $storeId)->values();

            if ($storeSpecific->count() === 1) {
                return $this->loadLookupRelations($storeSpecific->first());
            }

            if ($storeSpecific->count() > 1) {
                throw new ProductBarcodeLookupConflictException('Multiple active barcodes match this store context.');
            }
        }

        $globalMatches = $matches->whereNull('store_id')->values();

        if ($globalMatches->count() !== 1) {
            throw new ProductBarcodeLookupConflictException('Multiple active barcodes match this barcode.');
        }

        return $this->loadLookupRelations($globalMatches->first());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function storeContext(ProductBarcode $barcode, ?int $storeId): ?array
    {
        if ($storeId === null) {
            return null;
        }

        $entity = $barcode->barcodeable;
        $query = StoreProduct::query()->where('store_id', $storeId);

        if ($entity instanceof Product) {
            $query->where('product_id', $entity->id)->whereNull('product_variant_id');
        } elseif ($entity instanceof ProductVariant) {
            $query->where('product_id', $entity->product_id)->where('product_variant_id', $entity->id);
        } elseif ($entity instanceof ProductUom) {
            $query->where('product_id', $entity->product_id)->whereNull('product_variant_id');
        } else {
            return null;
        }

        $storeProduct = $query->first();

        if (! $storeProduct) {
            return [
                'store_id' => $storeId,
                'is_available' => false,
                'store_selling_price' => null,
                'min_stock_level' => null,
            ];
        }

        return [
            'store_id' => $storeId,
            'is_available' => $storeProduct->is_available,
            'store_selling_price' => $storeProduct->store_selling_price,
            'min_stock_level' => $storeProduct->min_stock_level,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSaleLine(ProductBarcode $barcode): array
    {
        $entity = $barcode->barcodeable;

        if ($entity instanceof Product) {
            $entity->loadMissing('baseUom');

            return [
                'target_type' => 'product',
                'product_id' => $entity->id,
                'variant_id' => null,
                'product_uom_id' => null,
                'uom_id' => $entity->base_uom_id,
                'uom_code' => $entity->baseUom?->code,
                'quantity' => 1.0,
                'quantity_in_base_uom' => 1.0,
                'sale_item_payload' => [
                    'product_id' => $entity->id,
                    'variant_id' => null,
                    'bundle_id' => null,
                    'uom_id' => $entity->base_uom_id,
                    'quantity' => 1.0,
                ],
            ];
        }

        if ($entity instanceof ProductVariant) {
            $entity->loadMissing(['product', 'uom']);
            $conversionFactor = (float) $entity->quantity_in_base_uom / max((float) $entity->uom_quantity, 0.0001);

            return [
                'target_type' => 'variant',
                'product_id' => $entity->product_id,
                'variant_id' => $entity->id,
                'product_uom_id' => null,
                'uom_id' => $entity->uom_id,
                'uom_code' => $entity->uom?->code,
                'quantity' => 1.0,
                'quantity_in_base_uom' => $conversionFactor,
                'sale_item_payload' => [
                    'product_id' => $entity->product_id,
                    'variant_id' => $entity->id,
                    'bundle_id' => null,
                    'uom_id' => $entity->uom_id,
                    'quantity' => 1.0,
                ],
            ];
        }

        if ($entity instanceof ProductUom) {
            $entity->loadMissing(['product', 'uom']);
            /** @var UnitOfMeasure|null $uom */
            $uom = $entity->uom;

            return [
                'target_type' => 'product_uom',
                'product_id' => $entity->product_id,
                'variant_id' => null,
                'product_uom_id' => $entity->id,
                'uom_id' => $entity->uom_id,
                'uom_code' => $uom?->code,
                'quantity' => 1.0,
                'quantity_in_base_uom' => (float) $entity->conversion_to_base,
                'sale_item_payload' => [
                    'product_id' => $entity->product_id,
                    'variant_id' => null,
                    'bundle_id' => null,
                    'uom_id' => $entity->uom_id,
                    'quantity' => 1.0,
                ],
            ];
        }

        return [
            'target_type' => 'unknown',
            'sale_item_payload' => null,
        ];
    }

    private function scaleQuantity(ProductBarcode $barcode, array $scaleBarcode, ?int $storeId): float
    {
        $value = (float) $scaleBarcode['value'];

        if (($scaleBarcode['value_type'] ?? 'weight') !== 'price') {
            return $value;
        }

        $unitPrice = $this->unitPriceForScalePriceBarcode($barcode, $storeId);

        if ($unitPrice <= 0.0) {
            abort(422, 'Cannot derive quantity from price-embedded barcode without a positive unit price.');
        }

        return round($value / $unitPrice, 6);
    }

    private function unitPriceForScalePriceBarcode(ProductBarcode $barcode, ?int $storeId): float
    {
        $entity = $barcode->barcodeable;

        if ($entity instanceof Product) {
            return (float) (StoreProduct::query()
                ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))
                ->where('product_id', $entity->id)
                ->whereNull('product_variant_id')
                ->value('store_selling_price') ?? $entity->base_selling_price);
        }

        if ($entity instanceof ProductVariant) {
            $entity->loadMissing('product');

            return (float) ($entity->variant_price ?? ((float) $entity->product?->base_selling_price + (float) $entity->base_selling_price_adjustment));
        }

        if ($entity instanceof ProductUom) {
            $entity->loadMissing('product');
            $basePrice = (float) (StoreProduct::query()
                ->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))
                ->where('product_id', $entity->product_id)
                ->whereNull('product_variant_id')
                ->value('store_selling_price') ?? $entity->product?->base_selling_price);

            return $basePrice * (float) $entity->conversion_to_base;
        }

        return 0.0;
    }

    private function scaleFormatOffsetsAreValid(ProductScaleBarcodeFormat $format): bool
    {
        return $format->product_code_start + $format->product_code_length <= $format->length
            && $format->value_start + $format->value_length <= $format->length;
    }

    private function hasValidEan13Checksum(string $barcode): bool
    {
        if (strlen($barcode) !== 13 || ! ctype_digit($barcode)) {
            return false;
        }

        $sum = 0;

        for ($index = 0; $index < 12; $index++) {
            $digit = (int) $barcode[$index];
            $sum += $index % 2 === 0 ? $digit : $digit * 3;
        }

        $expected = (10 - ($sum % 10)) % 10;

        return $expected === (int) $barcode[12];
    }

    private function ensureUniqueActiveBarcode(array $data): void
    {
        if (! ($data['is_active'] ?? true)) {
            return;
        }

        $exists = ProductBarcode::query()
            ->where('barcode', $data['barcode'])
            ->where('is_active', true)
            ->where('store_id', $data['store_id'] ?? null)
            ->where('supplier_id', $data['supplier_id'] ?? null)
            ->exists();

        if ($exists) {
            abort(422, 'An active barcode already exists for this store and supplier context.');
        }
    }

    private function createWorkflowBarcode(array $data, string $source, string $workflow): ProductBarcode
    {
        $entity = $this->resolveTarget($data);
        $metadata = $data['metadata'] ?? [];
        $metadata['workflow'] = $workflow;

        if (! empty($data['captured_by'])) {
            $metadata['captured_by'] = $data['captured_by'];
        }

        return $this->createFor($entity, [
            'barcode' => $data['barcode'],
            'barcode_type' => $data['barcode_type'] ?? 'INTERNAL',
            'is_primary' => $data['is_primary'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'supplier_id' => $data['supplier_id'] ?? null,
            'region' => $data['region'] ?? null,
            'store_id' => $data['store_id'] ?? null,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'source' => $source,
            'metadata' => $metadata,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    private function resolveTarget(array $data): Model
    {
        return match ($data['target_type'] ?? null) {
            'product' => Product::where('uuid', $data['product_uuid'] ?? null)->firstOrFail(),
            'variant' => ProductVariant::findOrFail($data['variant_id'] ?? null),
            'product_uom' => $this->resolveProductUom($data),
            default => throw new \InvalidArgumentException('Unsupported barcode target type.'),
        };
    }

    private function resolveProductUom(array $data): ProductUom
    {
        $product = Product::where('uuid', $data['product_uuid'] ?? null)->firstOrFail();

        return ProductUom::where('product_id', $product->id)->findOrFail($data['product_uom_id'] ?? null);
    }

    private function generateUniqueInternalBarcode(): string
    {
        do {
            $barcode = sprintf(
                'PCH-%s-%s',
                now()->format('ymd'),
                Str::upper(Str::random(8))
            );
        } while (ProductBarcode::withTrashed()->where('barcode', $barcode)->exists());

        return $barcode;
    }

    private function loadLookupRelations(ProductBarcode $barcode): ProductBarcode
    {
        $entity = $barcode->barcodeable;

        if ($entity instanceof ProductVariant) {
            $barcode->load('barcodeable.product');
        } elseif ($entity instanceof ProductUom) {
            $barcode->load(['barcodeable.product', 'barcodeable.uom']);
        }

        return $barcode;
    }

    private function barcodeableIsSellable(ProductBarcode $barcode): bool
    {
        $entity = $barcode->barcodeable;

        if ($entity instanceof Product) {
            return $entity->is_active;
        }

        if ($entity instanceof ProductVariant) {
            $entity->loadMissing('product');

            return $entity->is_active && ($entity->product?->is_active ?? false);
        }

        if ($entity instanceof ProductUom) {
            $entity->loadMissing('product');

            return ($entity->product?->is_active ?? false) && $entity->is_sales_uom;
        }

        return false;
    }

    private function barcodeRelation(Model $entity): MorphMany
    {
        if (! method_exists($entity, 'barcodes')) {
            throw new \InvalidArgumentException('Entity does not support barcodes.');
        }

        return $entity->barcodes();
    }
}
