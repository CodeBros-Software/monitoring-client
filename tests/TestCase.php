<?php

namespace CodeBros\MonitoringClient\Tests;

use CodeBros\MonitoringClient\MonitoringClientServiceProvider;
use Laravel\Pulse\PulseServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PulseServiceProvider::class,
            MonitoringClientServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('pulse.enabled', true);
        $app['config']->set('monitoring-client.endpoint', 'https://portal.test/api/monitoring/ingest');
        $app['config']->set('monitoring-client.token', 'test-token');
    }
}
