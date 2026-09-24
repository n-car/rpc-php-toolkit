<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Middleware;

/**
 * Process-local rate-limit storage, intended for tests and long-running workers.
 */
final class InMemoryRateLimitStore implements RateLimitStoreInterface
{
    /** @var array<string, array{requests: int, window_start: int}> */
    private array $buckets = [];

    public function increment(string $key, int $timeWindow, int $now): array
    {
        if (!isset($this->buckets[$key])) {
            $this->buckets[$key] = [
                'requests' => 0,
                'window_start' => $now
            ];
        }

        if ($now - $this->buckets[$key]['window_start'] >= $timeWindow) {
            $this->buckets[$key] = [
                'requests' => 0,
                'window_start' => $now
            ];
        }

        $this->buckets[$key]['requests']++;
        $this->cleanup($now, $timeWindow);

        return $this->buckets[$key];
    }

    private function cleanup(int $now, int $timeWindow): void
    {
        foreach ($this->buckets as $key => $bucket) {
            if ($now - $bucket['window_start'] >= $timeWindow * 2) {
                unset($this->buckets[$key]);
            }
        }
    }
}
