<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Tenant\Expenses\ExpenseController;
use App\Http\Requests\Tenant\Expense\ApproveExpenseRequest;
use App\Http\Requests\Tenant\Expense\RejectExpenseRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionParameter;
use Tests\TestCase;

class ExpenseControllerAuthorizationTest extends TestCase
{
    public static function approvalActions(): array
    {
        return [
            'approve' => ['approve', ApproveExpenseRequest::class],
            'reject' => ['reject', RejectExpenseRequest::class],
        ];
    }

    /**
     * approve() previously took only `int $id`, so its FormRequest — and the
     * manage-expenses check inside it — was never instantiated by Laravel's
     * container, letting any authenticated user approve expenses.
     */
    #[DataProvider('approvalActions')]
    public function test_action_type_hints_its_authorizing_form_request(string $method, string $expectedRequest): void
    {
        $parameters = (new ReflectionMethod(ExpenseController::class, $method))->getParameters();

        $requestParameter = current(array_filter(
            $parameters,
            fn (ReflectionParameter $p) => $p->getType() && $p->getType()->getName() === $expectedRequest
        ));

        $this->assertNotFalse(
            $requestParameter,
            "{$method}() must type-hint {$expectedRequest} for its authorization check to run."
        );
    }
}
