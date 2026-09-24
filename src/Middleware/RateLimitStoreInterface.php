<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Middleware;

/**
 * Persistent counter storage used by RateLimitMiddleware.
 */
interface RateLimitStoreInterface
{
    /**
     * Atomically increments a bucket, resetting it when its window has expired.
     *
     * @return array{requests: int, window_start: int}
     */
    public function increment(string $key, int $timeWindow, int $now): array;
}
