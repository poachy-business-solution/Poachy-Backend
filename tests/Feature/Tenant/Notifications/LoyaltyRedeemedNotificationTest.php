<?php

namespace Tests\Feature\Tenant\Notifications;

use App\Events\Tenant\LoyaltyPointsRedeemed;
use App\Jobs\Tenant\SendNotificationJob;
use App\Listeners\Tenant\SendLoyaltyRedeemedNotification;
use App\Models\Tenant\Customer;
use App\Models\Tenant\LoyaltyTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoyaltyRedeemedNotificationTest extends TestCase
{
    private const TEST_DB = 'poachy_test';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', self::TEST_DB);
        DB::purge('tenant');

        DB::connection('tenant')->statement('SET foreign_key_checks = 0');
        $this->dropTestTables();
        $this->createMinimalSchema();

        Queue::fake();
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    public function test_handle_dispatches_email_when_redeeming_customer_has_email(): void
    {
        $customer = $this->createCustomer(['email' => 'loyalty@test.com']);
        $transaction = $this->createRedeemedTransaction($customer);

        (new SendLoyaltyRedeemedNotification)->handle(new LoyaltyPointsRedeemed($customer, $transaction));

        Queue::assertPushed(SendNotificationJob::class, function (SendNotificationJob $job) use ($customer, $transaction) {
            return $job->channel === 'email'
                && $job->recipient === $customer->email
                && $job->message['subject'] === 'Loyalty Points Redeemed'
                && str_contains($job->message['body'], '100 loyalty points')
                && $job->metadata['customer_id'] === $customer->id
                && $job->metadata['transaction_id'] === $transaction->id
                && $job->metadata['notification_type'] === 'loyalty_redeemed';
        });
    }

    public function test_handle_skips_email_when_customer_has_no_email(): void
    {
        $customer = $this->createCustomer(['email' => null]);
        $transaction = $this->createRedeemedTransaction($customer);

        (new SendLoyaltyRedeemedNotification)->handle(new LoyaltyPointsRedeemed($customer, $transaction));

        Queue::assertNotPushed(SendNotificationJob::class, fn (SendNotificationJob $job) => $job->channel === 'email');
    }

    private function createCustomer(array $overrides = []): Customer
    {
        return Model::withoutEvents(fn () => Customer::create(array_merge([
            'customer_number' => 'CUST-'.uniqid(),
            'name' => 'Loyalty Customer',
            'phone' => '0712'.rand(100000, 999999),
            'email' => 'customer-'.uniqid().'@test.com',
            'loyalty_points' => 400,
            'accepts_marketing' => true,
        ], $overrides)));
    }

    private function createRedeemedTransaction(Customer $customer): LoyaltyTransaction
    {
        return Model::withoutEvents(fn () => LoyaltyTransaction::create([
            'customer_id' => $customer->id,
            'transaction_type' => 'redeemed',
            'points' => -100,
            'balance_after' => 400,
            'reference_type' => 'Sale',
            'reference_id' => 1,
            'description' => 'Redeemed for sale',
        ]));
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number')->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->string('customer_type')->default('walk_in');
            $table->decimal('loyalty_points', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('accepts_marketing')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection($conn)->create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('transaction_type');
            $table->decimal('points', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function dropTestTables(): void
    {
        foreach (['loyalty_transactions', 'customers'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }
}
