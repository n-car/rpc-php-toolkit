<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * PHP client to test the RPC server
 */

class RpcClient
{
    private string $url;
    private array $headers;
    private int $timeout;

    public function __construct(string $url, array $headers = [], int $timeout = 30)
    {
        $this->url = $url;
        $this->headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json'
        ], $headers);
        $this->timeout = $timeout;
    }

    /**
     * Executes a single RPC call
     */
    public function call(string $method, array $params = [], $id = null): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params
        ];

        if ($id !== null) {
            $request['id'] = $id;
        }

        return $this->sendRequest($request);
    }

    /**
     * Executes an RPC notification (no response)
     */
    public function notify(string $method, array $params = []): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params
        ];

        $this->sendRequest($request, false);
    }

    /**
     * Executes batch requests
     */
    public function batch(array $requests): array
    {
        $batchRequest = [];
        
        foreach ($requests as $request) {
            $rpcRequest = [
                'jsonrpc' => '2.0',
                'method' => $request['method'],
                'params' => $request['params'] ?? []
            ];
            
            if (isset($request['id'])) {
                $rpcRequest['id'] = $request['id'];
            }
            
            $batchRequest[] = $rpcRequest;
        }

        return $this->sendRequest($batchRequest);
    }

    /**
     * Sends the HTTP request
     */
    private function sendRequest(array $request, bool $expectResponse = true): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $this->headers),
                'content' => json_encode($request),
                'timeout' => $this->timeout
            ]
        ]);

        $response = file_get_contents($this->url, false, $context);
        
        if ($response === false) {
            throw new \Exception('HTTP request error');
        }

        if (!$expectResponse) {
            return [];
        }

        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    public function setAuthToken(string $token): self
    {
        $this->headers['Authorization'] = 'Bearer ' . $token;
        return $this;
    }
}

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
