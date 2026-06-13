# Security

These notes cover production use of RPC PHP Toolkit endpoints.

## Validate Inputs

Use method schemas for public methods. Invalid parameters should fail before business logic runs.

## Sanitize Errors

Keep `sanitizeErrors` enabled in production so internal exception details are not exposed to callers.

```php
$rpc = new RpcEndpoint('/api/rpc', null, [
    'sanitizeErrors' => true,
]);
```

## Authenticate Protected Methods

Use `AuthMiddleware` or application routing before dispatching to protected methods.

```php
$rpc->getMiddleware()->add(new AuthMiddleware(function($token) {
    return validateToken($token);
}), 'before');
```

## Configure CORS Deliberately

Avoid wildcard CORS for authenticated browser clients. Configure explicit origins and headers in production.

## Rate Limit Public Endpoints

Use `RateLimitMiddleware` or an upstream reverse proxy to limit request volume.

## TLS

Keep SSL verification enabled for clients. Disable `verifySSL` only in local development against self-signed certificates.

```php
$client = new RpcClient('https://localhost:8443/api/rpc', [], [
    'verifySSL' => false,
]);
```

Do not use this setting in production.
