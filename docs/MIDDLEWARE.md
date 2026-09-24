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
use RpcPhpToolkit\Middleware\MySqlRateLimitStore;
use RpcPhpToolkit\Middleware\RateLimitMiddleware;

$middleware = $rpc->getMiddleware();

$middleware->add(new CorsMiddleware([
    'environment' => 'production',
    'origin' => ['https://app.example.com'],
    'methods' => ['GET', 'POST', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-RPC-Safe-Enabled'],
]), 'before');

$rateLimitPdo = new PDO(
    'mysql:host=127.0.0.1;dbname=application;charset=utf8mb4',
    'application',
    'password',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$rateLimitStore = new MySqlRateLimitStore($rateLimitPdo);

// Run once during installation/migration, not on every request.
$rateLimitStore->createTable();

$middleware->add(
    new RateLimitMiddleware(100, 60, 'ip', $rateLimitStore, 'public-api'),
    'before'
);

$middleware->add(new AuthMiddleware(function($token) {
    return $token === 'secret-token' ? ['id' => 1] : null;
}), 'before');
```

`AuthMiddleware` accepts credentials only from `Authorization: Bearer <token>`.
It does not read tokens from the query string. The authenticated value returned
by the callback is available to RPC handlers as
`$context['authenticated_user']`.

`MySqlRateLimitStore` performs an atomic MySQL upsert and keeps counters across
PHP requests and workers. Give it a dedicated PDO connection so application
transaction rollbacks cannot undo rate-limit increments. Its bucket keys are
SHA-256 hashes; call `purgeExpired()` periodically to delete inactive buckets.
The default `InMemoryRateLimitStore` is suitable only for tests or a single
long-running process.

## Method-Level Middleware

A plain method stack runs before its handler:

```php
$rpc->addMethod('user.profile', $handler, [
    'middleware' => [$authorizationMiddleware],
]);
```

Use named phases when a method also needs response processing:

```php
$rpc->addMethod('report.create', $handler, [
    'middleware' => [
        'before' => [$authorizationMiddleware],
        'after' => [$auditMiddleware],
    ],
]);
```

Global `before` middleware runs first, followed by method `before` middleware.
After the handler, method `after` middleware runs before global `after`
middleware. Changes to `params`, `context`, and `result` are propagated.

## Custom Middleware

Implement `RpcPhpToolkit\Middleware\MiddlewareInterface` when you need application-specific request processing.

```php
use RpcPhpToolkit\Middleware\MiddlewareInterface;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function handle(array $context): array
    {
        $context['context'] = is_array($context['context'] ?? null)
            ? $context['context']
            : [];
        $context['context']['request_id'] = bin2hex(random_bytes(8));
        return $context;
    }
}
```

Register custom middleware in the same way as built-in middleware:

```php
$rpc->getMiddleware()->add(new RequestIdMiddleware(), 'before');
```
