<?php

namespace CodeBros\MonitoringClient\Recorders;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Laravel\Pulse\Pulse;
use Throwable;

class Exceptions
{
    public function __construct(protected Pulse $pulse) {}

    public function register(callable $record, Application $app): void
    {
        $app->afterResolving(
            ExceptionHandler::class,
            fn (ExceptionHandler $handler) => $handler->reportable(fn (Throwable $e) => $record($e)),
        );
    }

    public function record(Throwable $e): void
    {
        $route = request()?->route();

        $this->pulse->record(
            type: 'exception',
            key: json_encode([
                'class' => $e::class,
                'message' => Str::limit($e->getMessage(), 2000),
                'context' => $route instanceof Route ? $route->uri() : null,
                'stack_trace' => Str::limit($e->getTraceAsString(), 5000),
            ], JSON_THROW_ON_ERROR),
        );
    }
}
