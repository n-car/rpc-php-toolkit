# RPC PHP Toolkit

[![CI](https://github.com/n-car/rpc-php-toolkit/actions/workflows/ci.yml/badge.svg)](https://github.com/n-car/rpc-php-toolkit/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/n-car/rpc-php-toolkit.svg)](https://packagist.org/packages/n-car/rpc-php-toolkit)
[![Packagist Downloads](https://img.shields.io/packagist/dt/n-car/rpc-php-toolkit.svg)](https://packagist.org/packages/n-car/rpc-php-toolkit)
[![PHP Version](https://img.shields.io/packagist/php-v/n-car/rpc-php-toolkit.svg)](https://packagist.org/packages/n-car/rpc-php-toolkit)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-stable-green.svg)](https://github.com/n-car/rpc-php-toolkit/releases)

PHP JSON-RPC 2.0 client/server toolkit with middleware, schema validation, batch processing, introspection, and optional RPC Toolkit Safe Mode.

Use this package when you need a PHP JSON-RPC endpoint, a PHP HTTP client, or a PHP runtime that interoperates with the broader RPC Toolkit ecosystem.

## Standard First

Standard JSON-RPC 2.0 is the default behavior. Enable RPC Toolkit Safe Mode only when both sides are compatible endpoints and need type-aware round-tripping for marker-like strings, dates, and large integer markers.

## Installation

Install from Packagist with Composer:

```bash
composer require n-car/rpc-php-toolkit
```

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use RpcPhpToolkit\RpcEndpoint;

$rpc = new RpcEndpoint('/api/rpc');

$rpc->addMethod('getTime', function($params, $context) {
    return [
        'timestamp' => time(),
        'datetime' => date('c')
    ];
});

$rpc->addMethod('echo', function($params, $context) {
    return ['message' => $params['message'] ?? 'Hello World'];
});

$input = file_get_contents('php://input');
echo $rpc->handleRequest($input);
```

## PHP Client

```php
use RpcPhpToolkit\Client\RpcClient;

$client = new RpcClient('http://localhost:8000/api/rpc');

$time = $client->call('getTime');
$echo = $client->call('echo', ['message' => 'Hello']);

$results = $client->batch([
    ['method' => 'getTime', 'id' => 1],
    ['method' => 'echo', 'params' => ['message' => 'Batch'], 'id' => 2],
]);

$client->notify('log.event', ['source' => 'php-client']);
```

## Key Capabilities

- JSON-RPC 2.0 calls, notifications, and batch requests
- PHP endpoint and PHP HTTP client
- Middleware for CORS, authentication, rate limiting, and custom request processing
- Schema validation for method parameters
- Optional introspection through `__rpc.*` methods
- Optional RPC Toolkit Safe Mode over HTTP headers
- Structured logging hooks and configurable error sanitization

## Advanced Documentation

- [Clients](docs/CLIENTS.md) - PHP client, Safe client, batch calls, notifications, and browser/Node.js client notes.
- [Schema Validation](docs/SCHEMA_VALIDATION.md) - method parameter schemas and validation errors.
- [Introspection](docs/INTROSPECTION.md) - `__rpc.listMethods`, `__rpc.describe`, `__rpc.describeAll`, `__rpc.version`, and `__rpc.capabilities`.
- [Middleware](docs/MIDDLEWARE.md) - CORS, authentication, rate limiting, and custom middleware.
- [Safe Mode](docs/SAFE_MODE.md) - optional type-aware serialization, headers, marker behavior, and PHP type notes.
- [Security](docs/SECURITY.md) - production hardening notes for validation, authentication, CORS, errors, and TLS.

## Examples

The [`examples/`](examples/) folder contains runnable PHP and cross-runtime examples:

- [`basic-server.php`](examples/basic-server.php) - HTTP endpoint with middleware and validation examples.
- [`client.php`](examples/client.php) - PHP client calls, batch requests, authentication, and errors.
- [`safe-mode-example.php`](examples/safe-mode-example.php) - `RpcSafeEndpoint` and `RpcSafeClient` usage.
- [`php-to-express-client.php`](examples/php-to-express-client.php) - PHP client calling an Express RPC endpoint.
- [`node-to-php-client.mjs`](examples/node-to-php-client.mjs) - JavaScript client calling a PHP endpoint.
- [`examples/introspection/`](examples/introspection/) - introspection server/client examples.

Quick local server:

```bash
cd examples
php -S localhost:8000 basic-server.php
```

## Related Packages

- [rpc-toolkit](https://github.com/n-car/rpc-toolkit) - ecosystem hub and compatibility reference
- [rpc-express-toolkit](https://github.com/n-car/rpc-express-toolkit) - Express implementation
- [rpc-node-toolkit](https://github.com/n-car/rpc-node-toolkit) - framework-agnostic Node.js core
- [rpc-toolkit-js-client](https://github.com/n-car/rpc-toolkit-js-client) - shared JavaScript client
- [rpc-dotnet-toolkit](https://github.com/n-car/rpc-dotnet-toolkit) - .NET implementation
- [rpc-java-toolkit](https://github.com/n-car/rpc-java-toolkit) - Java and Android implementation
- [rpc-python-toolkit](https://github.com/n-car/rpc-python-toolkit) - Python implementation
- [rpc-arduino-toolkit](https://github.com/n-car/rpc-arduino-toolkit) - Arduino/ESP32/ESP8266 implementation
- [node-red-contrib-rpc-toolkit](https://github.com/n-car/node-red-contrib-rpc-toolkit) - Node-RED nodes

## License

MIT. See [LICENSE](LICENSE).
