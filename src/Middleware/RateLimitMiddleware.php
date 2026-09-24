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
    private string $identifier;
    private RateLimitStoreInterface $store;
    private string $namespace;

    public function __construct(
        int $maxRequests = 100,
        int $timeWindow = 60,
        string $identifier = 'ip',
        ?RateLimitStoreInterface $store = null,
        string $namespace = 'rpc'
    ) {
        if ($maxRequests < 1 || $timeWindow < 1) {
            throw new \InvalidArgumentException('Rate-limit values must be greater than zero');
        }

        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;
        $this->identifier = $identifier;
        $this->store = $store ?? new InMemoryRateLimitStore();
        $this->namespace = $namespace;
    }

    public function handle(array $context): array
    {
        $identifierKey = $this->getIdentifierKey($context);
        $key = hash(
            'sha256',
            implode("\0", [
                $this->namespace,
                $this->identifier,
                (string) $this->maxRequests,
                (string) $this->timeWindow,
                $identifierKey
            ])
        );
        $now = time();
        $bucket = $this->store->increment($key, $this->timeWindow, $now);

        // Check limit
        if ($bucket['requests'] > $this->maxRequests) {
            throw new \RpcPhpToolkit\Exceptions\InternalErrorException(
                'Rate limit exceeded',
                null
            );
        }

        // Add rate limit headers to context
        $context['rate_limit'] = [
            'limit' => $this->maxRequests,
            'remaining' => max(0, $this->maxRequests - $bucket['requests']),
            'reset' => $bucket['window_start'] + $this->timeWindow
        ];

        return $context;
    }

    private function getIdentifierKey(array $context): string
    {
        $value = match ($this->identifier) {
            'ip' => $context['request']['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_id' => $context['authenticated_user']['id']
                ?? $context['context']['authenticated_user']['id']
                ?? $context['user_id']
                ?? 'anonymous',
            'session' => session_id() ?: 'no_session',
            default => 'global'
        };

        return is_scalar($value) ? (string) $value : hash('sha256', serialize($value));
    }
}
