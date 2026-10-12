<?php

namespace App\Services\Central\Signup;

use App\Helpers\PhoneNumber;
use App\Jobs\Central\ProvisionSignupJob;
use App\Mail\Central\Signup\SignupCodeMail;
use App\Models\BusinessCategory;
use App\Models\BusinessDetail;
use App\Models\Domain;
use App\Models\SignupRequest;
use App\Services\Shared\Messaging\Messenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Self sign-up: details → phone + email codes → workspace name + password → queued provisioning.
 *
 * Abuse controls: Turnstile and rate limits at the route; one trial per phone and per email
 * (an existing shop or a finished sign-up with the same phone/email is refused); codes are
 * HMAC-hashed, expire after 15 minutes and allow 5 attempts.
 */
class SignupService
{
    public function __construct(private Messenger $messenger) {}

    /**
     * @param  array{shop_name: string, owner_name: string, phone: string, email: string, town: string, branches?: int, business_category_id?: int|null}  $details
     */
    public function start(array $details, ?string $ip): SignupRequest
    {
        $phone = PhoneNumber::normalize($details['phone']);
        $email = Str::lower(trim($details['email']));
        $this->ensureNoExistingShop($phone, $email);

        $signup = SignupRequest::query()->create([
            'shop_name' => trim($details['shop_name']),
            'owner_name' => trim($details['owner_name']),
            'phone' => $phone,
            'email' => $email,
            'town' => trim($details['town']),
            'branches' => (int) ($details['branches'] ?? 1),
            'business_category_id' => $details['business_category_id'] ?? null,
            'status' => SignupRequest::PENDING,
            'ip' => $ip,
            'expires_at' => now()->addHours((int) config('signup.expires_after_hours', 24)),
        ]);

        $this->sendCodes($signup);

        return $signup->fresh();
    }

    /**
     * A shop opened by Poachy staff: same checks and provisioning as self sign-up, but phone and
     * email are taken as checked by staff and the owner gets a temporary password by email
     * (the app asks them to choose their own on first sign-in).
     *
     * @param  array{shop_name: string, owner_name: string, phone: string, email: string, town: string, workspace: string, branches?: int, business_category_id?: int|null}  $details
     */
    public function startByAdmin(array $details, string $adminId): SignupRequest
    {
        $phone = PhoneNumber::normalize($details['phone']);
        $email = Str::lower(trim($details['email']));
        $this->ensureNoExistingShop($phone, $email);

        $check = $this->workspaceAvailability($details['workspace']);
        if (! $check['available']) {
            throw ValidationException::withMessages(['workspace' => [$check['reason']]]);
        }

        $signup = DB::connection('central')->transaction(function () use ($details, $phone, $email, $check, $adminId) {
            $busy = SignupRequest::query()->where('workspace', $check['name'])
                ->whereIn('status', [SignupRequest::PROVISIONING, SignupRequest::READY])->lockForUpdate()->exists();
            if ($busy) {
                throw ValidationException::withMessages(['workspace' => ['That name is taken.']]);
            }

            return SignupRequest::query()->create([
                'shop_name' => trim($details['shop_name']),
                'owner_name' => trim($details['owner_name']),
                'phone' => $phone,
                'email' => $email,
                'town' => trim($details['town']),
                'branches' => (int) ($details['branches'] ?? 1),
                'business_category_id' => $details['business_category_id'] ?? null,
                'workspace' => $check['name'],
                'status' => SignupRequest::PROVISIONING,
                'source' => SignupRequest::SOURCE_ADMIN,
                'created_by_admin' => $adminId,
                'expires_at' => now()->addDay(),
            ]);
        });

        ProvisionSignupJob::dispatch($signup->id);

        return $signup->fresh();
    }

    /** Send fresh codes (also used for "resend"). */
    public function sendCodes(SignupRequest $signup): void
    {
        if ($signup->code_sends >= (int) config('signup.max_code_sends', 3)) {
            throw ValidationException::withMessages(['signup' => ['Too many codes sent. Start again in a while.']]);
        }

        $phoneCode = $this->code();
        $emailCode = $this->code();
        $minutes = (int) config('signup.code_ttl_minutes', 15);

        $signup->forceFill([
            'phone_code_hash' => $this->hashCode($signup, 'phone', $phoneCode),
            'email_code_hash' => $this->hashCode($signup, 'email', $emailCode),
            'codes_expire_at' => now()->addMinutes($minutes),
            'code_attempts' => 0,
            'code_sends' => $signup->code_sends + 1,
        ])->save();

        $this->messenger->sms($signup->phone, "Poachy sign-up code: {$phoneCode}. It works for {$minutes} minutes. Don't share it.", 'signup_phone_code');
        $this->messenger->email($signup->email, new SignupCodeMail($signup->owner_name, $emailCode, $minutes), null, 'signup_email_code');
    }

    /** Check both codes; on success the sign-up is verified. */
    public function verify(SignupRequest $signup, string $phoneCode, string $emailCode): SignupRequest
    {
        $this->ensureUsable($signup);

        if ($signup->isVerified()) {
            return $signup;
        }

        if ($signup->codes_expire_at === null || $signup->codes_expire_at->isPast()) {
            throw ValidationException::withMessages(['codes' => ['These codes have expired. Ask for new ones.']]);
        }

        if ($signup->code_attempts >= (int) config('signup.max_code_attempts', 5)) {
            throw ValidationException::withMessages(['codes' => ['Too many wrong codes. Ask for new ones.']]);
        }

        $phoneOk = hash_equals((string) $signup->phone_code_hash, $this->hashCode($signup, 'phone', $phoneCode));
        $emailOk = hash_equals((string) $signup->email_code_hash, $this->hashCode($signup, 'email', $emailCode));

        if (! $phoneOk || ! $emailOk) {
            $signup->increment('code_attempts');
            throw ValidationException::withMessages(array_filter([
                'phone_code' => $phoneOk ? null : ['That SMS code is not right.'],
                'email_code' => $emailOk ? null : ['That email code is not right.'],
            ]));
        }

        $signup->forceFill([
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'phone_code_hash' => null,
            'email_code_hash' => null,
            'status' => SignupRequest::VERIFIED,
        ])->save();

        return $signup;
    }

    /**
     * Is this workspace name free? Returns a reason when not, and a suggestion.
     *
     * @return array{name: string, available: bool, reason: string|null, suggestion: string|null}
     */
    public function workspaceAvailability(string $name): array
    {
        $name = Str::lower(trim($name));
        $reason = match (true) {
            ! preg_match('/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/', $name) || str_contains($name, '--') => 'Use 3–30 letters, numbers or single hyphens, starting and ending with a letter or number.',
            in_array($name, config('signup.reserved', []), true) => 'That name is reserved.',
            $this->isTaken($name) => 'That name is taken.',
            default => null,
        };

        return [
            'name' => $name,
            'available' => $reason === null,
            'reason' => $reason,
            'suggestion' => $reason === null ? null : $this->suggest($name),
        ];
    }

    /** "Mama Njeri's Shop" → "mama-njeris-shop" (free, or with a number). */
    public function suggest(string $from): string
    {
        $base = Str::of($from)->lower()->ascii()->replace("'", '')->slug('-')->limit(26, '')->trim('-')->toString();
        $base = strlen($base) >= 3 ? $base : 'shop-'.$base;
        $base = in_array($base, config('signup.reserved', []), true) ? "{$base}-shop" : $base;

        $candidate = $base;
        for ($i = 2; $this->isTaken($candidate) && $i < 100; $i++) {
            $candidate = "{$base}-{$i}";
        }

        return $candidate;
    }

    /**
     * Choose the workspace + password, accept terms, and queue provisioning.
     */
    public function complete(SignupRequest $signup, string $workspace, string $password): SignupRequest
    {
        $this->ensureUsable($signup);

        if (! $signup->isVerified()) {
            throw ValidationException::withMessages(['codes' => ['Confirm your phone and email first.']]);
        }

        if (in_array($signup->status, [SignupRequest::PROVISIONING, SignupRequest::READY], true)) {
            return $signup;
        }

        // Re-check at the last moment: another shop may have opened with the same phone/email.
        $this->ensureNoExistingShop($signup->phone, $signup->email, $signup->id);

        $check = $this->workspaceAvailability($workspace);
        if (! $check['available']) {
            throw ValidationException::withMessages(['workspace' => [$check['reason']]]);
        }

        $claimed = DB::connection('central')->transaction(function () use ($signup, $check, $password) {
            // A workspace reserved by another sign-up that is being set up right now is taken too.
            $busy = SignupRequest::query()->whereKeyNot($signup->id)->where('workspace', $check['name'])
                ->whereIn('status', [SignupRequest::PROVISIONING, SignupRequest::READY])->lockForUpdate()->exists();
            if ($busy) {
                return false;
            }

            $signup->forceFill([
                'workspace' => $check['name'],
                'password_hash' => Hash::make($password),
                'terms_accepted_at' => now(),
                'status' => SignupRequest::PROVISIONING,
                'failure_reason' => null,
            ])->save();

            return true;
        });

        if (! $claimed) {
            throw ValidationException::withMessages(['workspace' => ['That name is taken.']]);
        }

        ProvisionSignupJob::dispatch($signup->id);

        return $signup->fresh();
    }

    public function businessCategoryIdFor(SignupRequest $signup): int
    {
        $id = $signup->business_category_id
            ?? BusinessCategory::query()->where('name', config('signup.default_business_category'))->value('id')
            ?? BusinessCategory::query()->orderBy('id')->value('id');

        return (int) $id;
    }

    private function isTaken(string $name): bool
    {
        $domains = array_map(fn (string $t) => str_replace('{slug}', $name, $t), config('signup.domains', []));

        return Domain::query()->whereIn('domain', $domains)->exists()
            || Domain::query()->where('domain', 'like', $name.'.%')->exists();
    }

    private function ensureNoExistingShop(string $phone, string $email, ?string $ignoreSignup = null): void
    {
        $phoneVariants = [$phone, '+'.$phone, '0'.substr($phone, 3)];

        $shop = BusinessDetail::query()->whereIn('business_phone', $phoneVariants)->orWhere('business_email', $email)->exists();
        $signup = SignupRequest::query()
            ->when($ignoreSignup, fn ($q) => $q->whereKeyNot($ignoreSignup))
            ->whereIn('status', [SignupRequest::PROVISIONING, SignupRequest::READY])
            ->where(fn ($q) => $q->where('phone', $phone)->orWhere('email', $email))
            ->exists();

        if ($shop || $signup) {
            throw ValidationException::withMessages(['phone' => ['This phone number or email already has a Poachy shop. Sign in instead, or contact us.']]);
        }
    }

    private function ensureUsable(SignupRequest $signup): void
    {
        if ($signup->isExpired() && $signup->status !== SignupRequest::READY) {
            throw ValidationException::withMessages(['signup' => ['This sign-up has expired. Please start again.']]);
        }
    }

    private function code(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hashCode(SignupRequest $signup, string $channel, string $code): string
    {
        return hash_hmac('sha256', "{$signup->id}|{$channel}|".trim($code), (string) config('app.key'));
    }
}
