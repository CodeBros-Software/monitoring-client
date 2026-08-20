<?php

namespace CodeBros\MonitoringClient\Recorders;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Laravel\Pulse\Pulse;

class ScheduledTasks
{
    /**
     * @var list<class-string>
     */
    public array $listen = [
        ScheduledTaskStarting::class,
        ScheduledTaskFinished::class,
        ScheduledTaskFailed::class,
    ];

    /**
     * The time the last task started, in milliseconds.
     */
    protected ?int $startedAt = null;

    public function __construct(protected Pulse $pulse) {}

    public function record(ScheduledTaskStarting|ScheduledTaskFinished|ScheduledTaskFailed $event): void
    {
        if ($event instanceof ScheduledTaskStarting) {
            $this->startedAt = (int) round(microtime(true) * 1000);

            return;
        }

        $duration = $event instanceof ScheduledTaskFinished
            ? (int) round($event->runtime * 1000)
            : ($this->startedAt !== null ? (int) round(microtime(true) * 1000) - $this->startedAt : null);

        $this->startedAt = null;

        $this->pulse->record(
            type: 'scheduled_task',
            key: json_encode([
                'command' => $event->task->getSummaryForDisplay(),
                'status' => $event instanceof ScheduledTaskFailed ? 'failed' : 'success',
            ], JSON_THROW_ON_ERROR),
            value: $duration,
        );
    }
}
