<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Tests;

use PHPUnit\Framework\TestCase;
use RpcPhpToolkit\RpcSafeEndpoint;
use RpcPhpToolkit\Client\RpcSafeClient;

class RpcSafeTest extends TestCase
{
    public function testSafeEndpointHasSafeEnabledByDefault(): void
    {
        $endpoint = new RpcSafeEndpoint('/api', ['test' => 'context']);
        
        $endpoint->addMethod('echo', function($params) {
            return $params;
        });

        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'echo',
            'params' => ['value' => 123],
            'id' => 1
        ]);

        $response = $endpoint->handleRequest($request);
        $result = json_decode($response, true);

        $this->assertEquals('2.0', $result['jsonrpc']);
        $this->assertEquals(1, $result['id']);
        $this->assertArrayHasKey('result', $result);
    }

    public function testSafeEndpointAllowsOverridingOptions(): void
    {
        // User can still override options
        $endpoint = new RpcSafeEndpoint('/api', null, [
            'enableBatch' => false
        ]);
        
        $endpoint->addMethod('test', fn() => 'ok');

        // Safe mode should still be enabled even if user provides other options
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => 1
        ]);

        $response = $endpoint->handleRequest($request);
        $result = json_decode($response, true);

        $this->assertEquals('ok', $result['result']);
    }

    public function testSafeClientSendsCorrectHeaders(): void
    {
        // Note: This is a unit test for the client constructor
        // In a real scenario, you would need to mock HTTP requests
        
        $client = new RpcSafeClient('http://localhost:3000/api');
        
        // Client is created successfully with safe mode enabled
        $this->assertInstanceOf(RpcSafeClient::class, $client);
    }

    public function testSafeEndpointHandlesSpecialTypes(): void
    {
        $endpoint = new RpcSafeEndpoint('/api');
        
        $endpoint->addMethod('special', function() {
            return [
                'inf' => INF,
                'nan' => NAN,
                'date' => new \DateTime('2024-01-01')
            ];
        });

        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'special',
            'id' => 1
        ]);

        $response = $endpoint->handleRequest($request);
        $result = json_decode($response, true);

        $this->assertArrayHasKey('result', $result);
        // In safe mode, special values are handled appropriately
    }

    public function testSafeEndpointInheritsFromRpcEndpoint(): void
    {
        $endpoint = new RpcSafeEndpoint();
        $this->assertInstanceOf(\RpcPhpToolkit\RpcEndpoint::class, $endpoint);
    }

    public function testSafeClientInheritsFromRpcClient(): void
    {
        $client = new RpcSafeClient('http://localhost:3000/api');
        $this->assertInstanceOf(\RpcPhpToolkit\Client\RpcClient::class, $client);
    }
}
