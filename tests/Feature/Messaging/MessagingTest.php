<?php

namespace Tests\Feature\Messaging;

use App\Exceptions\SmsException;
use App\Mail\Shared\PlainMessageMail;
use App\Models\BusinessDetail;
use App\Models\MessageLog;
use App\Services\Shared\Messaging\Messenger;
use App\Services\Shared\Sms\Gateways\AfricasTalkingSmsGateway;
use App\Services\Shared\Sms\Gateways\LogSmsGateway;
use App\Services\Shared\Sms\SmsGateway;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * SMS gateways, the Poachy markdown mail layout and the Messenger helper (send + log).
 */
class MessagingTest extends TestCase
{
    private const TENANT = 'messaging-test-tenant';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Config::set('messaging.paybill_number', '4123456');
        Config::set('messaging.site_url', 'https://poachy.test');
        MessageLog::query()->where('recipient', 'like', '%messaging-test%')->orWhere('tenant_id', self::TENANT)->delete();
    }

    protected function tearDown(): void
    {
        MessageLog::query()->where('tenant_id', self::TENANT)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        DB::connection('central')->table('business_details')->where('tenant_id', self::TENANT)->delete();
        DB::connection('central')->table('tenants')->where('id', self::TENANT)->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        parent::tearDown();
    }

    public function test_the_log_sms_driver_is_the_default(): void
    {
        $this->assertInstanceOf(LogSmsGateway::class, app(SmsGateway::class));

        Config::set('messaging.sms.driver', 'africastalking');
        $this->assertInstanceOf(AfricasTalkingSmsGateway::class, app(SmsGateway::class));
    }

    public function test_the_poachy_markdown_layout_renders_with_the_paybill_box(): void
    {
        $mail = new PlainMessageMail(
            heading: 'Top up your Poachy balance',
            paragraphs: ['Your balance covers 2 more trading days.'],
            buttonLabel: 'Pay now',
            buttonUrl: 'https://poachy.test/pay/abc',
            paybillAccount: 'POA00042',
            payUrl: 'https://poachy.test/pay/abc',
        );

        $html = $mail->render();

        $this->assertStringContainsString('class="wordmark"', $html);
        $this->assertStringContainsString('#1E7A4B', $html); // Poachy green from the theme
        $this->assertStringContainsString('4123456', $html);
        $this->assertStringContainsString('POA00042', $html);
        $this->assertStringContainsString('Pay now', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_messenger_email_is_logged_and_marked_sent(): void
    {
        $log = app(Messenger::class)->email('Owner.messaging-test@Example.com', new PlainMessageMail('Hello', ['Body']), self::TENANT);

        $log->refresh();
        $this->assertSame('email', $log->channel);
        $this->assertSame('PlainMessageMail', $log->template);
        $this->assertSame('owner.messaging-test@example.com', $log->recipient);
        $this->assertSame('Hello', $log->subject);
        $this->assertSame('sent', $log->status, (string) $log->error); // sync queue + array mailer in tests
    }

    public function test_messenger_sms_goes_to_the_log_and_is_marked_sent(): void
    {
        Log::shouldReceive('channel')->with('sms')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(fn ($m, $ctx) => $ctx['to'] === '+254712345678' && $ctx['message'] === 'Your code is 123456');

        $log = app(Messenger::class)->sms('0712 345 678', 'Your code is 123456', 'signup_code', self::TENANT)->refresh();

        $this->assertSame('sms', $log->channel);
        $this->assertSame('+254712345678', $log->recipient);
        $this->assertSame('sent', $log->status);
        $this->assertStringStartsWith('log-', $log->provider_id);
    }

    public function test_an_invalid_phone_is_logged_as_failed_without_sending(): void
    {
        $log = app(Messenger::class)->sms('not-a-phone', 'Hi', 'test', self::TENANT);

        $this->assertSame('failed', $log->status);
        $this->assertNotNull($log->error);
    }

    public function test_to_shop_uses_the_business_email_and_phone(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        DB::connection('central')->table('tenants')->insertOrIgnore(['id' => self::TENANT, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::query()->create([
            'tenant_id' => self::TENANT,
            'business_name' => 'Messaging Test Shop',
            'business_type_id' => 1,
            'business_category_id' => 1,
            'business_phone' => '0722000111',
            'business_email' => 'shop.messaging-test@example.com',
        ]);
        DB::connection('central')->statement('SET foreign_key_checks = 1');

        $sent = app(Messenger::class)->toShop(self::TENANT, new PlainMessageMail('Low balance', ['Top up']), 'low_balance', 'Poachy: your balance is low.');

        $this->assertSame('shop.messaging-test@example.com', $sent['email']->recipient);
        $this->assertSame('+254722000111', $sent['sms']->recipient);
        $this->assertSame(2, MessageLog::query()->where('tenant_id', self::TENANT)->count());
    }

    public function test_to_shop_without_business_details_sends_nothing(): void
    {
        $sent = app(Messenger::class)->toShop('no-such-tenant', new PlainMessageMail('x', ['y']), 'k', 'v');

        $this->assertSame(['email' => null, 'sms' => null], $sent);
    }

    public function test_africas_talking_driver_sends_the_documented_request(): void
    {
        Config::set('messaging.sms.africastalking', ['username' => 'sandbox', 'api_key' => 'at_key', 'base_url' => 'https://api.sandbox.africastalking.com']);
        Http::fake(['api.sandbox.africastalking.com/*' => Http::response(['SMSMessageData' => ['Message' => 'Sent to 1/1', 'Recipients' => [
            ['statusCode' => 101, 'number' => '+254712345678', 'status' => 'Success', 'messageId' => 'ATXid_1'],
        ]]], 201)]);

        $result = (new AfricasTalkingSmsGateway)->send('+254712345678', 'Hello');

        $this->assertSame(['id' => 'ATXid_1', 'status' => 'Success'], $result);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.sandbox.africastalking.com/version1/messaging'
            && $r->hasHeader('apiKey', 'at_key') && $r['to'] === '+254712345678' && $r['username'] === 'sandbox' && $r['from'] === 'POACHY');
    }

    public function test_africas_talking_rejections_raise(): void
    {
        Http::fake(['*' => Http::response(['SMSMessageData' => ['Message' => 'Sent to 0/1', 'Recipients' => [
            ['statusCode' => 403, 'number' => '+254712345678', 'status' => 'InvalidPhoneNumber', 'messageId' => 'None'],
        ]]], 201)]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('InvalidPhoneNumber');

        (new AfricasTalkingSmsGateway)->send('+254712345678', 'Hello');
    }
}
