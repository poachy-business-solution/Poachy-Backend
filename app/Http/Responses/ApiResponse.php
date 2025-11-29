<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ApiResponse
{
    // Create a successful JSON response
    public static function success(
        string $message = 'Operation successful',
        mixed $data = null,
        int $status = 200,
        array $headers = []
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $response['meta'] = self::generateMeta();

        return response()->json($response, $status, $headers);
    }

    // Create an error JSON response
    public static function error(
        string $message = 'Operation failed',
        mixed $errors = null,
        int $status = 400,
        array $headers = []
    ): JsonResponse {
        $structuredErrors = null;

        if ($errors !== null) {
            // Ensure errors is always an array
            $structuredErrors = is_array($errors) ? $errors : ['message' => (string)$errors];
        }

        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($structuredErrors) {
            $response['errors'] = $structuredErrors;
        }

        $response['meta'] = self::generateMeta();

        return response()->json($response, $status, $headers);
    }

    // Create a validation error response (422)
    public static function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::error($message, $errors, 422);
    }

    // Create a created response (201)
    public static function created(
        string $message = 'Resource created successfully',
        mixed $data = null,
        array $headers = []
    ): JsonResponse {
        return self::success($message, $data, 201, $headers);
    }

    // Create a no content response (204)
    public static function noContent(array $headers = []): Response
    {
        return response()->noContent($headers);
    }

    // Create an unauthorized response (401)
    public static function unauthorized(string $message = 'Unauthorized', mixed $errors = null): JsonResponse
    {
        return self::error($message, $errors, 401);
    }

    // Create a forbidden response (403)
    public static function forbidden(string $message = 'Forbidden', mixed $errors = null): JsonResponse
    {
        return self::error($message, $errors, 403);
    }

    // Create a not found response (404)
    public static function notFound(string $message = 'Resource not found', mixed $errors = null): JsonResponse
    {
        return self::error($message, $errors, 404);
    }

    // Create a conflict response (409)
    public static function conflict(string $message = 'Resource conflict', mixed $errors = null): JsonResponse
    {
        return self::error($message, $errors, 409);
    }

    // Create a rate limited response (429)
    public static function rateLimited(string $message = 'Too many requests', mixed $errors = null): JsonResponse
    {
        $headers = [];
        if (is_array($errors) && isset($errors['retry_after'])) {
            $headers['Retry-After'] = $errors['retry_after'];
        }

        return self::error($message, $errors, 429, $headers);
    }

    // Create a server error response (500)
    public static function serverError(string $message = 'Internal server error', mixed $errors = null): JsonResponse
    {
        return self::error($message, $errors, 500);
    }

    // Handle API Resource responses
    public static function resource(
        JsonResource|ResourceCollection $resource,
        string $message = 'Operation successful',
        int $status = 200
    ): JsonResponse {
        $data = $resource->response()->getData(true);
        return self::success($message, $data, $status);
    }

    // Handle paginated responses
    public static function paginated(ResourceCollection $collection, string $message = 'Data retrieved successfully'): JsonResponse
    {
        $pagination = [
            'current_page' => $collection->resource->currentPage(),
            'last_page' => $collection->resource->lastPage(),
            'per_page' => $collection->resource->perPage(),
            'total' => $collection->resource->total(),
            'from' => $collection->resource->firstItem(),
            'to' => $collection->resource->lastItem(),
        ];

        $data = $collection->response()->getData(true)['data'] ?? [];

        return self::success(
            $message,
            ['data' => $data, 'pagination' => $pagination]
        );
    }

    // Generate meta information for all responses
    protected static function generateMeta(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-ID') ?? (string) str()->uuid(),
            'tenant_id' => tenant()?->id ?? null,
            'tenant_name' => tenant()?->name ?? null,
        ];
    }
}
