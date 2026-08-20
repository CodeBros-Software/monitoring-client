<?php

namespace CodeBros\MonitoringClient\Recorders;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Laravel\Pulse\Pulse;

class Jobs
{
    /**
     * @var list<class-string>
     */
    public array $listen = [
        JobProcessing::class,
        JobProcessed::class,
        JobFailed::class,
        JobReleasedAfterException::class,
    ];

    /**
     * The time the last job started processing, in milliseconds.
     */
    protected ?int $startedAt = null;

    public function __construct(protected Pulse $pulse) {}

    public function record(JobProcessing|JobProcessed|JobFailed|JobReleasedAfterException $event): void
    {
        if ($event instanceof JobProcessing) {
            $this->startedAt = (int) round(microtime(true) * 1000);

            return;
        }

        if ($this->startedAt === null) {
            return;
        }

        $duration = (int) round(microtime(true) * 1000) - $this->startedAt;
        $this->startedAt = null;

        $this->pulse->record(
            type: 'job',
            key: json_encode([
                'job_class' => $event->job->resolveName(),
                'status' => match (true) {
                    $event instanceof JobProcessed => 'success',
                    $event instanceof JobFailed => 'failed',
                    default => 'retried',
                },
                'queue' => $event->job->getQueue(),
            ], JSON_THROW_ON_ERROR),
            value: $duration,
        );
    }
}
