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

Send tokens only as `Authorization: Bearer <token>`. Query-string tokens are
not accepted because URLs commonly leak through logs, browser history, and
referrer headers. The authenticated user is propagated to the RPC method in
`$context['authenticated_user']`.

## Configure CORS Deliberately

Wildcard CORS is rejected when `environment` is `production` (the default).
Configure explicit origins and headers:

```php
$cors = new CorsMiddleware([
    'environment' => 'production',
    'origin' => ['https://app.example.com'],
]);
```

## Rate Limit Public Endpoints

Use `RateLimitMiddleware` with `MySqlRateLimitStore`, or an upstream reverse
proxy, to limit request volume. The in-memory store resets between PHP requests
on traditional hosting and must not be used there as the only rate limiter.

## Disable Discovery and Batch in Production

`RpcEndpoint` uses `APP_ENV` (or the `environment` option) and assumes
`production` when neither is provided. Batch and introspection are disabled by
default in production. Keep them disabled unless the endpoint explicitly needs
them:

```php
$rpc = new RpcEndpoint('/api/rpc', $context, [
    'environment' => 'production',
    'enableBatch' => false,
    'enableIntrospection' => false,
]);
```

## TLS

Keep SSL verification enabled for clients. Disable `verifySSL` only in local development against self-signed certificates.

```php
$client = new RpcClient('https://localhost:8443/api/rpc', [], [
    'verifySSL' => false,
]);
```

Do not use this setting in production.
