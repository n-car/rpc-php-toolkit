<?php
/**
 * RPC PHP Toolkit - Introspection Server Example
 * 
 * This example demonstrates the introspection feature that allows
 * clients to discover available RPC methods, their schemas, and server capabilities.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use RpcPhpToolkit\RpcEndpoint;

// Create endpoint with introspection enabled
$endpoint = new RpcEndpoint('/rpc', null, [
    'enableIntrospection' => true,     // Enable introspection methods
    'introspectionPrefix' => '__rpc',  // Default prefix (can be customized)
    'enableValidation' => true,
    'enableLogging' => true
]);

// Register a public method with schema (will be discoverable)
$endpoint->addMethod(
    'add',
    function ($params, $context) {
        return $params['a'] + $params['b'];
    },
    [
        'schema' => [
            'type' => 'object',
            'properties' => [
                'a' => ['type' => 'number'],
                'b' => ['type' => 'number']
            ],
            'required' => ['a', 'b']
        ],
        'exposeSchema' => true,
        'description' => 'Add two numbers'
    ]
);

// Register a method with exposed schema but no description
$endpoint->addMethod(
    'multiply',
    function ($params, $context) {
        return $params['a'] * $params['b'];
    },
    [
        'schema' => [
            'type' => 'object',
            'properties' => [
                'a' => ['type' => 'number'],
                'b' => ['type' => 'number']
            ],
            'required' => ['a', 'b']
        ],
        'exposeSchema' => true
    ]
);

// Register a private method (schema not exposed)
$endpoint->addMethod(
    'internalCalculation',
    function ($params, $context) {
        return $params['value'] * 2 + 10;
    },
    [
        'schema' => [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'number']
            ],
            'required' => ['value']
        ],
        'exposeSchema' => false,  // Schema is private
        'description' => 'Internal calculation'
    ]
);

// Register a simple method without schema
$endpoint->addMethod(
    'echo',
    function ($params, $context) {
        return $params;
    },
    [
        'exposeSchema' => true,
        'description' => 'Echo back the parameters'
    ]
);

// Handle request
$input = file_get_contents('php://input');

if (empty($input)) {
    // Show available introspection methods
    header('Content-Type: application/json');
    echo json_encode([
        'service' => 'RPC PHP Toolkit - Introspection Example',
        'introspection' => [
            'enabled' => true,
            'methods' => [
                '__rpc.listMethods' => 'List all available user methods',
                '__rpc.describe' => 'Get schema and description of a specific method',
                '__rpc.describeAll' => 'Get all methods with exposed schemas',
                '__rpc.version' => 'Get server version information',
                '__rpc.capabilities' => 'Get server capabilities'
            ]
        ],
        'example' => [
            'method' => '__rpc.listMethods',
            'request' => [
                'jsonrpc' => '2.0',
                'method' => '__rpc.listMethods',
                'id' => 1
            ]
        ]
    ], JSON_PRETTY_PRINT);
    exit;
}

// Process RPC request
$response = $endpoint->handleRequest($input);
header('Content-Type: application/json');
echo $response;
