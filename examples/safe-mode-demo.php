<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use RpcPhpToolkit\RpcEndpoint;
use RpcPhpToolkit\Client\RpcClient;

/**
 * Example: Safe Mode serialization demo
 * Demonstrates type-safe BigInt and Date handling
 */

echo "=== Safe Mode Serialization Test ===\n\n";

// Server with safe mode enabled
$serverEndpoint = new RpcEndpoint('/api/rpc', null, [
    'safeEnabled' => true,
    'warnOnUnsafe' => false
]);

$serverEndpoint->addMethod('test.types', function($params, $context) {
    return [
        'string' => 'hello world',
        'number' => 42,
        'bigint' => 9007199254740992,  // Larger than JS safe integer
        'date' => new DateTime('2025-11-26T10:30:00Z'),
        'array' => ['a', 'b', 'c'],
        'nested' => [
            'data' => new DateTime('now'),
            'value' => 'test'
        ]
    ];
});

// Simulate request
$request = json_encode([
    'jsonrpc' => '2.0',
    'method' => 'test.types',
    'params' => [],
    'id' => 1
]);

// Get response
$response = $serverEndpoint->handleRequest($request);
$decoded = json_decode($response, true);

echo "Server Response (Safe Mode Enabled):\n";
echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n\n";

// Show how values are serialized
echo "Serialized values:\n";
echo "- String: {$decoded['result']['string']}\n";
echo "- BigInt: {$decoded['result']['bigint']}\n";
echo "- Date: {$decoded['result']['date']}\n";
echo "- Nested date: {$decoded['result']['nested']['data']}\n\n";

// Compare with safe mode disabled
echo "=== Standard Mode (Safe Disabled) ===\n\n";

$standardEndpoint = new RpcEndpoint('/api/rpc', null, [
    'safeEnabled' => false,
    'warnOnUnsafe' => false
]);

$standardEndpoint->addMethod('test.types', function($params, $context) {
    return [
        'string' => 'hello world',
        'number' => 42,
        'bigint' => 9007199254740992,
        'date' => new DateTime('2025-11-26T10:30:00Z'),
    ];
});

$standardResponse = $standardEndpoint->handleRequest($request);
$standardDecoded = json_decode($standardResponse, true);

echo "Server Response (Safe Mode Disabled):\n";
echo json_encode($standardDecoded, JSON_PRETTY_PRINT) . "\n\n";

echo "Serialized values:\n";
echo "- String: {$standardDecoded['result']['string']} (no prefix)\n";
echo "- BigInt: {$standardDecoded['result']['bigint']} (n suffix)\n";
echo "- Date: {$standardDecoded['result']['date']} (no D: prefix)\n\n";

echo "Notice:\n";
echo "- Safe mode adds S: prefix to strings\n";
echo "- Safe mode adds D: prefix to dates\n";
echo "- BigInt always gets 'n' suffix in both modes\n";
echo "- This prevents ambiguity when deserializing\n";
