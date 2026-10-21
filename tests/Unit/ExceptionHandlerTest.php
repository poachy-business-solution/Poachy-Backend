<?php

namespace Tests\Unit;

use App\Exceptions\ExceptionHandler;
use App\Exceptions\SmsException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use PDOException;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;
use Throwable;

class ExceptionHandlerTest extends TestCase
{
    private function apiRequest(): Request
    {
        return Request::create('/api/v1/tenant/expenses/1/approve', 'POST');
    }

    /** As if thrown at this path (Poachy's services, or a library under vendor/). */
    private function thrownAt(Throwable $e, string $file): Throwable
    {
        (new ReflectionProperty(\Exception::class, 'file'))->setValue($e, $file);

        return $e;
    }

    private function fromPoachy(Throwable $e): Throwable
    {
        return $this->thrownAt($e, app_path('Services/Tenant/ExpenseService.php'));
    }

    public function test_runtime_exception_maps_to_422_with_its_own_message(): void
    {
        $response = (new ExceptionHandler)->handleApiException(
            $this->apiRequest(),
            $this->fromPoachy(new RuntimeException('Only pending expenses can be approved.'))
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Only pending expenses can be approved.', $response->getData(true)['message']);
    }

    public function test_invalid_argument_exception_maps_to_422_with_its_own_message(): void
    {
        $response = (new ExceptionHandler)->handleApiException(
            $this->apiRequest(),
            $this->fromPoachy(new InvalidArgumentException('Store not found or inactive.'))
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

    public function test_poachys_own_exception_classes_keep_their_message(): void
    {
        $response = (new ExceptionHandler)->handleApiException($this->apiRequest(), new SmsException('Could not send the SMS. Try again.'));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Could not send the SMS. Try again.', $response->getData(true)['message']);
    }

    /** Library errors are RuntimeExceptions too, but their messages are technical: never shown. */
    public function test_a_library_runtime_exception_is_a_plain_500_without_its_message(): void
    {
        config(['app.debug' => false]);

        foreach ([
            new PDOException('SQLSTATE[HY000] [2002] Connection refused (host mysql, user poachy)'),
            $this->thrownAt(new RuntimeException('No application encryption key has been specified.'), base_path('vendor/laravel/framework/src/X.php')),
            $this->thrownAt(new InvalidArgumentException('Could not parse \'xyz\': Failed to parse time string'), base_path('vendor/nesbot/carbon/src/X.php')),
        ] as $e) {
            $response = (new ExceptionHandler)->handleApiException($this->apiRequest(), $e);

            $this->assertSame(500, $response->getStatusCode(), $e->getMessage());
            $this->assertSame('An unexpected error occurred.', $response->getData(true)['message']);
        }
    }

    public function test_debug_is_off_in_the_deployment_template(): void
    {
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', file_get_contents(base_path('.env.example')));
    }
}
