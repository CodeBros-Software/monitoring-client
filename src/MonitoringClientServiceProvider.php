<?php

namespace CodeBros\MonitoringClient;

use CodeBros\MonitoringClient\Ingest\NullStorage;
use CodeBros\MonitoringClient\Ingest\RemoteIngestStorage;
use CodeBros\MonitoringClient\Recorders\Exceptions;
use CodeBros\MonitoringClient\Recorders\Jobs;
use CodeBros\MonitoringClient\Recorders\OutgoingRequests;
use CodeBros\MonitoringClient\Recorders\Requests;
use CodeBros\MonitoringClient\Recorders\ScheduledTasks;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
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
        // Pulse's own) so these reliably win over Pulse's default Ingest and
        // Storage bindings regardless of provider load order. Storage must be
        // replaced too: Pulse forwards unrecognised method calls on it (e.g.
        // pulse:work's periodic trim()) straight to the container's Storage
        // binding, which would otherwise fall through to Pulse's own
        // DatabaseStorage and fail against tables this package never migrates.
        $this->app->bind(Ingest::class, RemoteIngestStorage::class);
        $this->app->bind(Storage::class, NullStorage::class);

        $this->publishes([
            __DIR__.'/../config/monitoring-client.php' => config_path('monitoring-client.php'),
        ], 'monitoring-client-config');
    }
}
