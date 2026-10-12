<?php

namespace App\Http\Requests\Internal\Admin;

use App\Models\TenantVerification;
use Illuminate\Validation\Validator;

/** Upload one verification check (multipart): a file, a value, or both (KRA PIN + certificate). */
class VerificationCheckRequest extends InternalAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'check' => ['required', 'in:'.implode(',', array_keys(TenantVerification::CHECKS))],
            'file' => ['nullable', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
            // KRA PIN: letter, 9 digits, letter (e.g. A012345678Z). Location: "lat,lng" inside Kenya.
            'value' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $v) {
            $check = $this->input('check');
            $value = trim((string) $this->input('value'));

            if (in_array($check, TenantVerification::FILE_CHECKS, true) && ! $this->hasFile('file')) {
                $v->errors()->add('file', 'Attach a photo or PDF for this check.');
            }
            if ($check === 'kra_pin' && ! preg_match('/^[A-Z]\d{9}[A-Z]$/', strtoupper($value))) {
                $v->errors()->add('value', 'Enter the KRA PIN, e.g. A012345678Z.');
            }
            if ($check === 'location') {
                $parts = array_map('trim', explode(',', $value));
                $ok = count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])
                    && (float) $parts[0] >= -4.8 && (float) $parts[0] <= 5.1 && (float) $parts[1] >= 33.9 && (float) $parts[1] <= 42.0;
                if (! $ok) {
                    $v->errors()->add('value', 'Enter the GPS pin as "latitude,longitude" inside Kenya, e.g. -1.2833,36.8167.');
                }
            }
        }];
    }

    public function normalisedValue(): ?string
    {
        $value = trim((string) $this->input('value'));

        return match ($this->input('check')) {
            'kra_pin' => strtoupper($value),
            'location' => implode(',', array_map(fn ($p) => (string) round((float) trim($p), 6), explode(',', $value))),
            default => null,
        };
    }
}
