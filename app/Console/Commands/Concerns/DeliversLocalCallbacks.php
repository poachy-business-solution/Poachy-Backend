<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Delivers a provider callback (M-Pesa, Paystack) to this app's own route in-process, exactly as
 * the provider would POST it — no network, no tunnel. Used by the simulate commands.
 */
trait DeliversLocalCallbacks
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array{status: int, body: mixed}
     */
    protected function deliverCallback(string $url, array $payload, array $headers = [], ?string $rawBody = null): array
    {
        $parts = parse_url($url);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        $server = ['HTTP_HOST' => $parts['host'] ?? 'localhost', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $request = Request::create($path, 'POST', [], [], [], $server, $rawBody ?? json_encode($payload));

        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $content = $response->getContent();

        return ['status' => $response->getStatusCode(), 'body' => json_decode((string) $content, true) ?? $content];
    }

    /** A configured URL, or the app's own default route path when the env var is not set. */
    protected function urlOr(?string $configured, string $defaultPath): string
    {
        return $configured ?: rtrim((string) config('app.url'), '/').$defaultPath;
    }
}
