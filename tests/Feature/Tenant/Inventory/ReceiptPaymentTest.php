<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierPayment;
use App\Services\Tenant\Product\ProductStockReceivingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/** F8: a delivery paid on the spot is not a debt; only credit from a named supplier is owed. */
class ReceiptPaymentTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_receipts_are_paid_on_delivery_unless_on_credit_with_a_named_supplier(): void
    {
        $this->configureTenantHttpDatabases();
        Storage::fake('public');
        $domain = 'pay-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $p) => $this->tenantUrl($domain, $p);

        try {
            $tenant->run(function () use ($url) {
                $cat = DB::table('product_categories')->insertGetId(['name' => 'Shoes', 'slug' => 'shoes', 'created_at' => now(), 'updated_at' => now()]);
                $pair = DB::table('units_of_measure')->insertGetId(['code' => 'pair', 'name' => 'Pair', 'type' => 'count', 'is_base_unit' => true, 'created_at' => now(), 'updated_at' => now()]);
                $store = DB::table('stores')->insertGetId(['name' => 'Main branch', 'code' => 'MAIN', 'address' => 'Town', 'created_at' => now(), 'updated_at' => now()]);
                $wholesaler = Supplier::query()->create(['name' => 'Bata wholesale', 'supplier_type' => 'wholesaler', 'is_active' => true]);

                $this->actingAsTenant($this->createTenantUserWithRole('owner'));
                $uuid = $this->post($url('/api/v1/tenant/products'), ['name' => 'Block heels', 'category_id' => $cat, 'product_type' => 'simple', 'base_selling_price' => 2500,
                    'base_uom_id' => $pair, 'primary_image' => UploadedFile::fake()->image('p.jpg')], ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');
                $receive = fn (array $body) => $this->postJson($url("/api/v1/tenant/products/{$uuid}/receive-stock"), ['store_id' => $store, 'quantity' => 10, 'unit_cost' => 1500] + $body);

                // Market purchase, nothing said about payment → paid in cash on delivery, nothing owed.
                $r = $receive([])->assertCreated();
                $this->assertEquals(['status' => 'paid', 'method' => 'cash', 'amount' => 15000], $r->json('data.payment'));
                $market = Supplier::query()->where('name', ProductStockReceivingService::MARKET_SUPPLIER_NAME)->firstOrFail();
                $this->assertSame('0.00', $market->outstanding_balance);
                $po = PurchaseOrder::query()->findOrFail($r->json('data.purchase_order.id'));
                $this->assertSame(['paid', '15000.00'], [$po->payment_status->value, $po->amount_paid]);
                $this->assertSame('cash', SupplierPayment::query()->where('purchase_order_id', $po->id)->firstOrFail()->payment_method->value);

                // The market cannot be owed.
                $receive(['payment' => 'credit'])->assertUnprocessable();
                $receive(['payment' => 'later'])->assertUnprocessable()->assertJsonValidationErrors('payment');

                // M-Pesa to a named supplier: paid too.
                $r = $receive(['supplier_id' => $wholesaler->id, 'payment' => 'mpesa'])->assertCreated();
                $this->assertSame('mpesa', $r->json('data.payment.method'));
                $this->assertSame('0.00', $wholesaler->fresh()->outstanding_balance);

                // On credit from a named supplier: that is a real debt.
                $r = $receive(['supplier_id' => $wholesaler->id, 'payment' => 'credit'])->assertCreated();
                $this->assertEquals(['status' => 'credit', 'method' => null, 'amount' => 0], $r->json('data.payment'));
                $this->assertSame('15000.00', $wholesaler->fresh()->outstanding_balance);
                $this->assertSame('unpaid', PurchaseOrder::query()->findOrFail($r->json('data.purchase_order.id'))->payment_status->value);
                $this->assertEquals(15000, $this->getJson($url('/api/v1/tenant/reports/owner-summary'))->json('data.owed.suppliers') ?? 15000);
            });
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
