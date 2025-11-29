<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ExceptionHandler extends Exception
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        //
    }

    public function render($request, Throwable $e): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions in a standardized JSON format.
     */
    public function handleApiException(Request $request, Throwable $e): JsonResponse
    {
        $this->logException($request, $e);

        return match (true) {
            $e instanceof ValidationException => $this->handleValidationException($e),
            $e instanceof AuthenticationException => $this->handleAuthenticationException($e),
            $e instanceof AccessDeniedHttpException => $this->handleAccessDeniedException($e),
            $e instanceof ModelNotFoundException => $this->handleModelNotFoundException($e),
            $e instanceof NotFoundHttpException => $this->handleNotFoundException(),
            $e instanceof ThrottleRequestsException => $this->handleThrottleException($e),
            $e instanceof TenantCouldNotBeIdentifiedException => $this->handleTenantNotIdentified($e),
            $e instanceof QueryException => $this->handleQueryException($e),
            $e instanceof HttpException => $this->handleHttpException($e),
            default => $this->handleGenericException($e),
        };
    }

    protected function handleValidationException(ValidationException $e): JsonResponse
    {
        return ApiResponse::validationError(
            $e->errors(),
            'The given data was invalid.'
        );
    }

    protected function handleAuthenticationException(AuthenticationException $e): JsonResponse
    {
        return ApiResponse::unauthorized(
            $e->getMessage() ?: 'Unauthenticated.'
        );
    }

    protected function handleAccessDeniedException(AccessDeniedHttpException $e): JsonResponse
    {
        return ApiResponse::forbidden(
            $e->getMessage() ?: 'This action is unauthorized.'
        );
    }

    protected function handleModelNotFoundException(ModelNotFoundException $e): JsonResponse
    {
        return ApiResponse::notFound(
            'The requested resource was not found.'
        );
    }

    protected function handleNotFoundException(): JsonResponse
    {
        return ApiResponse::notFound('Resource not found.');
    }

    protected function handleThrottleException(ThrottleRequestsException $e): JsonResponse
    {
        $retryAfter = $e->getHeaders()['Retry-After'] ?? null;

        return ApiResponse::rateLimited(
            'Too many requests. Please try again later.',
            ['retry_after' => $retryAfter]
        );
    }

    protected function handleTenantNotIdentified(TenantCouldNotBeIdentifiedException $e): JsonResponse
    {
        return ApiResponse::error(
            'Tenant could not be identified from the request.',
            ['hint' => 'Ensure domain or tenant identifier is correct.'],
            400
        );
    }

    protected function handleQueryException(QueryException $e): JsonResponse
    {
        // Handle duplicate key constraint
        if (str_contains($e->getMessage(), 'Duplicate entry')) {
            return ApiResponse::conflict(
                'A record with similar data already exists.'
            );
        }

        return ApiResponse::serverError(
            config('app.debug')
                ? $e->getMessage()
                : 'A database error occurred.'
        );
    }

    protected function handleHttpException(HttpException $e): JsonResponse
    {
        return ApiResponse::error(
            $e->getMessage() ?: $this->defaultHttpMessage($e->getStatusCode()),
            null,
            $e->getStatusCode()
        );
    }

    protected function handleGenericException(Throwable $e): JsonResponse
    {
        return ApiResponse::serverError(
            config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.'
        );
    }

    protected function defaultHttpMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'HTTP Error',
        };
    }

    protected function logException(Request $request, Throwable $e): void
    {
        $requestId = $request->header('X-Request-ID') ?? (string) str()->uuid();
        $request->headers->set('X-Request-ID', $requestId);

        $context = [
            'request_id' => $requestId,
            'tenant_id' => tenant()?->id,
            'tenant_name' => tenant()?->name,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'user_agent' => $request->userAgent(),
        ];

        if ($e instanceof ValidationException) {
            logger()->info('Validation failed', $context + ['errors' => $e->errors()]);
            return;
        }

        logger()->error('Exception occurred', $context + [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
}
