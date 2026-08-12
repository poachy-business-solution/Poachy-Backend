<?php

namespace App\Services\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBarcode;
use App\Models\Tenant\ProductBarcodeSuggestion;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\ProductVariant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductBarcodeSuggestionService
{
    public function __construct(
        private readonly ProductBarcodeService $barcodeService
    ) {}

    /**
     * @return EloquentCollection<int, ProductBarcodeSuggestion>
     */
    public function pending(): EloquentCollection
    {
        return ProductBarcodeSuggestion::query()
            ->with(['suggestedBarcodeable', 'submittedBy:id,name,email'])
            ->pending()
            ->orderBy('created_at')
            ->get();
    }

    public function suggest(array $data, int $submittedBy): ProductBarcodeSuggestion
    {
        $entity = $this->resolveTarget($data);
        $barcode = ProductBarcode::normalizeBarcode($data['barcode']);

        $this->ensureNoActiveBarcode($barcode, $data);

        $pending = ProductBarcodeSuggestion::query()
            ->pending()
            ->where('barcode', $barcode)
            ->where('store_id', $data['store_id'] ?? null)
            ->where('supplier_id', $data['supplier_id'] ?? null)
            ->first();

        if ($pending) {
            if (
                $pending->suggested_barcodeable_type === $entity->getMorphClass()
                && (int) $pending->suggested_barcodeable_id === (int) $entity->getKey()
            ) {
                return $this->loadSuggestionRelations($pending);
            }

            abort(409, 'A pending barcode suggestion already exists for this barcode context.');
        }

        $suggestion = ProductBarcodeSuggestion::create([
            'suggested_barcodeable_type' => $entity->getMorphClass(),
            'suggested_barcodeable_id' => $entity->getKey(),
            'barcode' => $barcode,
            'barcode_type' => $data['barcode_type'] ?? 'INTERNAL',
            'status' => ProductBarcodeSuggestion::STATUS_PENDING,
            'is_primary' => $data['is_primary'] ?? false,
            'supplier_id' => $data['supplier_id'] ?? null,
            'region' => $data['region'] ?? null,
            'store_id' => $data['store_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'notes' => $data['notes'] ?? null,
            'submitted_by' => $submittedBy,
        ]);

        return $this->loadSuggestionRelations($suggestion);
    }

    /**
     * @return array{suggestion: ProductBarcodeSuggestion, barcode: ProductBarcode}
     */
    public function approve(ProductBarcodeSuggestion $suggestion, array $data, int $reviewedBy): array
    {
        if ($suggestion->status !== ProductBarcodeSuggestion::STATUS_PENDING) {
            abort(422, 'Only pending barcode suggestions can be approved.');
        }

        return DB::connection('tenant')->transaction(function () use ($suggestion, $data, $reviewedBy) {
            $suggestion->loadMissing('suggestedBarcodeable');

            $metadata = $suggestion->metadata ?? [];
            $metadata['workflow'] = 'approved_unknown_barcode_suggestion';
            $metadata['suggestion_id'] = $suggestion->id;
            $metadata['submitted_by'] = $suggestion->submitted_by;
            $metadata['reviewed_by'] = $reviewedBy;

            $barcode = $this->barcodeService->createFor($suggestion->suggestedBarcodeable, [
                'barcode' => $suggestion->barcode,
                'barcode_type' => $suggestion->barcode_type,
                'is_primary' => $data['is_primary'] ?? $suggestion->is_primary,
                'is_active' => true,
                'supplier_id' => $suggestion->supplier_id,
                'region' => $suggestion->region,
                'store_id' => $suggestion->store_id,
                'source' => 'manual',
                'metadata' => $metadata,
                'notes' => $data['notes'] ?? $suggestion->notes,
            ]);

            $suggestion->update([
                'status' => ProductBarcodeSuggestion::STATUS_APPROVED,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'approved_barcode_id' => $barcode->id,
            ]);

            return [
                'suggestion' => $this->loadSuggestionRelations($suggestion->fresh()),
                'barcode' => $barcode,
            ];
        });
    }

    public function reject(ProductBarcodeSuggestion $suggestion, array $data, int $reviewedBy): ProductBarcodeSuggestion
    {
        if ($suggestion->status !== ProductBarcodeSuggestion::STATUS_PENDING) {
            abort(422, 'Only pending barcode suggestions can be rejected.');
        }

        $suggestion->update([
            'status' => ProductBarcodeSuggestion::STATUS_REJECTED,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return $this->loadSuggestionRelations($suggestion->fresh());
    }

    private function ensureNoActiveBarcode(string $barcode, array $data): void
    {
        $exists = ProductBarcode::query()
            ->where('barcode', $barcode)
            ->where('is_active', true)
            ->where('store_id', $data['store_id'] ?? null)
            ->where('supplier_id', $data['supplier_id'] ?? null)
            ->exists();

        if ($exists) {
            abort(422, 'An active barcode already exists for this store and supplier context.');
        }
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

    private function loadSuggestionRelations(ProductBarcodeSuggestion $suggestion): ProductBarcodeSuggestion
    {
        return $suggestion->load([
            'suggestedBarcodeable',
            'submittedBy:id,name,email',
            'reviewedBy:id,name,email',
            'approvedBarcode.barcodeable',
        ]);
    }
}
