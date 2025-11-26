<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Middleware;

/**
 * Middleware for rate limiting
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $timeWindow;
    private array $storage = [];
    private string $identifier;

    public function __construct(int $maxRequests = 100, int $timeWindow = 60, string $identifier = 'ip')
    {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->identifier = $identifier;
    }

    public function handle(array $context): array
    {
        $key = $this->getIdentifierKey($context);
        $now = time();

        // Clean old entries
        $this->cleanup($now);

        // Initialize bucket if it doesn't exist
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = [
                'requests' => 0,
                'window_start' => $now
            ];
        }

        $bucket = &$this->storage[$key];

        // Reset bucket if window expired
        if ($now - $bucket['window_start'] >= $this->timeWindow) {
            $bucket['requests'] = 0;
            $bucket['window_start'] = $now;
        }

        // Check limit
        if ($bucket['requests'] >= $this->maxRequests) {
            throw new \RpcPhpToolkit\Exceptions\InternalErrorException(
                'Rate limit exceeded',
                null
            );
        }

        $bucket['requests']++;

        // Add rate limit headers to context
        $context['rate_limit'] = [
            'limit' => $this->maxRequests,
            'remaining' => $this->maxRequests - $bucket['requests'],
            'reset' => $bucket['window_start'] + $this->timeWindow
        ];

        return $context;
    }

    private function getIdentifierKey(array $context): string
    {
        return match ($this->identifier) {
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_id' => $context['user_id'] ?? 'anonymous',
            'session' => session_id() ?: 'no_session',
            default => 'global'
        };
    }

    private function cleanup(int $now): void
    {
        foreach ($this->storage as $key => $bucket) {
            if ($now - $bucket['window_start'] >= $this->timeWindow * 2) {
                unset($this->storage[$key]);
            }
        }
    }
}
