<?php

namespace CodeBros\MonitoringClient\Ingest;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Pulse\Contracts\Ingest;
use Laravel\Pulse\Contracts\Storage;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Value;
use Throwable;

/**
 * Buffers recorded entries in the cache between requests/jobs, then posts
 * them in batches to the portal's monitoring ingest endpoint when digested
 * (by `php artisan pulse:work`). Replaces Pulse's local database storage
 * entirely — nothing is persisted locally.
 */
class RemoteIngestStorage implements Ingest
{
    protected const BUFFER_KEY = 'monitoring-client:buffer';

    protected const LOCK_KEY = 'monitoring-client:buffer-lock';

    public function __construct(protected PayloadTranslator $translator) {}

    /**
     * @param  Collection<int, Entry|Value>  $items
     */
    public function ingest(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $store = $this->store();

        $store->lock(self::LOCK_KEY, 5)->block(3, function () use ($store, $items) {
            $buffered = $store->get(self::BUFFER_KEY, []);

            foreach ($items as $item) {
                $buffered[] = serialize($item);
            }

            $store->put(self::BUFFER_KEY, $buffered, now()->addHour());
        });
    }

    public function trim(): void
    {
        //
    }

    public function digest(Storage $storage): int
    {
        $endpoint = config('monitoring-client.endpoint');
        $token = config('monitoring-client.token');

        if (! $endpoint || ! $token) {
            return 0;
        }

        $store = $this->store();
        $chunk = (int) config('monitoring-client.chunk', 500);

        $payloads = $store->lock(self::LOCK_KEY, 5)->block(3, function () use ($store, $chunk) {
            $buffered = $store->get(self::BUFFER_KEY, []);
            $take = array_slice($buffered, 0, $chunk);
            $remaining = array_slice($buffered, $chunk);

            if ($remaining === []) {
                $store->forget(self::BUFFER_KEY);
            } else {
                $store->put(self::BUFFER_KEY, $remaining, now()->addHour());
            }

            return $take;
        });

        if ($payloads === []) {
            return 0;
        }

        $entries = collect($payloads)->map(
            fn (string $payload) => unserialize($payload, ['allowed_classes' => [Entry::class, Value::class]]),
        );

        $batch = $this->translator->translate($entries);

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('monitoring-client.timeout', 5))
                ->post($endpoint, $batch);

            if ($response->failed()) {
                $this->requeue($store, $payloads);

                return 0;
            }
        } catch (Throwable $e) {
            $this->requeue($store, $payloads);
            report($e);

            return 0;
        }

        return $entries->count();
    }

    /**
     * @param  list<string>  $payloads
     */
    protected function requeue(Repository $store, array $payloads): void
    {
        $store->lock(self::LOCK_KEY, 5)->block(3, function () use ($store, $payloads) {
            $buffered = $store->get(self::BUFFER_KEY, []);
            $store->put(self::BUFFER_KEY, [...$payloads, ...$buffered], now()->addHour());
        });
    }

    protected function store(): Repository
    {
        return Cache::store(config('monitoring-client.cache_store'));
    }
}
