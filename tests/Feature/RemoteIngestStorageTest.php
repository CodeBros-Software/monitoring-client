<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Pulse\Facades\Pulse;

test('digest does nothing when the endpoint or token is not configured', function () {
    config(['monitoring-client.endpoint' => null]);

    Http::fake();

    Pulse::record(type: 'request', key: json_encode(['route' => 'x', 'method' => 'GET', 'status' => 200]), value: 1);
    Pulse::ingest();

    expect(Pulse::digest())->toBe(0);
    Http::assertNothingSent();
});

test('a failed portal response requeues the batch for the next digest', function () {
    Http::fake([
        'https://portal.test/api/monitoring/ingest' => Http::response(['error' => 'nope'], 500),
    ]);

    Pulse::record(type: 'request', key: json_encode(['route' => 'x', 'method' => 'GET', 'status' => 200]), value: 1);
    Pulse::ingest();

    expect(Pulse::digest())->toBe(0);

    expect(Cache::get('monitoring-client:buffer'))->toHaveCount(1);
});

test('a network exception during digest requeues the batch', function () {
    Http::fake(function () {
        throw new ConnectionException('connection refused');
    });

    Pulse::record(type: 'request', key: json_encode(['route' => 'x', 'method' => 'GET', 'status' => 200]), value: 1);
    Pulse::ingest();

    expect(Pulse::digest())->toBe(0);
    expect(Cache::get('monitoring-client:buffer'))->toHaveCount(1);
});

test('digest only sends up to the configured chunk size and keeps the rest buffered', function () {
    config(['monitoring-client.chunk' => 2]);

    Http::fake([
        'https://portal.test/api/monitoring/ingest' => Http::response(['status' => 'ok']),
    ]);

    foreach (range(1, 5) as $i) {
        Pulse::record(type: 'request', key: json_encode(['route' => "route-{$i}", 'method' => 'GET', 'status' => 200]), value: 1);
    }
    Pulse::ingest();

    expect(Pulse::digest())->toBe(2);
    expect(Cache::get('monitoring-client:buffer'))->toHaveCount(3);

    expect(Pulse::digest())->toBe(2);
    expect(Cache::get('monitoring-client:buffer'))->toHaveCount(1);

    expect(Pulse::digest())->toBe(1);
    expect(Cache::get('monitoring-client:buffer'))->toBeNull();
});
