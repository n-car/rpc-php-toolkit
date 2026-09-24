<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Tests;

use PHPUnit\Framework\TestCase;
use RpcPhpToolkit\Middleware\MiddlewareManager;
use RpcPhpToolkit\Middleware\RateLimitMiddleware;
use RpcPhpToolkit\Middleware\RateLimitStoreInterface;
use RpcPhpToolkit\Middleware\AuthMiddleware;
use RpcPhpToolkit\Middleware\CorsMiddleware;
use RpcPhpToolkit\Exceptions\AuthException;

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
        $middleware->method('handle')
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
        $middleware1->method('handle')
            ->willReturnCallback(function($context) use (&$order) {
                $order[] = 1;
                return $context;
            });

        $middleware2 = $this->createMock(\RpcPhpToolkit\Middleware\MiddlewareInterface::class);
        $middleware2->method('handle')
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
        $result1 = $rateLimiter->handle($context);
        $this->assertIsArray($result1);

        $result2 = $rateLimiter->handle($context);
        $this->assertIsArray($result2);

        // Third request should be rate limited
        $this->expectException(\RpcPhpToolkit\Exceptions\RpcException::class);
        $rateLimiter->handle($context);
    }

    public function testRateLimitStorePersistsAcrossMiddlewareInstances(): void
    {
        $store = new class implements RateLimitStoreInterface {
            private int $requests = 0;

            public function increment(string $key, int $timeWindow, int $now): array
            {
                return ['requests' => ++$this->requests, 'window_start' => $now];
            }
        };

        (new RateLimitMiddleware(1, 60, 'ip', $store))->handle([
            'request' => ['ip' => '127.0.0.1']
        ]);

        $this->expectException(\RpcPhpToolkit\Exceptions\RpcException::class);
        (new RateLimitMiddleware(1, 60, 'ip', $store))->handle([
            'request' => ['ip' => '127.0.0.1']
        ]);
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

        $result = $authMiddleware->handle($validContext);
        $this->assertIsArray($result);

        $invalidContext = [
            'request' => [
                'headers' => [
                    'Authorization' => 'Bearer invalid-token'
                ]
            ]
        ];

        $this->expectException(\RpcPhpToolkit\Exceptions\RpcException::class);
        $authMiddleware->handle($invalidContext);
    }

    public function testAuthMiddlewareDoesNotAcceptQueryStringToken(): void
    {
        $previousGet = $_GET;
        $_GET['token'] = 'valid-token';

        try {
            $middleware = new AuthMiddleware(fn($token) => $token === 'valid-token');
            $middleware->handle(['request' => ['headers' => []]]);
            $this->fail('A query-string token must not authenticate a request');
        } catch (AuthException $error) {
            $this->assertSame('Authentication required', $error->getMessage());
        } finally {
            $_GET = $previousGet;
        }
    }

    public function testAuthMiddlewareAddsUserToApplicationContext(): void
    {
        $middleware = new AuthMiddleware(fn($token) => ['id' => 42]);
        $result = $middleware->handle([
            'context' => ['application' => 'test'],
            'request' => ['headers' => ['authorization' => 'Bearer valid-token']]
        ]);

        $this->assertSame(['id' => 42], $result['authenticated_user']);
        $this->assertSame(['id' => 42], $result['context']['authenticated_user']);
    }

    public function testWildcardCorsIsRejectedInProduction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CorsMiddleware(['environment' => 'production', 'origin' => '*']);
    }

    public function testDevelopmentCorsCanUseWildcard(): void
    {
        $middleware = new CorsMiddleware(['environment' => 'development']);

        $this->assertSame('*', $middleware->getOptions()['origin']);
    }

    public function testWildcardCorsArrayIsRejectedInProduction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CorsMiddleware(['environment' => 'production', 'origin' => ['*']]);
    }
}
