<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Middleware;

use RpcPhpToolkit\Logger\Logger;

/**
 * Middleware manager for the RPC system
 */
class MiddlewareManager
{
    private array $middleware = [];
    private ?Logger $logger;

    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Adds a middleware
     */
    public function add(MiddlewareInterface $middleware, string $phase = 'before'): self
    {
        if (!isset($this->middleware[$phase])) {
            $this->middleware[$phase] = [];
        }

        $this->middleware[$phase][] = $middleware;

        $this->logger?->debug('Middleware added', [
            'middleware' => get_class($middleware),
            'phase' => $phase
        ]);

        return $this;
    }

    /**
     * Removes a middleware
     */
    public function remove(string $middlewareClass, ?string $phase = null): self
    {
        if ($phase === null) {
            // Remove from all phases
            foreach ($this->middleware as $currentPhase => $middlewares) {
                $this->removeFromPhase($middlewareClass, $currentPhase);
            }
        } else {
            $this->removeFromPhase($middlewareClass, $phase);
        }

        return $this;
    }

    private function removeFromPhase(string $middlewareClass, string $phase): void
    {
        if (!isset($this->middleware[$phase])) {
            return;
        }

        $this->middleware[$phase] = array_filter(
            $this->middleware[$phase],
            fn($middleware) => !($middleware instanceof $middlewareClass)
        );

        $this->logger?->debug('Middleware removed', [
            'middleware' => $middlewareClass,
            'phase' => $phase
        ]);
    }

    /**
     * Executes middleware for a specific phase
     */
    public function executeMiddleware(string $phase, array $context): array
    {
        if (!isset($this->middleware[$phase])) {
            return $context;
        }

        $this->logger?->debug("Executing middleware phase: {$phase}", [
            'middleware_count' => count($this->middleware[$phase]),
            'context_keys' => array_keys($context)
        ]);

        foreach ($this->middleware[$phase] as $middleware) {
            try {
                $startTime = microtime(true);

                $context = $middleware->handle($context);

                $executionTime = (microtime(true) - $startTime) * 1000;

                $this->logger?->debug('Middleware executed', [
                    'middleware' => get_class($middleware),
                    'phase' => $phase,
                    'execution_time_ms' => round($executionTime, 2)
                ]);

            } catch (\Throwable $e) {
                $this->logger?->error('Middleware error', [
                    'middleware' => get_class($middleware),
                    'phase' => $phase,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);

                throw $e;
            }
        }

        return $context;
    }

    /**
     * Gets all middleware for a phase
     */
    public function getMiddleware(?string $phase = null): array
    {
        if ($phase === null) {
            return $this->middleware;
        }

        return $this->middleware[$phase] ?? [];
    }

    /**
     * Counts middleware for a phase
     */
    public function count(?string $phase = null): int
    {
        if ($phase === null) {
            return array_sum(array_map('count', $this->middleware));
        }

        return count($this->middleware[$phase] ?? []);
    }

    /**
     * Clears all middleware
     */
    public function clear(?string $phase = null): self
    {
        if ($phase === null) {
            $this->middleware = [];
            $this->logger?->info('All middleware have been removed');
        } else {
            unset($this->middleware[$phase]);
            $this->logger?->info("Middleware removed from phase: {$phase}");
        }

        return $this;
    }
}
