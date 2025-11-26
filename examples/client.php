<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use RpcPhpToolkit\Client\RpcClient;

/**
 * Simple example client usage
 * For full RpcClient implementation, see src/Client/RpcClient.php
 */

// ========== USAGE EXAMPLES ==========

try {
    // Configure the client
    $client = new RpcClient('http://localhost:8000/basic-server.php');
    
    echo "=== PHP RPC CLIENT TEST ===\n\n";

    // Test 1: Simple method
    echo "1. getTime test:\n";
    $result = $client->call('public.getTime', [], 1);
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    // Test 2: Method with parameters
    echo "2. Echo test with parameters:\n";
    $result = $client->call('public.echo', ['message' => 'Hello from PHP client!'], 2);
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    // Test 3: Calculation
    echo "3. Calculation test (5 + 3):\n";
    $result = $client->call('calc.add', ['a' => 5, 'b' => 3], 3);
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    // Test 4: Protected method without authentication
    echo "4. Protected method test without auth:\n";
    $result = $client->call('user.getProfile', [], 4);
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    // Test 5: Protected method with authentication
    echo "5. Protected method test with auth:\n";
    $client->setAuthToken('user123');
    $result = $client->call('user.getProfile', [], 5);
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    // Test 6: Batch request
    echo "6. Batch request test:\n";
    $batchRequests = [
        ['method' => 'public.getTime', 'id' => 'time'],
        ['method' => 'calc.add', 'params' => ['a' => 10, 'b' => 20], 'id' => 'calc'],
        ['method' => 'public.echo', 'params' => ['message' => 'Batch test']], // notification
    ];
    $result = $client->batch($batchRequests);
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    // Test 7: Error
    echo "7. Error handling test:\n";
    $result = $client->call('test.error', ['type' => 'validation'], 7);
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
