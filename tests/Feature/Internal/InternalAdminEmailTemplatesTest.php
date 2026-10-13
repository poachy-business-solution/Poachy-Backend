<?php

namespace Tests\Feature\Internal;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/** GET /api/internal/admin/emails/templates(/{key}/preview): the emails Poachy sends, rendered with sample data, nothing sent. */
class InternalAdminEmailTemplatesTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    private const TOKEN = 'test-internal-admin-token-0123456789abcdef';

    public function test_templates_are_listed_and_rendered_for_support_but_not_finance(): void
    {
        $this->configureTenantHttpDatabases();
        Config::set('internal_admin.token', self::TOKEN);
        Config::set('internal_admin.allowed_ips', ['127.0.0.1']);
        Mail::fake();
        $headers = fn (string $role) => ['Authorization' => 'Bearer '.self::TOKEN, 'X-Admin-Id' => '3', 'X-Admin-Role' => $role];

        $list = $this->getJson('/api/internal/admin/emails/templates', $headers('support'))->assertOk()->json('data');
        $this->assertSame(['welcome', 'receipt', 'low_balance', 'grace', 'limited', 'restored', 'trial_ending', 'payment_link', 'signup_code'], array_column($list, 'key'));

        $preview = $this->getJson('/api/internal/admin/emails/templates/low_balance/preview', $headers('support'))->assertOk()->json('data');
        $this->assertSame('Top up soon: Poachy balance is running low', $preview['subject']);
        $this->assertStringContainsString('Mama Njeri Duka', $preview['html']);
        $this->assertStringContainsString('POA00042', $preview['html']);

        foreach (array_column($list, 'key') as $key) {
            $this->assertNotEmpty($this->getJson("/api/internal/admin/emails/templates/{$key}/preview", $headers('super'))->assertOk()->json('data.html'), $key);
        }
        Mail::assertNothingSent();

        $this->getJson('/api/internal/admin/emails/templates/no-such/preview', $headers('support'))->assertNotFound();
        $this->getJson('/api/internal/admin/emails/templates', $headers('finance'))->assertForbidden();
        $this->getJson('/api/internal/admin/emails/templates')->assertUnauthorized();
    }
}
