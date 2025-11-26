<?php

declare(strict_types=1);

// Use custom autoloader (composer not required for testing)
require_once __DIR__ . '/../autoload.php';

use RpcPhpToolkit\RpcEndpoint;
use RpcPhpToolkit\Middleware\CorsMiddleware;

/**
 * Example: CORS enabled RPC server
 */

$context = ['database' => null];

// Create endpoint with CORS middleware
$rpc = new RpcEndpoint('/api/rpc', $context);

// Add CORS middleware
$rpc->getMiddleware()->add(
    new CorsMiddleware([
        'origin' => '*',  // Allow all origins (or specify: ['https://example.com'])
        'methods' => ['GET', 'POST', 'OPTIONS'],
        'headers' => ['Content-Type', 'Authorization', 'X-RPC-Safe'],
        'credentials' => false,
        'maxAge' => 86400  // 24 hours preflight cache
    ]),
    'before'
);

// Add some test methods
$rpc->addMethod('public.getTime', function($params, $context) {
    return [
        'timestamp' => time(),
        'datetime' => date('c'),
        'timezone' => date_default_timezone_get()
    ];
});

$rpc->addMethod('public.echo', function($params, $context) {
    return [
        'message' => $params['message'] ?? 'Hello World!',
        'received_at' => date('c')
    ];
});

// Handle the request
$input = file_get_contents('php://input');
echo $rpc->handleRequest($input);
