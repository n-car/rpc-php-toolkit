<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Tests;

use PHPUnit\Framework\TestCase;
use RpcPhpToolkit\RpcEndpoint;
use RpcPhpToolkit\Exceptions\MethodNotFoundException;

class IntrospectionTest extends TestCase
{
    private RpcEndpoint $endpoint;
    
    protected function setUp(): void
    {
        // Create endpoint with introspection enabled
        $this->endpoint = new RpcEndpoint('/rpc', ['test' => 'context'], [
            'enableIntrospection' => true,
            'enableValidation' => true
        ]);

        // Register test methods
        $this->endpoint->addMethod(
            'add',
            function($params, $context) {
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

        $this->endpoint->addMethod(
            'echo',
            function($params, $context) {
                return $params;
            },
            [
                'exposeSchema' => true,
                'description' => 'Echo back parameters'
            ]
        );

        $this->endpoint->addMethod(
            'internalCalc',
            function($params, $context) {
                return $params['value'] * 2;
            },
            [
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'value' => ['type' => 'number']
                    ],
                    'required' => ['value']
                ],
                'exposeSchema' => false,  // Private method
                'description' => 'Internal calculation'
            ]
        );

        $this->endpoint->addMethod(
            'simple',
            function($params, $context) {
                return 'ok';
            }
        );
    }

    public function testListMethods(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.listMethods',
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertIsArray($response['result']);
        $this->assertContains('add', $response['result']);
        $this->assertContains('echo', $response['result']);
        $this->assertContains('internalCalc', $response['result']);
        $this->assertContains('simple', $response['result']);
        
        // Should not include introspection methods
        $this->assertNotContains('__rpc.listMethods', $response['result']);
        $this->assertNotContains('__rpc.describe', $response['result']);
    }

    public function testDescribePublicMethod(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.describe',
            'params' => ['method' => 'add'],
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('add', $response['result']['name']);
        $this->assertEquals('Add two numbers', $response['result']['description']);
        $this->assertIsArray($response['result']['schema']);
        $this->assertEquals('object', $response['result']['schema']['type']);
    }

    public function testDescribeMethodWithoutSchema(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.describe',
            'params' => ['method' => 'echo'],
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('echo', $response['result']['name']);
        $this->assertEquals('Echo back parameters', $response['result']['description']);
        $this->assertNull($response['result']['schema']);
    }

    public function testDescribePrivateMethod(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.describe',
            'params' => ['method' => 'internalCalc'],
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertStringContainsString('not available', $response['error']['message']);
    }

    public function testDescribeNonExistentMethod(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.describe',
            'params' => ['method' => 'nonExistent'],
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertStringContainsString('not found', $response['error']['message']);
    }

    public function testDescribeIntrospectionMethod(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.describe',
            'params' => ['method' => '__rpc.listMethods'],
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertStringContainsString('Cannot describe introspection methods', $response['error']['message']);
    }

    public function testDescribeWithoutMethodParameter(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.describe',
            'params' => [],
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32602, $response['error']['code']);
    }

    public function testDescribeAll(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.describeAll',
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertIsArray($response['result']);
        $this->assertCount(2, $response['result']);  // Only 'add' and 'echo' are exposed
        
        $names = array_column($response['result'], 'name');
        $this->assertContains('add', $names);
        $this->assertContains('echo', $names);
        $this->assertNotContains('internalCalc', $names);
        $this->assertNotContains('simple', $names);
    }

    public function testVersion(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.version',
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('rpc-php-toolkit', $response['result']['toolkit']);
        $this->assertArrayHasKey('version', $response['result']);
        $this->assertArrayHasKey('phpVersion', $response['result']);
        $this->assertEquals(PHP_VERSION, $response['result']['phpVersion']);
    }

    public function testCapabilities(): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.capabilities',
            'id' => 1
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('batch', $response['result']);
        $this->assertArrayHasKey('introspection', $response['result']);
        $this->assertArrayHasKey('validation', $response['result']);
        $this->assertArrayHasKey('middleware', $response['result']);
        $this->assertArrayHasKey('safeMode', $response['result']);
        $this->assertArrayHasKey('methodCount', $response['result']);
        $this->assertTrue($response['result']['introspection']);
        $this->assertEquals(4, $response['result']['methodCount']);
    }

    public function testCustomPrefix(): void
    {
        $endpoint = new RpcEndpoint('/rpc', null, [
            'enableIntrospection' => true,
            'introspectionPrefix' => '_meta'
        ]);

        $endpoint->addMethod('test', fn() => 'ok');

        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '_meta.listMethods',
            'id' => 1
        ]);

        $response = json_decode($endpoint->handleRequest($request), true);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertContains('test', $response['result']);
    }

    public function testIntrospectionDisabled(): void
    {
        $endpoint = new RpcEndpoint('/rpc', null, [
            'enableIntrospection' => false
        ]);

        $endpoint->addMethod('test', fn() => 'ok');

        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => '__rpc.listMethods',
            'id' => 1
        ]);

        $response = json_decode($endpoint->handleRequest($request), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertStringContainsString('not found', $response['error']['message']);
    }

    public function testReservedNamespaceProtection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved for RPC introspection');

        $this->endpoint->addMethod('__rpc.custom', fn() => 'bad');
    }

    public function testAllowOtherPrefixes(): void
    {
        $this->endpoint->addMethod('_custom', fn() => 'ok');
        $this->endpoint->addMethod('custom__rpc', fn() => 'ok');
        
        $methods = $this->endpoint->getMethods();
        $this->assertContains('_custom', $methods);
        $this->assertContains('custom__rpc', $methods);
    }

    public function testBatchWithIntrospection(): void
    {
        $request = json_encode([
            [
                'jsonrpc' => '2.0',
                'method' => '__rpc.listMethods',
                'id' => 1
            ],
            [
                'jsonrpc' => '2.0',
                'method' => '__rpc.version',
                'id' => 2
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'add',
                'params' => ['a' => 1, 'b' => 2],
                'id' => 3
            ]
        ]);

        $response = json_decode($this->endpoint->handleRequest($request), true);

        $this->assertIsArray($response);
        $this->assertCount(3, $response);

        $listResult = null;
        $versionResult = null;
        $addResult = null;
        
        foreach ($response as $item) {
            if ($item['id'] === 1) $listResult = $item;
            if ($item['id'] === 2) $versionResult = $item;
            if ($item['id'] === 3) $addResult = $item;
        }

        $this->assertNotNull($listResult);
        $this->assertIsArray($listResult['result']);
        $this->assertContains('add', $listResult['result']);

        $this->assertNotNull($versionResult);
        $this->assertEquals('rpc-php-toolkit', $versionResult['result']['toolkit']);

        $this->assertNotNull($addResult);
        $this->assertEquals(3, $addResult['result']);
    }
}
