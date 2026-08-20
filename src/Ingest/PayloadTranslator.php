<?php

namespace CodeBros\MonitoringClient\Ingest;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Laravel\Pulse\Entry;

class PayloadTranslator
{
    /**
     * Translate buffered Pulse entries into the portal's ingest batch shape.
     *
     * @param  Collection<int, mixed>  $entries
     * @return array<string, list<array<string, mixed>>>
     */
    public function translate(Collection $entries): array
    {
        $batch = [
            'requests' => [],
            'exceptions' => [],
            'queries' => [],
            'jobs' => [],
            'cache' => [],
            'scheduled_tasks' => [],
            'outgoing_requests' => [],
        ];

        foreach ($entries as $entry) {
            if (! $entry instanceof Entry) {
                continue;
            }

            $timestamp = CarbonImmutable::createFromTimestamp($entry->timestamp)->toIso8601String();

            match ($entry->type) {
                'request' => $batch['requests'][] = $this->decodeRequest($entry, $timestamp),
                'exception' => $batch['exceptions'][] = $this->decodeException($entry, $timestamp),
                'slow_query' => $batch['queries'][] = $this->decodeQuery($entry, $timestamp),
                'job' => $batch['jobs'][] = $this->decodeJob($entry, $timestamp),
                'cache_hit' => $batch['cache'][] = $this->decodeCache($entry, true, $timestamp),
                'cache_miss' => $batch['cache'][] = $this->decodeCache($entry, false, $timestamp),
                'scheduled_task' => $batch['scheduled_tasks'][] = $this->decodeScheduledTask($entry, $timestamp),
                'outgoing_request' => $batch['outgoing_requests'][] = $this->decodeOutgoingRequest($entry, $timestamp),
                default => null,
            };
        }

        return array_filter($batch, fn (array $items): bool => $items !== []);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeRequest(Entry $entry, string $timestamp): array
    {
        $data = json_decode($entry->key, true);

        return [
            'route' => $data['route'] ?? null,
            'method' => $data['method'] ?? 'GET',
            'status' => $data['status'] ?? 0,
            'duration' => $entry->value ?? 0,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeException(Entry $entry, string $timestamp): array
    {
        $data = json_decode($entry->key, true);

        return [
            'class' => $data['class'] ?? 'Unknown',
            'message' => $data['message'] ?? null,
            'context' => $data['context'] ?? null,
            'stack_trace' => $data['stack_trace'] ?? null,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeQuery(Entry $entry, string $timestamp): array
    {
        $data = json_decode($entry->key, true);

        return [
            'sql' => $data[0] ?? '',
            'duration' => $entry->value ?? 0,
            'context' => $data[1] ?? null,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJob(Entry $entry, string $timestamp): array
    {
        $data = json_decode($entry->key, true);

        return [
            'job_class' => $data['job_class'] ?? 'Unknown',
            'status' => $data['status'] ?? 'success',
            'duration' => $entry->value,
            'queue' => $data['queue'] ?? null,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeCache(Entry $entry, bool $hit, string $timestamp): array
    {
        return [
            'hit' => $hit,
            'key_pattern' => $entry->key,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeScheduledTask(Entry $entry, string $timestamp): array
    {
        $data = json_decode($entry->key, true);

        return [
            'command' => $data['command'] ?? 'unknown',
            'status' => $data['status'] ?? 'success',
            'duration' => $entry->value,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeOutgoingRequest(Entry $entry, string $timestamp): array
    {
        $data = json_decode($entry->key, true);

        return [
            'host' => $data['host'] ?? 'unknown',
            'method' => $data['method'] ?? 'GET',
            'status' => $data['status'] ?? null,
            'duration' => $entry->value ?? 0,
            'timestamp' => $timestamp,
        ];
    }
}
