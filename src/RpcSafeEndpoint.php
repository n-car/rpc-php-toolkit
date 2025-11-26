<?php

declare(strict_types=1);

namespace RpcPhpToolkit;

use RpcPhpToolkit\RpcEndpoint;

/**
 * RpcSafeEndpoint - Convenience class with Safe Mode preset enabled
 *
 * This class extends RpcEndpoint and automatically enables Safe Mode,
 * providing a cleaner API for safe RPC endpoints without manually
 * setting safeEnabled in options.
 *
 * Safe Mode enables safe serialization of special types like DateTime,
 * NaN, INF, and provides better type preservation across RPC calls.
 *
 * @example
 * ```php
 * // Instead of:
 * $rpc = new RpcEndpoint('/api', $context, ['safeEnabled' => true]);
 *
 * // Use:
 * $rpc = new RpcSafeEndpoint('/api', $context);
 * ```
 */
class RpcSafeEndpoint extends RpcEndpoint
{
    /**
     * Creates a new RPC endpoint with Safe Mode enabled by default
     *
     * @param string $endpoint The endpoint path (default: '/rpc')
     * @param mixed $context Optional context object available to all methods
     * @param array $options Additional options (safeEnabled is preset to true)
     */
    public function __construct(
        string $endpoint = '/rpc',
        mixed $context = null,
        array $options = []
    ) {
        // Merge user options with safe defaults
        $safeOptions = array_merge([
            'safeEnabled' => true,
            'sanitizeErrors' => true,
        ], $options);

        parent::__construct($endpoint, $context, $safeOptions);
    }
}
