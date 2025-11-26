<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Middleware;

/**
 * Interface for middlewares
 */
interface MiddlewareInterface
{
    /**
     * Handles the middleware
     *
     * @param array $context The request context
     * @return array The modified context
     */
    public function handle(array $context): array;
}
