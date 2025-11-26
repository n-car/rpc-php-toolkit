<?php

declare(strict_types=1);

require_once __DIR__ . '/../test-quick.php'; // Uses autoloader

use RpcPhpToolkit\Client\RpcClient;

/**
 * PHP client calling Node.js Express server
 * 
 * Requirements:
 * - Express server running on http://localhost:3000/api
 * - Use the express example from rpc-express-toolkit
 */

echo "=== PHP Client → Node.js Express Server ===\n\n";

try {
    // Create client pointing to Express server
    $client = new RpcClient('http://localhost:3000/api', [], [
        'timeout' => 30,
        'verifySSL' => true,
        'safeEnabled' => false
    ]);

    // Test 1: Simple call (assuming Express has this method)
    echo "1. Testing simple call...\n";
    try {
        $result = $client->call('add', ['a' => 5, 'b' => 3]);
        echo "   Result: 5 + 3 = " . json_encode($result) . "\n";
        echo "   ✓ Success\n\n";
    } catch (Exception $e) {
        echo "   Method not found (expected if Express doesn't have 'add' method)\n";
        echo "   Error: {$e->getMessage()}\n\n";
    }

    // Test 2: Echo method (common in examples)
    echo "2. Testing echo method...\n";
    try {
        $result = $client->call('echo', ['message' => 'Hello from PHP!']);
        echo "   Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        echo "   ✓ Success\n\n";
    } catch (Exception $e) {
        echo "   Error: {$e->getMessage()}\n\n";
    }

    // Test 3: Batch request
    echo "3. Testing batch request...\n";
    try {
        $results = $client->batch([
            ['method' => 'add', 'params' => ['a' => 10, 'b' => 20], 'id' => 1],
            ['method' => 'echo', 'params' => ['message' => 'Batch from PHP'], 'id' => 2]
        ]);
        echo "   Results: " . json_encode($results, JSON_PRETTY_PRINT) . "\n";
        echo "   ✓ Success\n\n";
    } catch (Exception $e) {
        echo "   Error: {$e->getMessage()}\n\n";
    }

    echo "=== Tests Completed ===\n";
    echo "\nPHP can call Node.js Express RPC server!\n";
    echo "\nNote: Make sure Express server is running on http://localhost:3000/api\n";
    echo "You can use examples from rpc-express-toolkit repository\n";

} catch (Exception $e) {
    echo "✗ Fatal Error: {$e->getMessage()}\n";
    exit(1);
}
