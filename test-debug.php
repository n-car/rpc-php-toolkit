<?php

require_once __DIR__ . '/vendor/autoload.php';

use RpcPhpToolkit\RpcEndpoint;

$endpoint = new RpcEndpoint('/rpc', null, [
    'enableLogging' => false
]);

$endpoint->addMethod(
    'add',
    function($params) {
        return $params['a'] + $params['b'];
    }
);

$request = json_encode([
    'jsonrpc' => '2.0',
    'method' => 'add',
    'params' => ['a' => 5, 'b' => 3],
    'id' => 1
]);

echo "Request:\n";
echo $request . "\n\n";

$response = $endpoint->handleRequest($request);

echo "Response:\n";
echo $response . "\n\n";

$decoded = json_decode($response, true);
echo "Decoded:\n";
print_r($decoded);
