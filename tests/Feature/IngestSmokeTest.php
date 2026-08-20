<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Laravel\Pulse\Facades\Pulse;

test('a handled request is buffered and flushed to the portal', function () {
    Http::fake([
        'https://portal.test/api/monitoring/ingest' => Http::response(['status' => 'ok']),
    ]);

    Route::get('/dashboard', fn () => 'ok');

    $request = Request::create('/dashboard', 'GET');
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);

    event(new RequestHandled($request, new Response('ok', 200)));

    Pulse::ingest();

    $count = Pulse::digest();

    expect($count)->toBe(1);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://portal.test/api/monitoring/ingest'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && ($data['requests'][0]['route'] ?? null) === 'dashboard'
            && ($data['requests'][0]['status'] ?? null) === 200;
    });
});

test('pulse:work style trim calls do not touch a local database', function () {
    // Pulse forwards unrecognised method calls (like the trim() that
    // pulse:work runs every 10 minutes) to the container's Storage
    // binding. Without NullStorage this falls through to Pulse's own
    // DatabaseStorage and fails against tables this package never
    // migrates — this app's sqlite connection has no pulse_* tables
    // either, so it reproduces the same failure a real client app hits.
    Pulse::trim();
})->throwsNoExceptions();
