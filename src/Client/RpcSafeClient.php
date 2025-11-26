<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Client;

use RpcPhpToolkit\Client\RpcClient;

/**
 * RpcSafeClient - Convenience class with Safe Mode preset enabled
 * 
 * This class extends RpcClient and automatically enables Safe Mode,
 * providing a cleaner API for safe RPC clients without manually
 * setting safeEnabled in options.
 * 
 * Safe Mode enables safe deserialization of special types like DateTime,
 * BigInt (as strings), NaN, INF, and provides better type preservation
 * across RPC calls.
 * 
 * @example
 * ```php
 * // Instead of:
 * $client = new RpcClient('http://localhost:3000/api', [], ['safeEnabled' => true]);
 * 
 * // Use:
 * $client = new RpcSafeClient('http://localhost:3000/api');
 * ```
 */
class RpcSafeClient extends RpcClient
{
    /**
     * Creates a new RPC client with Safe Mode enabled by default
     *
     * @param string $url The RPC endpoint URL
     * @param array $headers Additional HTTP headers
     * @param array $options Additional options (safeEnabled is preset to true)
     */
    public function __construct(string $url, array $headers = [], array $options = [])
    {
        // Merge user options with safe defaults
        $safeOptions = array_merge([
            'safeEnabled' => true,
        ], $options);

        parent::__construct($url, $headers, $safeOptions);
    }
}
