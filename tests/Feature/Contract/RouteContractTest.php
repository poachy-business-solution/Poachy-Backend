<?php

namespace Tests\Feature\Contract;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Item 45: the OpenAPI document (storage/api-docs/api-docs.json, regenerated with
 * `php artisan l5-swagger:generate`) lists every API route — the website's TypeScript types and
 * the app's contract test are built from it, so an undocumented route is an unchecked one.
 */
class RouteContractTest extends TestCase
{
    /** Framework routes that are not part of Poachy's API. */
    private const IGNORED = ['api/documentation', 'api/oauth2-callback', 'api/user'];

    public function test_every_api_route_is_in_the_openapi_document(): void
    {
        $doc = json_decode((string) file_get_contents(storage_path('api-docs/api-docs.json')), true);
        $documented = [];
        foreach ($doc['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[strtoupper($method).' '.self::shape($path)] = true;
            }
        }

        $missing = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => str_starts_with($r->uri(), 'api/') && ! in_array($r->uri(), self::IGNORED, true))
            ->flatMap(fn (Route $r) => collect($r->methods())->reject(fn ($m) => $m === 'HEAD')->map(fn ($m) => $m.' '.self::shape($r->uri())))
            ->reject(fn (string $op) => isset($documented[$op]))
            ->values()->all();

        $this->assertSame([], $missing, 'Undocumented API routes — add @OA annotations and run l5-swagger:generate');
    }

    private static function shape(string $path): string
    {
        return preg_replace('/\{[^}]+\}/', '{}', trim($path, '/'));
    }
}
