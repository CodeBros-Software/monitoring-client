<?php

namespace CodeBros\MonitoringClient\Recorders;

use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Laravel\Pulse\Pulse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class OutgoingRequests
{
    public function __construct(protected Pulse $pulse) {}

    public function register(callable $record, Application $app): void
    {
        $app->afterResolving(
            Factory::class,
            fn (Factory $factory) => $factory->globalMiddleware($this->middleware($record)),
        );
    }

    protected function middleware(callable $record): callable
    {
        return fn (callable $handler) => function (RequestInterface $request, array $options) use ($handler, $record) {
            $startedAt = microtime(true);

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request, $startedAt, $record) {
                    $record($request, $startedAt, $response->getStatusCode());

                    return $response;
                },
                function (Throwable $exception) use ($request, $startedAt, $record) {
                    $record($request, $startedAt, null);

                    return new RejectedPromise($exception);
                },
            );
        };
    }

    public function record(RequestInterface $request, float $startedAt, ?int $status): void
    {
        $uri = (string) $request->getUri();

        if ($this->shouldIgnore($uri)) {
            return;
        }

        $this->pulse->record(
            type: 'outgoing_request',
            key: json_encode([
                'host' => parse_url($uri, PHP_URL_HOST) ?? $uri,
                'method' => $request->getMethod(),
                'status' => $status,
            ], JSON_THROW_ON_ERROR),
            value: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }

    /**
     * Skip calls to the monitoring ingest endpoint itself to avoid a feedback loop.
     */
    protected function shouldIgnore(string $uri): bool
    {
        $endpoint = config('monitoring-client.endpoint');

        return is_string($endpoint) && $endpoint !== '' && str_starts_with($uri, $endpoint);
    }
}
