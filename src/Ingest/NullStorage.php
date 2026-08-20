<?php

namespace CodeBros\MonitoringClient\Ingest;

use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Laravel\Pulse\Contracts\Storage;

/**
 * Pulse forwards any method it doesn't recognise (e.g. the `trim()` call
 * `pulse:work` makes every 10 minutes) straight to the container's
 * Storage binding. Nothing in this package ever stores data locally —
 * RemoteIngestStorage posts everything to the portal instead — so without
 * this binding those forwarded calls would fall through to Pulse's own
 * DatabaseStorage and fail against tables that were never migrated here.
 */
class NullStorage implements Storage
{
    /**
     * @param  Collection<int, mixed>  $items
     */
    public function store(Collection $items): void
    {
        //
    }

    public function trim(): void
    {
        //
    }

    /**
     * @param  list<string>|null  $types
     */
    public function purge(?array $types = null): void
    {
        //
    }

    /**
     * @param  list<string>|null  $keys
     * @return Collection<string, mixed>
     */
    public function values(string $type, ?array $keys = null): Collection
    {
        return new Collection;
    }

    /**
     * @param  list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return Collection<string, Collection<string, Collection<string, int|null>>>
     */
    public function graph(array $types, string $aggregate, CarbonInterval $interval): Collection
    {
        return new Collection;
    }

    /**
     * @param  'count'|'min'|'max'|'sum'|'avg'|list<'count'|'min'|'max'|'sum'|'avg'>  $aggregates
     * @return Collection<int, object>
     */
    public function aggregate(
        string $type,
        string|array $aggregates,
        CarbonInterval $interval,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        return new Collection;
    }

    /**
     * @param  string|list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     * @return Collection<int, object>
     */
    public function aggregateTypes(
        string|array $types,
        string $aggregate,
        CarbonInterval $interval,
        ?string $orderBy = null,
        string $direction = 'desc',
        int $limit = 101,
    ): Collection {
        return new Collection;
    }

    /**
     * @param  string|list<string>  $types
     * @param  'count'|'min'|'max'|'sum'|'avg'  $aggregate
     */
    public function aggregateTotal(
        array|string $types,
        string $aggregate,
        CarbonInterval $interval,
    ): float|Collection {
        return 0.0;
    }
}
