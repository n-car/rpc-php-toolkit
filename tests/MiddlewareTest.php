<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Tests;

use PHPUnit\Framework\TestCase;
use RpcPhpToolkit\Middleware\MiddlewareManager;
use RpcPhpToolkit\Middleware\RateLimitMiddleware;
use RpcPhpToolkit\Middleware\AuthMiddleware;

class MiddlewareTest extends TestCase
{
    private MiddlewareManager $manager;

    protected function setUp(): void
    {
        $this->manager = new MiddlewareManager();
    }

    public function testAddMiddleware(): void
    {
        $executed = false;
        
        $middleware = $this->createMock(\RpcPhpToolkit\Middleware\MiddlewareInterface::class);
        $middleware->method('execute')
            ->willReturnCallback(function($context) use (&$executed) {
                $executed = true;
                return $context;
            });

        $this->manager->add($middleware, 'before');
        $this->manager->executeMiddleware('before', ['test' => 'data']);

        $this->assertTrue($executed);
    }

    public function testMiddlewareOrder(): void
    {
        $order = [];

        $middleware1 = $this->createMock(\RpcPhpToolkit\Middleware\MiddlewareInterface::class);
        $middleware1->method('execute')
            ->willReturnCallback(function($context) use (&$order) {
                $order[] = 1;
                return $context;
            });

        $middleware2 = $this->createMock(\RpcPhpToolkit\Middleware\MiddlewareInterface::class);
        $middleware2->method('execute')
            ->willReturnCallback(function($context) use (&$order) {
                $order[] = 2;
                return $context;
            });

        $this->manager->add($middleware1, 'before');
        $this->manager->add($middleware2, 'before');
        $this->manager->executeMiddleware('before', []);

        $this->assertEquals([1, 2], $order);
    }

    public function testRateLimitMiddleware(): void
    {
        $rateLimiter = new RateLimitMiddleware(2, 60, 'ip');

        $context = [
            'request' => [
                'ip' => '127.0.0.1'
            ]
        ];

        // First two requests should pass
        $result1 = $rateLimiter->execute($context);
        $this->assertIsArray($result1);

        $result2 = $rateLimiter->execute($context);
        $this->assertIsArray($result2);

        // Third request should be rate limited
        $this->expectException(\RpcPhpToolkit\Exceptions\RpcException::class);
        $rateLimiter->execute($context);
    }

    public function testAuthMiddleware(): void
    {
        $authenticator = function($token) {
            return $token === 'valid-token';
        };

        $authMiddleware = new AuthMiddleware($authenticator);

        $validContext = [
            'request' => [
                'headers' => [
                    'Authorization' => 'Bearer valid-token'
                ]
            ]
        ];

        $result = $authMiddleware->execute($validContext);
        $this->assertIsArray($result);

        $invalidContext = [
            'request' => [
                'headers' => [
                    'Authorization' => 'Bearer invalid-token'
                ]
            ]
        ];

        $this->expectException(\RpcPhpToolkit\Exceptions\RpcException::class);
        $authMiddleware->execute($invalidContext);
    }
}
