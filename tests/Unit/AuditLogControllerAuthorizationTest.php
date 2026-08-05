<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Tenant\Audit\AuditLogController;
use App\Http\Requests\Tenant\Audit\AuditLogRequest;
use ReflectionMethod;
use ReflectionParameter;
use Tests\TestCase;

class AuditLogControllerAuthorizationTest extends TestCase
{
    /**
     * availableFilters() previously took no parameters at all, so — unlike its
     * siblings (index/statistics/groupedSummary/recentActivity) — the
     * owner|manager role check inside AuditLogRequest never ran for it.
     */
    public function test_available_filters_type_hints_the_authorizing_form_request(): void
    {
        $parameters = (new ReflectionMethod(AuditLogController::class, 'availableFilters'))->getParameters();

        $requestParameter = current(array_filter(
            $parameters,
            fn (ReflectionParameter $p) => $p->getType() && $p->getType()->getName() === AuditLogRequest::class
        ));

        $this->assertNotFalse($requestParameter);
    }
}
