<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use RpcPhpToolkit\RpcEndpoint;
use RpcPhpToolkit\Logger\Logger;
use RpcPhpToolkit\Middleware\RateLimitMiddleware;
use RpcPhpToolkit\Middleware\AuthMiddleware;

/**
 * Basic example of RPC PHP Toolkit usage
 */

// Logger configuration
$logger = new Logger([
    'level' => Logger::INFO,
    'file' => [
        'filename' => __DIR__ . '/logs/rpc.log',
        'format' => 'json'
    ]
]);

// Application context
$context = [
    'database' => null, // Here you would put the DB connection
    'config' => [
        'version' => '1.0.0',
        'environment' => 'development'
    ]
];

// Create the RPC endpoint
$rpc = new RpcEndpoint('/api/rpc', $context, [
    'enableLogging' => true,
    'enableBatch' => true,
    'enableValidation' => true,
    'sanitizeErrors' => false, // For debugging
    'logger' => [
        'level' => Logger::DEBUG,
        'file' => [
            'filename' => __DIR__ . '/logs/rpc.log'
        ]
    ]
]);

// Add middleware
if ($rpc->getMiddleware()) {
    // Rate limiting: max 100 requests per minute per IP
    $rpc->getMiddleware()->add(new RateLimitMiddleware(100, 60, 'ip'), 'before');
    
    // Authentication for protected methods
    $rpc->getMiddleware()->add(new AuthMiddleware(
        function($token) {
            // Authentication simulation
            $validTokens = ['user123' => ['id' => 1, 'name' => 'Mario Rossi']];
            return $validTokens[$token] ?? null;
        },
        ['public.getTime', 'public.getVersion'], // Public methods
        false // Not required for all methods
    ), 'before');
}

// ========== METHOD REGISTRATION ==========

// Simple method
$rpc->addMethod('public.getTime', function($params, $context) {
    return [
        'timestamp' => time(),
        'datetime' => date('c'),
        'timezone' => date_default_timezone_get()
    ];
});

// Method with parameters
$rpc->addMethod('public.echo', function($params, $context) {
    return [
        'message' => $params['message'] ?? 'No message',
        'received_at' => date('c'),
        'context_version' => $context['config']['version'] ?? 'unknown'
    ];
}, [
    'type' => 'object',
    'properties' => [
        'message' => [
            'type' => 'string',
            'minLength' => 1,
            'maxLength' => 100
        ]
    ],
    'required' => ['message']
]);

// Method with calculation
$rpc->addMethod('calc.add', function($params, $context) {
    $a = $params['a'] ?? $params[0] ?? 0;
    $b = $params['b'] ?? $params[1] ?? 0;
    
    return [
        'result' => $a + $b,
        'operation' => 'addition',
        'operands' => [$a, $b]
    ];
}, [
    'type' => 'object',
    'properties' => [
        'a' => ['type' => 'number'],
        'b' => ['type' => 'number']
    ],
    'required' => ['a', 'b']
]);

// Protected method that requires authentication
$rpc->addMethod('user.getProfile', function($params, $context) {
    $user = $context['authenticated_user'] ?? null;
    
    if (!$user) {
        throw new \RpcPhpToolkit\Exceptions\RpcException(
            'User not authenticated',
            -32001
        );
    }
    
    return [
        'user_id' => $user['id'],
        'name' => $user['name'],
        'last_access' => date('c'),
        'permissions' => ['read', 'write']
    ];
});

// Method that simulates an error
$rpc->addMethod('test.error', function($params, $context) {
    $type = $params['type'] ?? 'generic';
    
    switch ($type) {
        case 'validation':
            throw new \RpcPhpToolkit\Exceptions\InvalidParamsException('Invalid parameters');
        case 'not_found':
            throw new \RpcPhpToolkit\Exceptions\MethodNotFoundException('Resource not found');
        case 'internal':
            throw new \RpcPhpToolkit\Exceptions\InternalErrorException('Internal server error');
        default:
            throw new \Exception('Generic test error');
    }
});

// ========== HTTP REQUEST HANDLING ==========

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only POST method accepted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32600,
            'message' => 'HTTP method not supported'
        ],
        'id' => null
    ]);
    exit;
}

// Read request body
$input = file_get_contents('php://input');

if (empty($input)) {
    http_response_code(400);
    echo json_encode([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32600,
            'message' => 'Empty request'
        ],
        'id' => null
    ]);
    exit;
}

// Process the RPC request
try {
    $response = $rpc->handleRequest($input);
    echo $response;
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32603,
            'message' => 'Internal server error',
            'data' => [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ],
        'id' => null
    ]);
}
