<?php

namespace Database\Seeders\Demo;

use App\Enums\Tenant\PaymentTerms;
use App\Enums\Tenant\ProductType;
use App\Enums\Tenant\SupplierType;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\Store;
use App\Models\Tenant\StoreProduct;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\TaxRate;
use App\Models\Tenant\UnitOfMeasure;
use App\Services\Tenant\Product\ProductBundleService;
use App\Services\Tenant\Product\ProductService;
use App\Services\Tenant\Product\ProductUomService;
use App\Services\Tenant\Product\ProductVariantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemoCatalogSeeder extends Seeder
{
    protected UnitOfMeasure $pcs;

    protected UnitOfMeasure $kg;

    protected TaxRate $vat;

    protected TaxRate $zeroRated;

    public function run(
        ProductService $productService,
        ProductUomService $productUomService,
        ProductVariantService $productVariantService,
        ProductBundleService $productBundleService,
    ): void {
        $this->pcs = UnitOfMeasure::where('code', 'pcs')->firstOrFail();
        $this->kg = UnitOfMeasure::where('code', 'kg')->firstOrFail();

        [$this->vat, $this->zeroRated] = $this->seedTaxRates();
        $suppliers = $this->seedSuppliers();

        $products = $this->seedProducts($productService, $productUomService, $suppliers);
        $this->seedVariants($productVariantService, $products);
        $this->seedBarcodes($products);
        $this->seedStoreAvailability($products);
        $this->seedBundles($productBundleService, $products);
        $this->backdatePriceHistory($productService, $products);

        $this->command->info('✓ Catalog: '.count($products).' products, '.$suppliers->count().' suppliers, 3 tax rates');
    }

    /**
     * @return array{0: TaxRate, 1: TaxRate}
     */
    protected function seedTaxRates(): array
    {
        $vat = TaxRate::create([
            'tax_name' => 'VAT 16%',
            'rate' => 16,
            'effective_from' => now()->subYear(),
            'is_active' => true,
            'is_default' => true,
        ]);

        $zeroRated = TaxRate::create([
            'tax_name' => 'Zero-Rated (Staple Foods)',
            'rate' => 0,
            'effective_from' => now()->subYear(),
            'is_active' => true,
            'is_default' => false,
        ]);

        TaxRate::create([
            'tax_name' => 'Exempt',
            'rate' => 0,
            'effective_from' => now()->subYear(),
            'is_active' => true,
            'is_default' => false,
        ]);

        return [$vat, $zeroRated];
    }

    /**
     * @return Collection<int, Supplier>
     */
    protected function seedSuppliers(): Collection
    {
        return collect([
            ['name' => 'Nairobi Wholesale Distributors', 'supplier_type' => SupplierType::DISTRIBUTOR, 'contact_person' => 'Peter Kariuki', 'email' => 'sales@nairobiwholesale.test', 'phone' => '+254722100001', 'payment_terms' => PaymentTerms::NET_30],
            ['name' => 'Coast Electronics Ltd', 'supplier_type' => SupplierType::MANUFACTURER, 'contact_person' => 'Grace Mumbi', 'email' => 'orders@coastelectronics.test', 'phone' => '+254722100002', 'payment_terms' => PaymentTerms::NET_15],
            ['name' => 'Freshpick Farms', 'supplier_type' => SupplierType::DISTRIBUTOR, 'contact_person' => 'Samuel Kiplagat', 'email' => 'supply@freshpick.test', 'phone' => '+254722100003', 'payment_terms' => PaymentTerms::COD],
            ['name' => 'Twiga Foods Supplies', 'supplier_type' => SupplierType::WHOLESALER, 'contact_person' => 'Lucy Chebet', 'email' => 'accounts@twigafoods.test', 'phone' => '+254722100004', 'payment_terms' => PaymentTerms::NET_30],
            ['name' => 'UrbanStyle Apparel', 'supplier_type' => SupplierType::MANUFACTURER, 'contact_person' => 'David Mwenda', 'email' => 'wholesale@urbanstyle.test', 'phone' => '+254722100005', 'payment_terms' => PaymentTerms::NET_15],
        ])->map(fn (array $data) => Supplier::create($data + ['is_active' => true]));
    }

    /**
     * @return array<string, Product> keyed by a short handle for later phases
     */
    protected function seedProducts(ProductService $productService, ProductUomService $productUomService, Collection $suppliers): array
    {
        $category = fn (string $name) => ProductCategory::where('name', $name)->firstOrFail()->id;
        $brand = fn (string $name) => ProductBrand::where('name', $name)->firstOrFail()->id;
        $supplier = fn (string $name) => $suppliers->firstWhere('name', $name)->id;

        $specs = [
            'phone_samsung' => ['name' => 'Samsung Galaxy A14', 'category' => 'Mobile Phones', 'brand' => 'Samsung', 'supplier' => 'Coast Electronics Ltd', 'price' => 24999, 'serial' => true, 'online' => 25999],
            'phone_tecno' => ['name' => 'Tecno Spark 10', 'category' => 'Mobile Phones', 'brand' => 'Tecno', 'supplier' => 'Coast Electronics Ltd', 'price' => 15999, 'serial' => true, 'online' => 16499],
            'laptop_hp' => ['name' => 'HP Pavilion 15 Laptop', 'category' => 'Computers & Laptops', 'brand' => 'HP', 'supplier' => 'Coast Electronics Ltd', 'price' => 68999, 'serial' => true],
            'laptop_dell' => ['name' => 'Dell Inspiron 14', 'category' => 'Computers & Laptops', 'brand' => 'Dell', 'supplier' => 'Coast Electronics Ltd', 'price' => 74999, 'serial' => true],
            'charger' => ['name' => 'Type-C Fast Charger', 'category' => 'Phone Accessories', 'brand' => 'Generic', 'supplier' => 'Coast Electronics Ltd', 'price' => 799],
            'earphones' => ['name' => 'Samsung Wired Earphones', 'category' => 'Phone Accessories', 'brand' => 'Samsung', 'supplier' => 'Coast Electronics Ltd', 'price' => 1499],
            'screen_protector' => ['name' => 'Universal Screen Protector', 'category' => 'Phone Accessories', 'brand' => 'Generic', 'supplier' => 'Coast Electronics Ltd', 'price' => 299],

            'milk' => ['name' => 'Brookside Fresh Milk 500ml', 'category' => 'Beverages', 'brand' => 'Brookside', 'supplier' => 'Freshpick Farms', 'price' => 65, 'batch' => true, 'shelf_life' => 7, 'tax' => 'zero'],
            'tomatoes' => ['name' => 'Fresh Tomatoes', 'category' => 'Fresh Produce', 'brand' => 'Generic', 'supplier' => 'Freshpick Farms', 'price' => 120, 'batch' => true, 'shelf_life' => 5, 'tax' => 'zero', 'weighed' => true, 'uom' => 'kg'],
            'bananas' => ['name' => 'Fresh Bananas', 'category' => 'Fresh Produce', 'brand' => 'Generic', 'supplier' => 'Freshpick Farms', 'price' => 100, 'batch' => true, 'shelf_life' => 6, 'tax' => 'zero', 'weighed' => true, 'uom' => 'kg'],
            'coke' => ['name' => 'Coca-Cola 500ml', 'category' => 'Beverages', 'brand' => 'Coca-Cola', 'supplier' => 'Twiga Foods Supplies', 'price' => 70, 'batch' => true, 'shelf_life' => 180, 'purchase_uom' => ['ctn', 24]],
            'pepsi' => ['name' => 'Pepsi 1L', 'category' => 'Beverages', 'brand' => 'Pepsi', 'supplier' => 'Twiga Foods Supplies', 'price' => 120, 'batch' => true, 'shelf_life' => 200, 'purchase_uom' => ['ctn', 12]],
            'cerelac' => ['name' => 'Nestlé Cerelac 400g', 'category' => 'Packaged Foods', 'brand' => 'Nestlé', 'supplier' => 'Twiga Foods Supplies', 'price' => 650, 'batch' => true, 'shelf_life' => 365],
            'blueband' => ['name' => 'Unilever Blue Band 500g', 'category' => 'Packaged Foods', 'brand' => 'Unilever', 'supplier' => 'Twiga Foods Supplies', 'price' => 340, 'batch' => true, 'shelf_life' => 270],
            'rice' => ['name' => 'Pishori Rice 2kg', 'category' => 'Packaged Foods', 'brand' => 'Generic', 'supplier' => 'Twiga Foods Supplies', 'price' => 380, 'batch' => true, 'shelf_life' => 365, 'tax' => 'zero'],

            'sneakers' => ['name' => 'Nike Air Max Sneakers', 'category' => 'Footwear', 'brand' => 'Nike', 'supplier' => 'UrbanStyle Apparel', 'price' => 8999, 'variable' => true, 'online' => 9499],
            'tshirt' => ['name' => 'Adidas Sport T-Shirt', 'category' => 'Men’s Fashion', 'brand' => 'Adidas', 'supplier' => 'UrbanStyle Apparel', 'price' => 2499, 'variable' => true],
            'jeans' => ['name' => 'Levi’s 501 Jeans', 'category' => 'Men’s Fashion', 'brand' => 'Levi’s', 'supplier' => 'UrbanStyle Apparel', 'price' => 4999],

            'kettle' => ['name' => 'Ramtons Electric Kettle', 'category' => 'Kitchenware', 'brand' => 'Ramtons', 'supplier' => 'Nairobi Wholesale Distributors', 'price' => 2999],
            'microwave' => ['name' => 'LG Microwave 20L', 'category' => 'Kitchenware', 'brand' => 'LG', 'supplier' => 'Nairobi Wholesale Distributors', 'price' => 12999],
            'wall_clock' => ['name' => 'Wall Clock', 'category' => 'Home Décor', 'brand' => 'Generic', 'supplier' => 'Nairobi Wholesale Distributors', 'price' => 1299],
            'shopping_bag' => ['name' => 'Reusable Shopping Bag', 'category' => 'Home Décor', 'brand' => 'Generic', 'supplier' => 'Nairobi Wholesale Distributors', 'price' => 150],
        ];

        $products = [];

        foreach ($specs as $handle => $spec) {
            $uom = $this->pcs;
            if (($spec['uom'] ?? null) === 'kg') {
                $uom = $this->kg;
            }

            $product = $productService->create([
                'name' => $spec['name'],
                'description' => $spec['name'].' — demo catalogue item.',
                'category_id' => $category($spec['category']),
                'brand_id' => $brand($spec['brand']),
                'supplier_id' => $supplier($spec['supplier']),
                'product_type' => ($spec['variable'] ?? false) ? ProductType::VARIABLE : ProductType::SIMPLE,
                'is_weighed' => $spec['weighed'] ?? false,
                'requires_batch_tracking' => $spec['batch'] ?? false,
                'requires_serial_tracking' => $spec['serial'] ?? false,
                'base_selling_price' => $spec['price'],
                'tax_rate_id' => ($spec['tax'] ?? null) === 'zero' ? $this->zeroRated->id : $this->vat->id,
                'base_uom_id' => $uom->id,
                'reorder_level' => $spec['batch'] ?? false ? 10 : 5,
                'shelf_life_days' => $spec['shelf_life'] ?? null,
                'is_active' => true,
                'is_available_online' => isset($spec['online']),
                'online_price' => $spec['online'] ?? null,
            ]);

            $productUomService->create($product, [
                'uom_id' => $uom->id,
                'is_base_uom' => true,
                'is_purchase_uom' => ! isset($spec['purchase_uom']),
                'is_sales_uom' => true,
            ]);

            if (isset($spec['purchase_uom'])) {
                [$purchaseUomCode, $conversion] = $spec['purchase_uom'];
                $purchaseUom = UnitOfMeasure::where('code', $purchaseUomCode)->firstOrFail();

                $productUomService->create($product, [
                    'uom_id' => $purchaseUom->id,
                    'is_base_uom' => false,
                    'is_purchase_uom' => true,
                    'is_sales_uom' => false,
                    'conversion_to_base' => $conversion,
                ]);
            }

            $products[$handle] = $product->fresh();
        }

        return $products;
    }

    /**
     * @param  array<string, Product>  $products
     */
    protected function seedVariants(ProductVariantService $productVariantService, array $products): void
    {
        foreach (['40' => -200, '41' => 0, '42' => 0, '43' => 200] as $size => $adjustment) {
            $productVariantService->create($products['sneakers'], [
                'variant_name' => "Size {$size}",
                'attributes' => ['size' => $size],
                'uom_id' => $this->pcs->id,
                'uom_quantity' => 1,
                'base_selling_price_adjustment' => $adjustment,
                'is_active' => true,
            ]);
        }

        foreach (['S', 'M', 'L', 'XL'] as $size) {
            $productVariantService->create($products['tshirt'], [
                'variant_name' => "Size {$size}",
                'attributes' => ['size' => $size],
                'uom_id' => $this->pcs->id,
                'uom_quantity' => 1,
                'base_selling_price_adjustment' => 0,
                'is_active' => true,
            ]);
        }
    }

    /**
     * product_barcodes has no model/service in the codebase (confirmed — no
     * app/Models/*Barcode* file, no references outside the migration). Inserted
     * directly against the schema so the table isn't left empty; flag to the team
     * that this feature is otherwise entirely unbuilt.
     *
     * @param  array<string, Product>  $products
     */
    protected function seedBarcodes(array $products): void
    {
        $morphClass = (new Product)->getMorphClass();
        $now = now();

        foreach (array_slice($products, 0, 10, true) as $product) {
            DB::table('product_barcodes')->insert([
                'barcodeable_type' => $morphClass,
                'barcodeable_id' => $product->id,
                'barcode' => fake()->unique()->ean13(),
                'barcode_type' => 'EAN-13',
                'is_primary' => true,
                'is_active' => true,
                'source' => 'generated',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, Product>  $products
     */
    protected function seedStoreAvailability(array $products): void
    {
        $stores = Store::all();

        foreach ($products as $product) {
            foreach ($stores as $store) {
                StoreProduct::create([
                    'store_id' => $store->id,
                    'product_id' => $product->id,
                    'is_available' => true,
                    'min_stock_level' => (float) $product->reorder_level,
                ]);
            }
        }
    }

    /**
     * @param  array<string, Product>  $products
     */
    protected function seedBundles(ProductBundleService $productBundleService, array $products): void
    {
        $bundle = $productBundleService->create([
            'bundle_name' => 'Phone Starter Pack',
            'description' => 'Charger, earphones, and a screen protector — bundled and discounted.',
            'base_uom_id' => $this->pcs->id,
            'tax_rate_id' => $this->vat->id,
            'bundle_price' => 2199,
            'online_price' => 2299,
            'is_active' => true,
            'is_available_online' => true,
            'items' => [
                ['product_id' => $products['charger']->id, 'uom_id' => $this->pcs->id, 'quantity' => 1],
                ['product_id' => $products['earphones']->id, 'uom_id' => $this->pcs->id, 'quantity' => 1],
                ['product_id' => $products['screen_protector']->id, 'uom_id' => $this->pcs->id, 'quantity' => 1],
            ],
        ]);

        $productBundleService->create([
            'bundle_name' => 'Breakfast Bundle',
            'description' => 'Milk, Cerelac, and Blue Band — a breakfast essentials pack.',
            'base_uom_id' => $this->pcs->id,
            'tax_rate_id' => $this->vat->id,
            'bundle_price' => 999,
            'is_active' => true,
            'is_available_online' => false,
            'items' => [
                ['product_id' => $products['milk']->id, 'uom_id' => $this->pcs->id, 'quantity' => 2],
                ['product_id' => $products['cerelac']->id, 'uom_id' => $this->pcs->id, 'quantity' => 1],
                ['product_id' => $products['blueband']->id, 'uom_id' => $this->pcs->id, 'quantity' => 1],
            ],
        ]);

        unset($bundle);
    }

    /**
     * ProductObserver only writes product_price_history on an *update* that changes
     * base_selling_price — never on the initial create — so without this the table
     * stays empty. Bump a few prices to simulate a real price-change history.
     *
     * @param  array<string, Product>  $products
     */
    protected function backdatePriceHistory(ProductService $productService, array $products): void
    {
        foreach (['phone_samsung', 'milk', 'sneakers'] as $handle) {
            $product = $products[$handle];

            $productService->update($product, [
                'base_selling_price' => round($product->base_selling_price * 1.05, 2),
            ]);
        }
    }
}
