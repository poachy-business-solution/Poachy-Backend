<?php

namespace App\Http\Requests\Internal\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Base for private admin API requests; the internal.admin middleware has already checked the caller. */
abstract class InternalAdminRequest extends FormRequest
{
    /** Money actions need an Idempotency-Key header. */
    protected bool $needsIdempotencyKey = false;

    public function authorize(): bool
    {
        return true;
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->needsIdempotencyKey) {
                return;
            }
            $key = (string) $this->header('Idempotency-Key');
            if ($key === '' || strlen($key) > 100) {
                $validator->errors()->add('idempotency_key', 'An Idempotency-Key header (up to 100 characters) is required.');
            }
        }];
    }

    public function idempotencyKey(): string
    {
        return (string) $this->header('Idempotency-Key');
    }
}
