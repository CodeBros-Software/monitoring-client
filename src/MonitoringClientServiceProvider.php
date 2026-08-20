<?php

namespace CodeBros\MonitoringClient;

use CodeBros\MonitoringClient\Ingest\RemoteIngestStorage;
use CodeBros\MonitoringClient\Recorders\Exceptions;
use CodeBros\MonitoringClient\Recorders\Jobs;
use CodeBros\MonitoringClient\Recorders\OutgoingRequests;
use CodeBros\MonitoringClient\Recorders\Requests;
use CodeBros\MonitoringClient\Recorders\ScheduledTasks;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Recorders\CacheInteractions;
use Laravel\Pulse\Recorders\SlowQueries;

class MonitoringClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/monitoring-client.php', 'monitoring-client');

        // Only the recorders our portal schema understands are registered;
        // Pulse's own defaults (Servers, UserRequests, ...) are left off.
        config()->set('pulse.recorders', [
            Requests::class => ['enabled' => true],
            Exceptions::class => ['enabled' => true],
            SlowQueries::class => [
                'enabled' => true,
                'sample_rate' => 1,
                'threshold' => 0,
                'location' => true,
                'max_query_length' => 2000,
                'ignore' => [],
            ],
            Jobs::class => ['enabled' => true],
            CacheInteractions::class => [
                'enabled' => true,
                'sample_rate' => 1,
                'ignore' => [],
                'groups' => [],
            ],
            ScheduledTasks::class => ['enabled' => true],
            OutgoingRequests::class => ['enabled' => true],
        ]);
    }

    public function boot(): void
    {
        // Bound in boot() (after every provider's register() phase, including
        // Pulse's own) so this reliably wins over Pulse's default Ingest
        // binding regardless of provider load order.
        $this->app->bind(Ingest::class, RemoteIngestStorage::class);

        $this->publishes([
            __DIR__.'/../config/monitoring-client.php' => config_path('monitoring-client.php'),
        ], 'monitoring-client-config');
    }
}
