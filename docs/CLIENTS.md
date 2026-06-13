# Clients

RPC PHP Toolkit includes a PHP HTTP client and bundled JavaScript client assets for browser testing. For new JavaScript-only projects, prefer the shared [`rpc-toolkit-js-client`](https://github.com/n-car/rpc-toolkit-js-client) package.

## PHP Client

```php
use RpcPhpToolkit\Client\RpcClient;

$client = new RpcClient('http://localhost:8000/api/rpc', [], [
    'timeout' => 30,
    'verifySSL' => true,
    'safeEnabled' => false,
]);

$result = $client->call('getTime');
$echo = $client->call('echo', ['message' => 'Hello']);
```

## Batch Requests

```php
$results = $client->batch([
    ['method' => 'getTime', 'id' => 1],
    ['method' => 'echo', 'params' => ['message' => 'Batch'], 'id' => 2],
    ['method' => 'log.event', 'params' => ['event' => 'batch-notify']],
]);
```

Requests without `id` are JSON-RPC notifications and do not produce response entries.

## Notifications

```php
$client->notify('log.event', [
    'source' => 'php-client',
]);
```

## Authentication Headers

```php
$client->setAuthToken('token-value');
$client->setHeader('X-Request-Source', 'example');
```

## Safe Client

```php
use RpcPhpToolkit\Client\RpcSafeClient;

$client = new RpcSafeClient('http://localhost:8000/api/rpc');
```

`RpcSafeClient` enables Safe Mode request serialization and requires the server to return the `X-RPC-Safe-Enabled` compatibility header.

## JavaScript Clients

The repository includes browser-oriented JavaScript client assets in `src/clients/` for compatibility with existing examples. For package-based browser or Node.js client code, use `rpc-toolkit-js-client`.
