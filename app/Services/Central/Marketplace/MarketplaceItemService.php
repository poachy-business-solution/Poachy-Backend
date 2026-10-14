<?php

namespace App\Services\Central\Marketplace;

use App\Models\MarketplaceItem;
use App\Models\MarketplaceProduct;
use Illuminate\Support\Str;

/**
 * Groups listings of the same product across shops. Barcode first (automatic, on every
 * sync), admin mapping second (for items without a barcode or with a wrong one).
 */
class MarketplaceItemService
{
    /** Attach a synced listing to the item that shares its barcode, creating the item on first sight. */
    public function attachByBarcode(MarketplaceProduct $product): ?MarketplaceItem
    {
        $barcode = $product->barcode ? trim($product->barcode) : null;

        if ($barcode === null || $barcode === '') {
            return $product->marketplace_item_id ? $product->item : null;
        }

        $item = MarketplaceItem::query()->where('barcode', $barcode)->first()
            ?? MarketplaceItem::query()->create([
                'name' => $product->name,
                'slug' => $this->uniqueSlug($product->slug ?: $product->name),
                'barcode' => $barcode,
                'marketplace_category_id' => $product->marketplace_category_id,
                'marketplace_brand_id' => $product->marketplace_brand_id,
                'primary_image' => $product->primary_image,
                'description' => $product->online_description ?: $product->description,
            ]);

        if ($product->marketplace_item_id !== $item->id) {
            $product->forceFill(['marketplace_item_id' => $item->id])->save();
        }

        if (! $item->primary_image && $product->primary_image) {
            $item->forceFill(['primary_image' => $product->primary_image])->save();
        }

        return $item;
    }

    /** Admin: put a listing under an existing item, or under a new one named after it. */
    public function map(MarketplaceProduct $product, ?MarketplaceItem $item, ?string $newName, string $adminId): MarketplaceItem
    {
        $item ??= MarketplaceItem::query()->create([
            'name' => $newName ?: $product->name,
            'slug' => $this->uniqueSlug($newName ?: $product->slug ?: $product->name),
            'marketplace_category_id' => $product->marketplace_category_id,
            'marketplace_brand_id' => $product->marketplace_brand_id,
            'primary_image' => $product->primary_image,
            'description' => $product->online_description ?: $product->description,
            'created_by_admin' => $adminId,
        ]);

        $product->forceFill(['marketplace_item_id' => $item->id])->save();

        return $item;
    }

    public function unmap(MarketplaceProduct $product): void
    {
        $product->forceFill(['marketplace_item_id' => null])->save();
    }

    private function uniqueSlug(string $base): string
    {
        $base = Str::limit(Str::slug($base) ?: 'item', 80, '');
        $slug = $base;

        for ($n = 2; MarketplaceItem::query()->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
