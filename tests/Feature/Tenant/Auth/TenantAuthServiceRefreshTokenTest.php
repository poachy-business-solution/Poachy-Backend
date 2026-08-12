<?php

namespace Tests\Feature\Tenant\Auth;

use App\Models\Tenant\TenantRefreshToken;
use App\Models\Tenant\User as TenantUser;
use App\Services\Tenant\Auth\TenantAuthService;
use App\Services\Tenant\Auth\TenantOtpService;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantAuthorization;
use Tests\TestCase;

class TenantAuthServiceRefreshTokenTest extends TestCase
{
    use InteractsWithTenantAuthorization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantAuthorization();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantAuthorization();
        Mockery::close();

        parent::tearDown();
    }

    public function test_verify_otp_returns_access_and_refresh_tokens(): void
    {
        $user = $this->makeTenantUserWithRole('cashier', ['create-sales']);
        $service = $this->makeServiceWithVerifiedUser($user);

        $result = $service->verifyOtpAndLogin($user->email, '1234567');

        $this->assertNotEmpty($result['token']);
        $this->assertNotEmpty($result['refresh_token']);
        $this->assertTrue($result['token_expires_at']->isFuture());
        $this->assertTrue($result['refresh_token_expires_at']->greaterThan($result['token_expires_at']));
        $this->assertSame(['cashier'], $result['user']->roles->pluck('name')->all());

        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame(1, TenantRefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
        $this->assertDatabaseMissing('tenant_refresh_tokens', [
            'token_hash' => $result['refresh_token'],
        ], 'tenant');
    }

    public function test_refresh_rotates_refresh_token_and_replaces_access_token(): void
    {
        $user = $this->makeTenantUser();
        $service = $this->makeServiceWithVerifiedUser($user);
        $login = $service->verifyOtpAndLogin($user->email, '1234567');
        $oldAccessTokenId = $this->accessTokenId($login['token']);

        $refreshed = $service->refreshToken($login['refresh_token'], 'field-phone');

        $this->assertNotSame($login['token'], $refreshed['token']);
        $this->assertNotSame($login['refresh_token'], $refreshed['refresh_token']);
        $this->assertSame(0, $user->tokens()->whereKey($oldAccessTokenId)->count());
        $this->assertSame(1, $user->tokens()->count());

        $oldRefresh = TenantRefreshToken::where('personal_access_token_id', $oldAccessTokenId)->firstOrFail();

        $this->assertNotNull($oldRefresh->revoked_at);
        $this->assertNotNull($oldRefresh->replaced_by_id);
        $this->assertSame(1, TenantRefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
    }

    public function test_reusing_a_rotated_refresh_token_revokes_the_token_family(): void
    {
        $user = $this->makeTenantUser();
        $service = $this->makeServiceWithVerifiedUser($user);
        $login = $service->verifyOtpAndLogin($user->email, '1234567');

        $service->refreshToken($login['refresh_token']);

        try {
            $service->refreshToken($login['refresh_token']);
            $this->fail('Expected reused refresh token to be rejected.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['Invalid or expired refresh token. Please sign in again.'],
                $e->errors()['refresh_token']
            );
        }

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, TenantRefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
    }

    public function test_logout_revokes_refresh_token_for_current_access_token(): void
    {
        $user = $this->makeTenantUser();
        $service = $this->makeServiceWithVerifiedUser($user);
        $login = $service->verifyOtpAndLogin($user->email, '1234567');
        $accessToken = PersonalAccessToken::findToken($login['token']);

        $user->withAccessToken($accessToken);

        $service->logout($user);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, TenantRefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
    }

    public function test_update_password_revokes_access_and_refresh_tokens(): void
    {
        $user = $this->makeTenantUser(['password' => bcrypt('OldPassword123!')]);
        $service = $this->makeServiceWithVerifiedUser($user);

        $service->verifyOtpAndLogin($user->email, '1234567');
        $service->updatePassword($user, 'OldPassword123!', 'NewPassword123!');

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, TenantRefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->count());
    }

    protected function makeServiceWithVerifiedUser(TenantUser $user): TenantAuthService
    {
        $otpService = Mockery::mock(TenantOtpService::class);
        $otpService->shouldReceive('verify')
            ->with($user->email, '1234567', 'login')
            ->andReturn($user);

        return new TenantAuthService($otpService);
    }

    protected function accessTokenId(string $plainTextToken): int
    {
        [$id] = explode('|', $plainTextToken, 2);

        return (int) $id;
    }
}
