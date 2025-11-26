<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use RpcPhpToolkit\RpcSafeEndpoint;
use RpcPhpToolkit\Client\RpcSafeClient;

/**
 * Example: Using RpcSafeEndpoint and RpcSafeClient
 * 
 * This example demonstrates how to use the Safe mode classes
 * for better type preservation and automatic safe mode handling.
 */

// ===== SERVER SIDE =====

// Create a safe endpoint (Safe Mode enabled by default)
$rpc = new RpcSafeEndpoint('/api', ['server' => 'example']);

// Register methods that handle special types
$rpc->addMethod('getCurrentTime', function() {
    return [
        'timestamp' => time(),
        'datetime' => new DateTime('now'),
        'timezone' => date_default_timezone_get()
    ];
});

$rpc->addMethod('mathOperations', function($params) {
    return [
        'infinity' => INF,
        'negativeInfinity' => -INF,
        'notANumber' => NAN,
        'result' => $params['a'] + $params['b']
    ];
});

$rpc->addMethod('echo', function($params) {
    return $params;
});

// Handle incoming request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $response = $rpc->handleRequest($input);
    
    header('Content-Type: application/json');
    echo $response;
    exit;
}

// ===== CLIENT SIDE =====

// Create a safe client (Safe Mode enabled by default)
$client = new RpcSafeClient('http://localhost:8080/api');

try {
    // Call methods
    $time = $client->call('getCurrentTime');
    echo "Current time: " . json_encode($time, JSON_PRETTY_PRINT) . "\n\n";
    
    $math = $client->call('mathOperations', ['a' => 10, 'b' => 5]);
    echo "Math operations: " . json_encode($math, JSON_PRETTY_PRINT) . "\n\n";
    
    $echo = $client->call('echo', ['test' => 'value', 'number' => 42]);
    echo "Echo: " . json_encode($echo, JSON_PRETTY_PRINT) . "\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// You can still override options if needed
$customClient = new RpcSafeClient('http://localhost:8080/api', [], [
    'timeout' => 10,
    'verifySSL' => false,
    // safeEnabled is still true by default
]);

echo "\n=== Safe Mode Classes Demo ===\n";
echo "RpcSafeEndpoint: Automatically enables Safe Mode for the server\n";
echo "RpcSafeClient: Automatically enables Safe Mode for the client\n";
echo "Both classes provide a cleaner API compared to manual option setting\n";
