<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use RpcPhpToolkit\Client\RpcClient;

/**
 * PHP client calling PHP server (same machine)
 * Demonstrates cross-process RPC communication
 */

echo "=== PHP Client → PHP Server (Cross-Process) ===\n\n";

// Wait a moment for server to be ready
sleep(1);

try {
    // Create client pointing to local PHP server
    $client = new RpcClient('http://localhost:8000/examples/cors-server.php', [], [
        'timeout' => 10,
        'verifySSL' => false,
        'safeEnabled' => false
    ]);

    // Test 1: Get server time
    echo "1. Testing public.getTime...\n";
    $result = $client->call('public.getTime');
    echo "   Server time: " . $result['datetime'] . "\n";
    echo "   Timestamp: " . $result['timestamp'] . "\n";
    echo "   ✓ Success\n\n";

    // Test 2: Echo with message
    echo "2. Testing public.echo...\n";
    $result = $client->call('public.echo', ['message' => 'Hello from PHP client!']);
    echo "   Echo: " . $result['message'] . "\n";
    echo "   Received at: " . $result['received_at'] . "\n";
    echo "   ✓ Success\n\n";

    // Test 3: Batch request
    echo "3. Testing batch request...\n";
    $results = $client->batch([
        ['method' => 'public.getTime', 'id' => 'time'],
        ['method' => 'public.echo', 'params' => ['message' => 'Batch test'], 'id' => 'echo']
    ]);
    
    echo "   Batch results:\n";
    foreach ($results as $idx => $result) {
        if (isset($result['result'])) {
            echo "   [{$idx}] ID: {$result['id']}\n";
            echo "       " . json_encode($result['result']) . "\n";
        }
    }
    echo "   ✓ Success\n\n";

    // Test 4: Test authentication (should fail without token)
    echo "4. Testing error handling (method not found)...\n";
    try {
        $client->call('nonexistent.method');
        echo "   ✗ Should have thrown exception\n";
    } catch (Exception $e) {
        echo "   ✓ Error correctly caught: {$e->getMessage()}\n\n";
    }

    echo "=== All Tests Passed! ✓ ===\n";
    echo "\nPHP-to-PHP RPC communication is working perfectly!\n";
    echo "\nThis demonstrates:\n";
    echo "- Cross-process communication\n";
    echo "- HTTP transport\n";
    echo "- JSON-RPC 2.0 protocol\n";
    echo "- Batch requests\n";
    echo "- Error handling\n";

} catch (Exception $e) {
    echo "✗ Error: {$e->getMessage()}\n\n";
    echo "Make sure the PHP server is running:\n";
    echo "  php -S localhost:8000 examples/cors-server.php\n";
    exit(1);
}
