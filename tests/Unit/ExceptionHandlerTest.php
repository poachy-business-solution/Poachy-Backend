<?php

namespace Tests\Unit;

use App\Exceptions\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ExceptionHandlerTest extends TestCase
{
    private function apiRequest(): Request
    {
        return Request::create('/api/v1/tenant/expenses/1/approve', 'POST');
    }

    public function test_runtime_exception_maps_to_422_with_its_own_message(): void
    {
        $response = (new ExceptionHandler)->handleApiException(
            $this->apiRequest(),
            new RuntimeException('Only pending expenses can be approved.')
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Only pending expenses can be approved.', $response->getData(true)['message']);
    }

    public function test_invalid_argument_exception_maps_to_422_with_its_own_message(): void
    {
        $response = (new ExceptionHandler)->handleApiException(
            $this->apiRequest(),
            new InvalidArgumentException('Store not found or inactive.')
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Store not found or inactive.', $response->getData(true)['message']);
    }

    /**
     * ModelNotFoundException is itself a RuntimeException subtype — it must
     * keep resolving through its own, more specific handler (404, generic
     * message) rather than falling into the new business-rule 422 branch.
     */
    public function test_model_not_found_exception_still_maps_to_404(): void
    {
        $response = (new ExceptionHandler)->handleApiException(
            $this->apiRequest(),
            new ModelNotFoundException('No query results for model [App\\Models\\Tenant\\Expense].')
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    /**
     * NotFoundHttpException (like the rest of the HttpException family) is
     * also a RuntimeException subtype — same guard as above.
     */
    public function test_not_found_http_exception_still_maps_to_404(): void
    {
        $response = (new ExceptionHandler)->handleApiException(
            $this->apiRequest(),
            new NotFoundHttpException
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_unrecognized_exception_still_maps_to_500(): void
    {
        $response = (new ExceptionHandler)->handleApiException(
            $this->apiRequest(),
            new \Exception('Something broke')
        );

        $this->assertSame(500, $response->getStatusCode());
    }
}
