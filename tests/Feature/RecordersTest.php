<?php

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Pulse\Facades\Pulse;

function fakeQueueJob(string $name = 'App\\Jobs\\Foo', string $queue = 'default'): QueueJob
{
    $job = Mockery::mock(QueueJob::class);
    $job->shouldReceive('resolveName')->andReturn($name);
    $job->shouldReceive('getQueue')->andReturn($queue);
    $job->shouldReceive('payload')->andReturn([]);

    return $job;
}

function assertSingleBatchItem(string $key): array
{
    Pulse::ingest();
    expect(Pulse::digest())->toBe(1);

    $sent = null;
    Http::assertSent(function ($request) use ($key, &$sent) {
        $sent = $request->data()[$key][0] ?? null;

        return $sent !== null;
    });

    return $sent;
}

beforeEach(function () {
    Http::fake([
        'https://portal.test/api/monitoring/ingest' => Http::response(['status' => 'ok']),
    ]);
});

test('a reported exception is captured with message and stack trace', function () {
    report(new RuntimeException('Something broke'));

    $item = assertSingleBatchItem('exceptions');

    expect($item['class'])->toBe(RuntimeException::class)
        ->and($item['message'])->toBe('Something broke')
        ->and($item['stack_trace'])->not->toBeEmpty();
});

test('a successfully processed job is recorded with success status', function () {
    $job = fakeQueueJob(queue: 'default');

    event(new JobProcessing('sync-test', $job));
    event(new JobProcessed('sync-test', $job));

    $item = assertSingleBatchItem('jobs');

    expect($item['job_class'])->toBe('App\\Jobs\\Foo')
        ->and($item['status'])->toBe('success')
        ->and($item['queue'])->toBe('default');
});

test('a failed job is recorded with failed status', function () {
    $job = fakeQueueJob();

    event(new JobProcessing('sync-test', $job));
    event(new JobFailed('sync-test', $job, new Exception('nope')));

    $item = assertSingleBatchItem('jobs');

    expect($item['status'])->toBe('failed');
});

test('a scheduled task run is recorded', function () {
    $mutex = Mockery::mock(EventMutex::class);
    $scheduledEvent = new ScheduledEvent($mutex, 'app:cleanup');
    $scheduledEvent->description('app:cleanup');

    event(new ScheduledTaskStarting($scheduledEvent));
    event(new ScheduledTaskFinished($scheduledEvent, 0.25));

    $item = assertSingleBatchItem('scheduled_tasks');

    expect($item['command'])->toBe('app:cleanup')
        ->and($item['status'])->toBe('success')
        ->and($item['duration'])->toBe(250);
});

test('every query is captured, not just slow ones, because threshold is 0', function () {
    DB::listen(function () {
        //
    });

    event(new QueryExecuted('select 1', [], 0.5, DB::connection()));

    $item = assertSingleBatchItem('queries');

    expect($item['sql'])->toBe('select 1');
});

test('an outgoing request is captured with its status code', function () {
    Http::fake([
        'https://portal.test/api/monitoring/ingest' => Http::response(['status' => 'ok']),
        'https://api.example.com/*' => Http::response('', 502),
    ]);

    Http::get('https://api.example.com/widgets');

    $item = assertSingleBatchItem('outgoing_requests');

    expect($item['host'])->toBe('api.example.com')
        ->and($item['method'])->toBe('GET')
        ->and($item['status'])->toBe(502);
});

test('calls to the ingest endpoint itself are not recorded as outgoing requests', function () {
    Http::fake([
        'https://portal.test/api/monitoring/ingest' => Http::response(['status' => 'ok']),
    ]);

    Http::withToken('test-token')->post('https://portal.test/api/monitoring/ingest', ['requests' => []]);

    Pulse::ingest();

    expect(Pulse::digest())->toBe(0);
});

test('cache hits and misses are captured', function () {
    event(new CacheHit('array', 'user:1', 'value'));
    event(new CacheMissed('array', 'user:2'));

    Pulse::ingest();
    expect(Pulse::digest())->toBe(2);

    Http::assertSent(function ($request) {
        $cache = $request->data()['cache'] ?? [];

        return count($cache) === 2
            && $cache[0]['hit'] === true
            && $cache[1]['hit'] === false;
    });
});
