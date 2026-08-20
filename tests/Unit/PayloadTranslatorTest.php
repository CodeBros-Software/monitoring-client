<?php

use CodeBros\MonitoringClient\Ingest\PayloadTranslator;
use Laravel\Pulse\Entry;

function makeEntry(string $type, string $key, ?int $value = null, int $timestamp = 1700000000): Entry
{
    return new Entry($timestamp, $type, $key, $value);
}

test('translates a request entry', function () {
    $entry = makeEntry('request', json_encode(['route' => 'dashboard', 'method' => 'GET', 'status' => 200]), 42);

    $batch = (new PayloadTranslator)->translate(collect([$entry]));

    expect($batch['requests'][0])->toMatchArray([
        'route' => 'dashboard',
        'method' => 'GET',
        'status' => 200,
        'duration' => 42,
    ]);
});

test('translates an exception entry', function () {
    $entry = makeEntry('exception', json_encode([
        'class' => 'RuntimeException',
        'message' => 'Boom',
        'context' => 'dashboard',
        'stack_trace' => '#0 ...',
    ]));

    $batch = (new PayloadTranslator)->translate(collect([$entry]));

    expect($batch['exceptions'][0])->toMatchArray([
        'class' => 'RuntimeException',
        'message' => 'Boom',
        'context' => 'dashboard',
        'stack_trace' => '#0 ...',
    ]);
});

test('translates a slow_query entry into the queries batch', function () {
    $entry = makeEntry('slow_query', json_encode(['select * from users', 'app/Foo.php:10']), 15);

    $batch = (new PayloadTranslator)->translate(collect([$entry]));

    expect($batch['queries'][0])->toMatchArray([
        'sql' => 'select * from users',
        'duration' => 15,
        'context' => 'app/Foo.php:10',
    ]);
});

test('translates a job entry', function () {
    $entry = makeEntry('job', json_encode(['job_class' => 'App\\Jobs\\Foo', 'status' => 'failed', 'queue' => 'default']), 500);

    $batch = (new PayloadTranslator)->translate(collect([$entry]));

    expect($batch['jobs'][0])->toMatchArray([
        'job_class' => 'App\\Jobs\\Foo',
        'status' => 'failed',
        'duration' => 500,
        'queue' => 'default',
    ]);
});

test('translates cache_hit and cache_miss entries', function () {
    $batch = (new PayloadTranslator)->translate(collect([
        makeEntry('cache_hit', 'user:1'),
        makeEntry('cache_miss', 'user:2'),
    ]));

    expect($batch['cache'])->toHaveCount(2)
        ->and($batch['cache'][0])->toMatchArray(['hit' => true, 'key_pattern' => 'user:1'])
        ->and($batch['cache'][1])->toMatchArray(['hit' => false, 'key_pattern' => 'user:2']);
});

test('translates a scheduled_task entry', function () {
    $entry = makeEntry('scheduled_task', json_encode(['command' => 'app:cleanup', 'status' => 'success']), 1200);

    $batch = (new PayloadTranslator)->translate(collect([$entry]));

    expect($batch['scheduled_tasks'][0])->toMatchArray([
        'command' => 'app:cleanup',
        'status' => 'success',
        'duration' => 1200,
    ]);
});

test('translates an outgoing_request entry', function () {
    $entry = makeEntry('outgoing_request', json_encode(['host' => 'api.example.com', 'method' => 'POST', 'status' => 502]), 900);

    $batch = (new PayloadTranslator)->translate(collect([$entry]));

    expect($batch['outgoing_requests'][0])->toMatchArray([
        'host' => 'api.example.com',
        'method' => 'POST',
        'status' => 502,
        'duration' => 900,
    ]);
});

test('omits empty batch keys and ignores unknown entry types', function () {
    $batch = (new PayloadTranslator)->translate(collect([
        makeEntry('something_unrecognized', 'x'),
    ]));

    expect($batch)->toBe([]);
});
