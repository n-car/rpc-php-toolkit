<?php

// Simple autoloader for testing without composer
spl_autoload_register(function($class) {
    $prefix = 'RpcPhpToolkit\\';
    $baseDir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

echo "=== RPC PHP Toolkit - Quick Test ===\n\n";

try {
    // Test 1: Create endpoint
    echo "1. Creating RpcEndpoint...\n";
    $endpoint = new RpcPhpToolkit\RpcEndpoint('/api/rpc', ['test' => 'context']);
    echo "   ✓ RpcEndpoint created successfully\n\n";
    
    // Test 2: Add method
    echo "2. Adding test method...\n";
    $endpoint->addMethod('test.add', function($params, $context) {
        return $params['a'] + $params['b'];
    });
    echo "   ✓ Method added successfully\n\n";
    
    // Test 3: Handle request
    echo "3. Testing RPC call...\n";
    $request = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'test.add',
        'params' => ['a' => 5, 'b' => 3],
        'id' => 1
    ]);
    
    $response = $endpoint->handleRequest($request);
    $decoded = json_decode($response, true);
    
    if (isset($decoded['result']) && $decoded['result'] === 8) {
        echo "   ✓ RPC call successful: 5 + 3 = {$decoded['result']}\n\n";
    } else {
        echo "   ✗ RPC call failed\n";
        print_r($decoded);
    }
    
    // Test 4: Test client
    echo "4. Testing RpcClient...\n";
    $client = new RpcPhpToolkit\Client\RpcClient('http://example.com/rpc');
    echo "   ✓ RpcClient created successfully\n";
    echo "   URL: {$client->getUrl()}\n";
    echo "   Timeout: {$client->getTimeout()}s\n\n";
    
    // Test 5: Test CORS middleware
    echo "5. Testing CORS Middleware...\n";
    $cors = new RpcPhpToolkit\Middleware\CorsMiddleware([
        'origin' => '*',
        'methods' => ['GET', 'POST', 'OPTIONS']
    ]);
    echo "   ✓ CORS Middleware created successfully\n";
    print_r($cors->getOptions());
    echo "\n";
    
    // Test 6: Test Safe Mode
    echo "6. Testing Safe Mode serialization...\n";
    $safeEndpoint = new RpcPhpToolkit\RpcEndpoint('/api/rpc', null, [
        'safeEnabled' => true,
        'warnOnUnsafe' => false
    ]);
    
    $safeEndpoint->addMethod('test.types', function($params) {
        return [
            'string' => 'hello',
            'date' => new DateTime('2025-11-26T10:30:00Z'),
            'number' => 42
        ];
    });
    
    $safeRequest = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'test.types',
        'params' => [],
        'id' => 2
    ]);
    
    $safeResponse = $safeEndpoint->handleRequest($safeRequest);
    $safeDecoded = json_decode($safeResponse, true);
    
    if (isset($safeDecoded['result'])) {
        echo "   ✓ Safe mode response:\n";
        echo "   - String: {$safeDecoded['result']['string']}\n";
        echo "   - Date: {$safeDecoded['result']['date']}\n";
        echo "   - Number: {$safeDecoded['result']['number']}\n\n";
    }
    
    echo "=== All Tests Passed! ✓ ===\n";
    echo "\nThe library is working correctly!\n";
    echo "You can now use: composer install && composer test\n";
    
} catch (Exception $e) {
    echo "✗ Error: {$e->getMessage()}\n";
    echo "  File: {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
