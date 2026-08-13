<?php

namespace Tests\Feature\Tenant\Supplier;

use App\Enums\Tenant\PaymentMethod;
use App\Events\Tenant\SupplierPaymentRecorded;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\LogSupplierPaymentRecorded;
use App\Models\Tenant\Supplier;
use App\Services\Tenant\Supplier\SupplierPaymentService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class SupplierPaymentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->dropTestTables();
        $this->createMinimalSchema();
        $this->seedBaseData();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        Auth::setUser(new class implements Authenticatable
        {
            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 1;
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return 'remember_token';
            }
        });
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    // =========================================================================
    // recordPayment()
    // =========================================================================

    public function test_record_payment_happy_path_no_po_link(): void
    {
        // Scoped to just this event — a blanket Event::fake() also fakes Eloquent
        // model events (creating/updating/etc.), which would silently disable the
        // SupplierPaymentObserver that generates payment_number.
        Event::fake([SupplierPaymentRecorded::class]);

        $supplier = $this->createSupplierRow(['outstanding_balance' => 1000]);

        $payment = $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 300,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        $this->assertNotEmpty($payment->payment_number);
        $this->assertStringStartsWith('PAY-SUP-', $payment->payment_number);
        $this->assertEquals(700.0, (float) $supplier->fresh()->outstanding_balance);

        Event::assertDispatched(SupplierPaymentRecorded::class, fn ($event) => $event->payment->id === $payment->id);
    }

    public function test_supplier_payment_recorded_listener_is_registered(): void
    {
        $this->assertTrue(Event::getFacadeRoot()->hasListeners(SupplierPaymentRecorded::class));
    }

    public function test_supplier_payment_recorded_listener_emails_supplier(): void
    {
        Queue::fake();
        $supplier = $this->createSupplierRow([
            'email' => 'supplier-payments@example.com',
            'outstanding_balance' => 1000,
        ]);
        $poId = $this->insertPurchaseOrder($supplier->id, status: 'sent', paymentStatus: 'unpaid', totalAmount: 1000, amountPaid: 0);

        Event::fake([SupplierPaymentRecorded::class]);

        $payment = $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $poId,
            'payment_date' => '2026-08-13',
            'amount' => 250,
            'payment_method' => PaymentMethod::MPESA->value,
            'reference_number' => 'MPESA-123',
        ]);

        Event::assertDispatched(SupplierPaymentRecorded::class);

        (new LogSupplierPaymentRecorded)->handle(new SupplierPaymentRecorded($payment));

        Queue::assertPushed(SendNotificationJob::class, function (SendNotificationJob $job) use ($payment) {
            return $job->channel === 'email'
                && $job->recipient === 'supplier-payments@example.com'
                && $job->metadata['notification_type'] === 'supplier_payment_recorded'
                && $job->metadata['supplier_payment_id'] === $payment->id
                && str_contains($job->message['subject'], $payment->payment_number)
                && str_contains($job->message['body'], 'Amount: KES 250.00')
                && str_contains($job->message['body'], 'Reference: MPESA-123');
        });
    }

    public function test_record_payment_throws_when_supplier_inactive(): void
    {
        $supplier = $this->createSupplierRow(['outstanding_balance' => 1000, 'is_active' => false]);

        $this->expectException(\RuntimeException::class);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'payment_method' => PaymentMethod::CASH->value,
        ]);
    }

    public function test_record_payment_with_po_link_updates_amount_paid_and_payment_status(): void
    {
        $supplier = $this->createSupplierRow(['outstanding_balance' => 1000]);
        $poId = $this->insertPurchaseOrder($supplier->id, status: 'sent', paymentStatus: 'unpaid', totalAmount: 1000, amountPaid: 0);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $poId,
            'payment_date' => now()->toDateString(),
            'amount' => 400,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        $po = DB::connection('tenant')->table('purchase_orders')->find($poId);
        $this->assertEquals(400.0, (float) $po->amount_paid);
        $this->assertSame('partially_paid', $po->payment_status);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $poId,
            'payment_date' => now()->toDateString(),
            'amount' => 600,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        $po = DB::connection('tenant')->table('purchase_orders')->find($poId);
        $this->assertEquals(1000.0, (float) $po->amount_paid);
        $this->assertSame('paid', $po->payment_status);
    }

    public function test_record_payment_throws_when_po_does_not_belong_to_supplier(): void
    {
        $supplierA = $this->createSupplierRow(['outstanding_balance' => 1000]);
        $supplierB = $this->createSupplierRow(['outstanding_balance' => 1000]);
        $poForB = $this->insertPurchaseOrder($supplierB->id, status: 'sent', paymentStatus: 'unpaid', totalAmount: 1000, amountPaid: 0);

        $this->expectException(\RuntimeException::class);

        $this->service()->recordPayment([
            'supplier_id' => $supplierA->id,
            'purchase_order_id' => $poForB,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'payment_method' => PaymentMethod::CASH->value,
        ]);
    }

    public function test_record_payment_throws_when_po_status_cannot_receive_payment(): void
    {
        $supplier = $this->createSupplierRow(['outstanding_balance' => 1000]);
        $poId = $this->insertPurchaseOrder($supplier->id, status: 'draft', paymentStatus: 'unpaid', totalAmount: 1000, amountPaid: 0);

        $this->expectException(\RuntimeException::class);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $poId,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'payment_method' => PaymentMethod::CASH->value,
        ]);
    }

    public function test_record_payment_throws_when_amount_exceeds_po_outstanding(): void
    {
        $supplier = $this->createSupplierRow(['outstanding_balance' => 5000]);
        $poId = $this->insertPurchaseOrder($supplier->id, status: 'sent', paymentStatus: 'unpaid', totalAmount: 1000, amountPaid: 0);

        $this->expectException(\RuntimeException::class);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $poId,
            'payment_date' => now()->toDateString(),
            'amount' => 1500, // exceeds the PO's own outstanding, though within the supplier's
            'payment_method' => PaymentMethod::CASH->value,
        ]);
    }

    public function test_record_payment_throws_when_amount_exceeds_supplier_outstanding_balance(): void
    {
        $supplier = $this->createSupplierRow(['outstanding_balance' => 200]);

        $this->expectException(\RuntimeException::class);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 300,
            'payment_method' => PaymentMethod::CASH->value,
        ]);
    }

    public function test_payment_number_sequencing_increments(): void
    {
        $supplier = $this->createSupplierRow(['outstanding_balance' => 1000]);

        $first = $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        $second = $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        $firstSeq = (int) substr($first->payment_number, -4);
        $secondSeq = (int) substr($second->payment_number, -4);

        $this->assertSame($firstSeq + 1, $secondSeq);
    }

    public function test_receipt_upload_stores_file_and_updates_accessors(): void
    {
        Storage::fake('public');

        $supplier = $this->createSupplierRow(['outstanding_balance' => 1000]);
        $file = UploadedFile::fake()->create('receipt.pdf', 10);

        $payment = $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'payment_method' => PaymentMethod::CASH->value,
            'receipt' => $file,
        ]);

        $this->assertTrue($payment->has_receipt);
        $this->assertNotNull($payment->receipt_path);
        $this->assertNotNull($payment->receipt_url);
        Storage::disk('public')->assertExists($payment->receipt_path);
    }

    // =========================================================================
    // getSupplierPayments() / getPurchaseOrderPayments()
    // =========================================================================

    public function test_get_supplier_payments_filters_by_po_method_and_date_range(): void
    {
        $supplier = $this->createSupplierRow(['outstanding_balance' => 10000]);
        $poId = $this->insertPurchaseOrder($supplier->id, status: 'sent', paymentStatus: 'unpaid', totalAmount: 5000, amountPaid: 0);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $poId,
            'payment_date' => '2026-01-01',
            'amount' => 100,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'payment_date' => '2026-02-01',
            'amount' => 200,
            'payment_method' => PaymentMethod::MPESA->value,
        ]);

        $byPo = $this->service()->getSupplierPayments($supplier->id, ['purchase_order_id' => $poId]);
        $this->assertSame(1, $byPo->total());

        $byMethod = $this->service()->getSupplierPayments($supplier->id, ['payment_method' => PaymentMethod::MPESA->value]);
        $this->assertSame(1, $byMethod->total());

        $byDateRange = $this->service()->getSupplierPayments($supplier->id, ['from_date' => '2026-02-01', 'to_date' => '2026-02-28']);
        $this->assertSame(1, $byDateRange->total());
        $this->assertEquals(200.0, (float) $byDateRange->first()->amount);
    }

    public function test_get_purchase_order_payments_returns_only_that_pos_payments(): void
    {
        $supplier = $this->createSupplierRow(['outstanding_balance' => 10000]);
        $poA = $this->insertPurchaseOrder($supplier->id, status: 'sent', paymentStatus: 'unpaid', totalAmount: 5000, amountPaid: 0);
        $poB = $this->insertPurchaseOrder($supplier->id, status: 'sent', paymentStatus: 'unpaid', totalAmount: 5000, amountPaid: 0);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $poA,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $poB,
            'payment_date' => now()->toDateString(),
            'amount' => 200,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        $result = $this->service()->getPurchaseOrderPayments($poA);

        $this->assertCount(1, $result);
        $this->assertEquals(100.0, (float) $result->first()->amount);
    }

    // =========================================================================
    // getSupplierPaymentSummary()
    // =========================================================================

    public function test_supplier_payment_summary_computes_totals_and_by_method_breakdown(): void
    {
        $supplier = $this->createSupplierRow(['outstanding_balance' => 10000]);

        $this->service()->recordPayment([
            'supplier_id' => $supplier->id, 'payment_date' => now()->toDateString(),
            'amount' => 100, 'payment_method' => PaymentMethod::CASH->value,
        ]);
        $this->service()->recordPayment([
            'supplier_id' => $supplier->id, 'payment_date' => now()->toDateString(),
            'amount' => 150, 'payment_method' => PaymentMethod::CASH->value,
        ]);
        $this->service()->recordPayment([
            'supplier_id' => $supplier->id, 'payment_date' => now()->toDateString(),
            'amount' => 200, 'payment_method' => PaymentMethod::MPESA->value,
        ]);

        $summary = $this->service()->getSupplierPaymentSummary($supplier->id);

        $this->assertEquals(450.0, $summary['total_paid']);
        $this->assertSame(3, $summary['payment_count']);
        $this->assertSame('KES', $summary['currency']);

        $byMethod = collect($summary['by_method'])->keyBy('method');
        $this->assertEquals(250.0, $byMethod['cash']['total']);
        $this->assertSame(2, $byMethod['cash']['count']);
        $this->assertEquals(200.0, $byMethod['mpesa']['total']);
        $this->assertSame(1, $byMethod['mpesa']['count']);
    }

    // =========================================================================
    // SupplierPayment deletion — observer-level guard
    // =========================================================================

    public function test_supplier_payment_delete_always_throws(): void
    {
        $supplier = $this->createSupplierRow(['outstanding_balance' => 1000]);

        $payment = $this->service()->recordPayment([
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'payment_method' => PaymentMethod::CASH->value,
        ]);

        $this->expectException(\RuntimeException::class);

        $payment->delete();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function service(): SupplierPaymentService
    {
        return new SupplierPaymentService;
    }

    private function createSupplierRow(array $overrides = []): Supplier
    {
        return Model::withoutEvents(fn () => Supplier::create(array_merge([
            'name' => 'Test Supplier '.uniqid(),
            'supplier_type' => 'distributor',
            'credit_limit' => 0,
            'outstanding_balance' => 0,
            'payment_terms' => 'cod',
            'is_active' => true,
        ], $overrides)));
    }

    private function insertPurchaseOrder(int $supplierId, string $status = 'sent', string $paymentStatus = 'unpaid', float $totalAmount = 0, float $amountPaid = 0): int
    {
        return DB::connection('tenant')->table('purchase_orders')->insertGetId([
            'po_number' => 'PO-'.uniqid(),
            'supplier_id' => $supplierId,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'total_amount' => $totalAmount,
            'amount_paid' => $amountPaid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedBaseData(): void
    {
        DB::connection('tenant')->table('users')->insert([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test-user@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function dropTestTables(): void
    {
        foreach ([
            'supplier_payments',
            'purchase_orders',
            'suppliers',
            'users',
        ] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::connection($conn)->create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('supplier_type');
            $table->string('email')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->string('payment_terms')->default('cod');
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('store_id')->nullable();
            $table->date('order_date')->nullable();
            $table->string('status')->default('draft');
            $table->string('payment_status')->default('unpaid');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('receipt_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }
}
