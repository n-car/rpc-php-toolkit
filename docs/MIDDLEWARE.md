# Middleware

Middleware can run before request handling and can be used for CORS, authentication, rate limiting, and custom request context processing.

## Built-In Middleware

- `CorsMiddleware`
- `AuthMiddleware`
- `RateLimitMiddleware`

## Example

```php
use RpcPhpToolkit\Middleware\AuthMiddleware;
use RpcPhpToolkit\Middleware\CorsMiddleware;
use RpcPhpToolkit\Middleware\RateLimitMiddleware;

$middleware = $rpc->getMiddleware();

$middleware->add(new CorsMiddleware([
    'origin' => '*',
    'methods' => ['GET', 'POST', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-RPC-Safe-Enabled'],
]), 'before');

$middleware->add(new RateLimitMiddleware(100, 60, 'ip'), 'before');

$middleware->add(new AuthMiddleware(function($token) {
    return $token === 'secret-token' ? ['id' => 1] : null;
}), 'before');
```

## Custom Middleware

Implement `RpcPhpToolkit\Middleware\MiddlewareInterface` when you need application-specific request processing.

```php
use RpcPhpToolkit\Middleware\MiddlewareInterface;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function handle(array $context): array
    {
        $context['request_id'] = bin2hex(random_bytes(8));
        return $context;
    }
}
```

Register custom middleware in the same way as built-in middleware:

```php
$rpc->getMiddleware()->add(new RequestIdMiddleware(), 'before');
```
