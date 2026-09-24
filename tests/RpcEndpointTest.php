<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Tests;

use PHPUnit\Framework\TestCase;
use RpcPhpToolkit\RpcEndpoint;
use RpcPhpToolkit\Exceptions\MethodNotFoundException;
use RpcPhpToolkit\Exceptions\InvalidRequestException;
use RpcPhpToolkit\Middleware\AuthMiddleware;
use RpcPhpToolkit\Middleware\MiddlewareInterface;

class RpcEndpointTest extends TestCase
{
    private RpcEndpoint $endpoint;
    
    protected function setUp(): void
    {
        $this->endpoint = new RpcEndpoint('/rpc', ['test' => 'context']);
    }

    public function testAddMethod(): void
    {
        $this->endpoint->addMethod('test.method', function($params, $context) {
            return ['success' => true, 'params' => $params];
        });

        $methods = $this->endpoint->getMethods();
        $this->assertContains('test.method', $methods);
    }

    public function testRemoveMethod(): void
    {
        $this->endpoint->addMethod('test.method', fn() => 'ok');
        $this->endpoint->removeMethod('test.method');

        $methods = $this->endpoint->getMethods();
        $this->assertNotContains('test.method', $methods);
    }

    public function testSimpleRpcCall(): void
    {
        $this->endpoint->addMethod('add', function($params, $context) {
            return $params['a'] + $params['b'];
        });

        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'add',
            'params' => ['a' => 5, 'b' => 3],
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(8, $response['result']);
        $this->assertEquals(1, $response['id']);
    }

    public function testMethodNotFound(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'nonexistent',
            'params' => [],
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertStringContainsString('Method not found', $response['error']['message']);
    }

    public function testInvalidJsonRpcVersion(): void
    {
        $request = json_encode([
            'jsonrpc' => '1.0',
            'method' => 'test',
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32600, $response['error']['code']);
    }

    public function testNotificationRequest(): void
    {
        $notificationReceived = false;
        
        $this->endpoint->addMethod('notify.test', function($params, $context) use (&$notificationReceived) {
            $notificationReceived = true;
            return 'processed';
        });

        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notify.test',
            'params' => ['data' => 'test']
            // No 'id' field = notification
        ]);

        $response = $this->endpoint->handleRequest($request);
        $decoded = json_decode($response, true);

        // Notifications should return empty response
        $this->assertEmpty($decoded);
    }

    public function testBatchRequest(): void
    {
        $endpoint = new RpcEndpoint('/rpc', null, ['enableBatch' => true]);
        $endpoint->addMethod('add', fn($params) => $params['a'] + $params['b']);
        $endpoint->addMethod('multiply', fn($params) => $params['a'] * $params['b']);

        $batchRequest = json_encode([
            ['jsonrpc' => '2.0', 'method' => 'add', 'params' => ['a' => 2, 'b' => 3], 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'multiply', 'params' => ['a' => 2, 'b' => 3], 'id' => 2]
        ]);

        $response = json_decode($endpoint->handleRequest($batchRequest), true);

        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertEquals(5, $response[0]['result']);
        $this->assertEquals(6, $response[1]['result']);
    }

    public function testContextPassedToHandler(): void
    {
        $contextValue = null;
        
        $this->endpoint->addMethod('getContext', function($params, $context) use (&$contextValue) {
            $contextValue = $context;
            return ['context' => $context];
        });

        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'getContext',
            'params' => [],
            'id' => 1
        ]);

        $this->endpoint->handleRequest($request);

        $this->assertEquals(['test' => 'context'], $contextValue);
    }

    public function testAuthenticatedUserIsPassedToHandlerContext(): void
    {
        $previousAuthorization = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid-token';

        try {
            $endpoint = new RpcEndpoint('/rpc');
            $endpoint->getMiddleware()->add(new AuthMiddleware(
                fn($token) => $token === 'valid-token' ? ['id' => 42] : null
            ));
            $endpoint->addMethod('user.current', fn($params, $context) => $context['authenticated_user']);

            $response = json_decode($endpoint->handleRequest(json_encode([
                'jsonrpc' => '2.0',
                'method' => 'user.current',
                'id' => 1
            ])), true);

            $this->assertSame(['id' => 42], $response['result']);
        } finally {
            if ($previousAuthorization === null) {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            } else {
                $_SERVER['HTTP_AUTHORIZATION'] = $previousAuthorization;
            }
        }
    }

    public function testMethodMiddlewareIsExecuted(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(array $context): array
            {
                $context['method_marker'] = 'executed';
                $context['params']['value']++;
                return $context;
            }
        };

        $this->endpoint->addMethod(
            'method.middleware',
            fn($params, $context) => [
                'value' => $params['value'],
                'marker' => $context['method_marker'] ?? null
            ],
            ['middleware' => [$middleware]]
        );

        $response = json_decode($this->endpoint->handleRequest(json_encode([
            'jsonrpc' => '2.0',
            'method' => 'method.middleware',
            'params' => ['value' => 1],
            'id' => 1
        ])), true);

        $this->assertSame(['value' => 2, 'marker' => 'executed'], $response['result']);
    }

    public function testMethodAfterMiddlewareCanTransformResult(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(array $context): array
            {
                $context['result'] = strtoupper($context['result']);
                return $context;
            }
        };

        $this->endpoint->addMethod(
            'method.after',
            fn() => 'ok',
            ['middleware' => ['after' => [$middleware]]]
        );

        $response = json_decode($this->endpoint->handleRequest(json_encode([
            'jsonrpc' => '2.0',
            'method' => 'method.after',
            'id' => 1
        ])), true);

        $this->assertSame('OK', $response['result']);
    }

    public function testProductionDefaultsDisableBatchAndIntrospection(): void
    {
        $endpoint = new RpcEndpoint('/rpc', null, ['environment' => 'production']);

        $this->assertFalse($endpoint->getOptions()['enableBatch']);
        $this->assertFalse($endpoint->getOptions()['enableIntrospection']);
    }

    public function testDevelopmentDefaultEnablesBatch(): void
    {
        $endpoint = new RpcEndpoint('/rpc', null, ['environment' => 'development']);

        $this->assertTrue($endpoint->getOptions()['enableBatch']);
        $this->assertFalse($endpoint->getOptions()['enableIntrospection']);
    }

    public function testSchemaValidation(): void
    {
        $this->endpoint->addMethod('createUser', function($params) {
            return ['id' => 123, 'name' => $params['name']];
        }, [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 2],
                'email' => ['type' => 'string', 'format' => 'email']
            ],
            'required' => ['name', 'email']
        ]);

        // Valid request
        $validRequest = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'createUser',
            'params' => ['name' => 'John', 'email' => 'john@example.com'],
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($validRequest), true);
        $this->assertArrayHasKey('result', $response);

        // Invalid request (missing email)
        $invalidRequest = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'createUser',
            'params' => ['name' => 'John'],
            'id' => 2
        ]);

        $response = json_decode($this->endpoint->handleRequest($invalidRequest), true);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32602, $response['error']['code']);
    }

    public function testInvalidJson(): void
    {
        $invalidJson = '{invalid json}';
        
        $response = json_decode($this->endpoint->handleRequest($invalidJson), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32600, $response['error']['code']);
    }

    public function testSanitizeErrors(): void
    {
        $endpoint = new RpcEndpoint('/rpc', null, ['sanitizeErrors' => true]);
        
        $endpoint->addMethod('throwError', function() {
            throw new \Exception('Sensitive error message with /path/to/file.php');
        });

        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'throwError',
            'id' => 1
        ]);

        $response = json_decode($endpoint->handleRequest($request), true);

        $this->assertArrayHasKey('error', $response);
        // With sanitize enabled, should not include full error details in data
        $this->assertArrayNotHasKey('data', $response['error']);
    }

    public function testGetters(): void
    {
        $this->assertEquals('/rpc', $this->endpoint->getEndpoint());
        $this->assertEquals(['test' => 'context'], $this->endpoint->getContext());
        $this->assertIsArray($this->endpoint->getOptions());
        $this->assertIsArray($this->endpoint->getMethods());
    }
}
