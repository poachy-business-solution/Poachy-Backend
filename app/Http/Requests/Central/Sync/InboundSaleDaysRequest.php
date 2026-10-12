<?php

namespace App\Http\Requests\Central\Sync;

use App\Models\Tenant;
use App\Services\Tenant\Billing\SaleDaysReporter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A shop's sale-days report (tenant → central, server to server).
 * Authorised by the shared central API token (constant-time compare; refused when unset).
 */
class InboundSaleDaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expected = (string) config('services.central_api.token');
        $provided = (string) $this->bearerToken();

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = now('Africa/Nairobi');
        // One extra day of slack on each side for clock skew around midnight.
        $earliest = $today->copy()->subDays(SaleDaysReporter::LOOK_BACK_DAYS + 1)->toDateString();
        $latest = $today->copy()->addDay()->toDateString();

        return [
            'tenant_id' => ['required', 'string', 'max:36'],
            'days' => ['present', 'array', 'max:500'],
            'days.*.store_id' => ['required', 'integer', 'min:1'],
            'days.*.date' => ['required', 'date_format:Y-m-d', "after_or_equal:{$earliest}", "before_or_equal:{$latest}"],
            'days.*.sales_count' => ['required', 'integer', 'min:1'],
            'days.*.is_main_branch' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $tenantId = (string) $this->input('tenant_id');

            if ($this->header('X-Tenant-ID') !== $tenantId) {
                $validator->errors()->add('tenant_id', 'The tenant does not match the X-Tenant-ID header.');
            } elseif (! Tenant::query()->whereKey($tenantId)->exists()) {
                $validator->errors()->add('tenant_id', 'Unknown tenant.');
            }
        }];
    }
}
