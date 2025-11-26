<?php
/**
 * RPC PHP Toolkit - Introspection Client Example
 * 
 * This example demonstrates how to use introspection methods
 * to discover available RPC methods and their capabilities.
 */

// Simple JSON-RPC client function
function rpcCall($url, $method, $params = null) {
    $request = [
        'jsonrpc' => '2.0',
        'method' => $method,
        'id' => rand(1, 10000)
    ];
    
    if ($params !== null) {
        $request['params'] = $params;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: $httpCode");
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['error'])) {
        throw new Exception($result['error']['message']);
    }
    
    return $result['result'];
}

$serverUrl = 'http://localhost:8080/rpc';

echo "=== RPC Introspection Client Example ===\n\n";

try {
    // 1. List all available methods
    echo "1. List all methods:\n";
    $methods = rpcCall($serverUrl, '__rpc.listMethods');
    echo "   Available methods: " . implode(', ', $methods) . "\n\n";

    // 2. Get server version
    echo "2. Server version:\n";
    $version = rpcCall($serverUrl, '__rpc.version');
    echo "   Toolkit: {$version['toolkit']}\n";
    echo "   Version: {$version['version']}\n";
    echo "   PHP: {$version['phpVersion']}\n\n";

    // 3. Get server capabilities
    echo "3. Server capabilities:\n";
    $capabilities = rpcCall($serverUrl, '__rpc.capabilities');
    echo "   Batch: " . ($capabilities['batch'] ? 'Yes' : 'No') . "\n";
    echo "   Introspection: " . ($capabilities['introspection'] ? 'Yes' : 'No') . "\n";
    echo "   Validation: " . ($capabilities['validation'] ? 'Yes' : 'No') . "\n";
    echo "   Method count: {$capabilities['methodCount']}\n\n";

    // 4. Describe a specific method
    echo "4. Describe 'add' method:\n";
    $describe = rpcCall($serverUrl, '__rpc.describe', ['method' => 'add']);
    echo "   Name: {$describe['name']}\n";
    echo "   Description: {$describe['description']}\n";
    echo "   Schema: " . json_encode($describe['schema'], JSON_PRETTY_PRINT) . "\n\n";

    // 5. Get all public methods
    echo "5. All methods with public schemas:\n";
    $allPublic = rpcCall($serverUrl, '__rpc.describeAll');
    foreach ($allPublic as $method) {
        echo "   - {$method['name']}";
        if (!empty($method['description'])) {
            echo ": {$method['description']}";
        }
        echo "\n";
    }
    echo "\n";

    // 6. Try to describe a private method (should fail)
    echo "6. Try to describe private method:\n";
    try {
        rpcCall($serverUrl, '__rpc.describe', ['method' => 'internalCalculation']);
    } catch (Exception $e) {
        echo "   Error (expected): {$e->getMessage()}\n\n";
    }

    // 7. Try to describe an introspection method (should fail)
    echo "7. Try to describe introspection method:\n";
    try {
        rpcCall($serverUrl, '__rpc.describe', ['method' => '__rpc.listMethods']);
    } catch (Exception $e) {
        echo "   Error (expected): {$e->getMessage()}\n\n";
    }

    // 8. Use discovered method
    echo "8. Call discovered 'add' method:\n";
    $sum = rpcCall($serverUrl, 'add', ['a' => 5, 'b' => 3]);
    echo "   5 + 3 = {$sum}\n\n";

} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Make sure the server is running: php -S localhost:8080 server.php\n";
}


echo "=== RPC Introspection Client Example ===\n\n";

try {
    // 1. List all available methods
    echo "1. List all methods:\n";
    $methods = $client->call('__rpc.listMethods');
    echo "   Available methods: " . implode(', ', $methods) . "\n\n";

    // 2. Get server version
    echo "2. Server version:\n";
    $version = $client->call('__rpc.version');
    echo "   Toolkit: {$version['toolkit']}\n";
    echo "   Version: {$version['version']}\n";
    echo "   PHP: {$version['phpVersion']}\n\n";

    // 3. Get server capabilities
    echo "3. Server capabilities:\n";
    $capabilities = $client->call('__rpc.capabilities');
    echo "   Batch: " . ($capabilities['batch'] ? 'Yes' : 'No') . "\n";
    echo "   Introspection: " . ($capabilities['introspection'] ? 'Yes' : 'No') . "\n";
    echo "   Validation: " . ($capabilities['validation'] ? 'Yes' : 'No') . "\n";
    echo "   Method count: {$capabilities['methodCount']}\n\n";

    // 4. Describe a specific method
    echo "4. Describe 'add' method:\n";
    $describe = $client->call('__rpc.describe', ['method' => 'add']);
    echo "   Name: {$describe['name']}\n";
    echo "   Description: {$describe['description']}\n";
    echo "   Schema: " . json_encode($describe['schema'], JSON_PRETTY_PRINT) . "\n\n";

    // 5. Get all public methods
    echo "5. All methods with public schemas:\n";
    $allPublic = $client->call('__rpc.describeAll');
    foreach ($allPublic as $method) {
        echo "   - {$method['name']}";
        if (!empty($method['description'])) {
            echo ": {$method['description']}";
        }
        echo "\n";
    }
    echo "\n";

    // 6. Try to describe a private method (should fail)
    echo "6. Try to describe private method:\n";
    try {
        $client->call('__rpc.describe', ['method' => 'internalCalculation']);
    } catch (Exception $e) {
        echo "   Error (expected): {$e->getMessage()}\n\n";
    }

    // 7. Try to describe an introspection method (should fail)
    echo "7. Try to describe introspection method:\n";
    try {
        $client->call('__rpc.describe', ['method' => '__rpc.listMethods']);
    } catch (Exception $e) {
        echo "   Error (expected): {$e->getMessage()}\n\n";
    }

    // 8. Use discovered method
    echo "8. Call discovered 'add' method:\n";
    $sum = $client->call('add', ['a' => 5, 'b' => 3]);
    echo "   5 + 3 = {$sum}\n\n";

} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
    echo "Make sure the server is running: php -S localhost:8080 server.php\n";
}
