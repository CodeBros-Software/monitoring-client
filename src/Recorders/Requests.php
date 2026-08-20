<?php

namespace CodeBros\MonitoringClient\Recorders;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Route;
use Laravel\Pulse\Pulse;

class Requests
{
    /**
     * @var class-string
     */
    public string $listen = RequestHandled::class;

    public function __construct(protected Pulse $pulse) {}

    public function record(RequestHandled $event): void
    {
        $route = $event->request->route();

        if (! $route instanceof Route) {
            return;
        }

        $startedAt = defined('LARAVEL_START') ? LARAVEL_START : $event->request->server('REQUEST_TIME_FLOAT', microtime(true));

        $this->pulse->record(
            type: 'request',
            key: json_encode([
                'route' => $route->uri(),
                'method' => $event->request->method(),
                'status' => $event->response->getStatusCode(),
            ], JSON_THROW_ON_ERROR),
            value: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }
}
