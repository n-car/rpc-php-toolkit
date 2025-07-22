# RPC PHP Toolkit

[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

An enterprise-ready JSON-RPC 2.0 library for PHP applications with simplified APIs, structured logging, middleware system, schema validation, batch processing, and full BigInt/Date serialization support.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Advanced Usage](#advanced-usage)
  - [Configuration Options](#configuration-options)
  - [Schema Validation](#schema-validation)
  - [Middleware System](#middleware-system)
  - [Batch Requests](#batch-requests)
  - [Structured Logging](#structured-logging)
  - [Built-in Middleware](#built-in-middleware)
- [JavaScript Client](#javascript-client)
- [API Reference](#api-reference)
- [Examples](#examples)
- [Contributing](#contributing)
- [License](#license)

## Features

### Core Features
- **JSON-RPC 2.0 Compliance:** Fully adheres to JSON-RPC 2.0 specification
- **Server & Client Support:** Provides server endpoints and PHP & JavaScript client classes
- **Async Support:** Handles asynchronous operations with Promises
- **BigInt & Date Serialization:** Robust serialization/deserialization with timezone support
- **Cross-Platform:** Works in both browser and PHP server environments
- **Error Handling:** Comprehensive error responses with sanitization options

### Enterprise Features
- **🔧 Structured Logging:** Configurable logging with multiple transports and levels
- **⚡ Middleware System:** Extensible middleware with built-in rate limiting, CORS, auth
- **✅ Schema Validation:** JSON Schema validation with schema builder utilities
- **📦 Batch Processing:** Efficient batch request handling with concurrent processing
- **📊 Health & Metrics:** Built-in health check endpoints and metrics
- **🔒 Security:** Method whitelisting, authentication, and error sanitization
- **🎯 Performance:** Request timing, caching support, and optimized serialization

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Advanced Usage](#advanced-usage)
  - [Configuration Options](#configuration-options)
  - [Schema Validation](#schema-validation)
  - [Middleware System](#middleware-system)
  - [Batch Requests](#batch-requests)
  - [Structured Logging](#structured-logging)
  - [Built-in Middleware](#built-in-middleware)
- [JavaScript Client](#javascript-client)
- [API Reference](#api-reference)
- [Examples](#examples)
- [Contributing](#contributing)
- [License](#license)

## Caratteristiche


## Features

### Core Features
- **JSON-RPC 2.0 Compliance:** Fully adheres to the JSON-RPC 2.0 specification
- **Server & Client Support:** Provides server endpoints and PHP & JavaScript client classes
- **Async Support:** Handles asynchronous operations with Promises
- **BigInt & Date Serialization:** Robust serialization/deserialization with timezone support
- **Cross-Platform:** Works in both browser and PHP server environments
- **Error Handling:** Comprehensive error responses with sanitization options

### Enterprise Features
- **🔧 Structured Logging:** Configurable logging with multiple transports and levels
- **⚡ Middleware System:** Extensible middleware with built-in rate limiting, CORS, auth
- **✅ Schema Validation:** JSON Schema validation with schema builder utilities
- **📦 Batch Processing:** Efficient batch request handling with concurrent processing
- **📊 Health & Metrics:** Built-in health check endpoints and metrics
- **🔒 Security:** Method whitelisting, authentication, and error sanitization
- **🎯 Performance:** Request timing, caching support, and optimized serialization

```bash
composer require rpc-php-toolkit
```

## Quick Start

### Basic Setup

```php
<?php
require_once 'vendor/autoload.php';

use RpcPhpToolkit\RpcEndpoint;

// Context object to pass to method handlers
$context = ['database' => $db, 'config' => $config];

// Create the RPC endpoint
$rpc = new RpcEndpoint('/api/rpc', $context);

// Add methods
$rpc->addMethod('getTime', function($params, $context) {
    return [
        'timestamp' => time(),
        'datetime' => date('c')
    ];
});

$rpc->addMethod('echo', function($params, $context) {
    return ['message' => $params['message'] ?? 'Hello World!'];
});

// Handle requests
$input = file_get_contents('php://input');
echo $rpc->handleRequest($input);
```

### PHP Client

```php
use RpcPhpToolkit\Client\RpcClient;

$client = new RpcClient('http://localhost:8000/api/rpc');

// Single call
$result = $client->call('getTime');

// Call with parameters
$result = $client->call('echo', ['message' => 'Hello!']);

// Batch request
$results = $client->batch([
    ['method' => 'getTime', 'id' => 1],
    ['method' => 'echo', 'params' => ['message' => 'Test'], 'id' => 2]
]);
```

### JavaScript Client

```javascript
// Import ES Module
import RpcClient from './rpc-client.mjs';

// Or classic script loading
// <script src="rpc-client.js"></script>

const client = new RpcClient('http://localhost:8000/api/rpc');

// Single call
const result = await client.call('getTime');

// Call with parameters
const echo = await client.call('echo', {message: 'Hello!'});

// Batch request
const results = await client.batch([
    {method: 'getTime', id: 1},
    {method: 'echo', params: {message: 'Test'}, id: 2}
]);
```

## Advanced Usage

### Middleware

```php
use RpcPhpToolkit\Middleware\RateLimitMiddleware;
use RpcPhpToolkit\Middleware\AuthMiddleware;

// Rate limiting
$rpc->getMiddleware()->add(
    new RateLimitMiddleware(100, 60, 'ip'), 
    'before'
);

// Authentication
$rpc->getMiddleware()->add(
    new AuthMiddleware(function($token) {
        return $this->authenticateUser($token);
    }),
    'before'
);
```

### Schema Validation

```php
$rpc->addMethod('createUser', function($params, $context) {
    // User creation logic
    return ['id' => 123, 'name' => $params['name']];
}, [
    'type' => 'object',
    'properties' => [
        'name' => [
            'type' => 'string',
            'minLength' => 2,
            'maxLength' => 50
        ],
        'email' => [
            'type' => 'string',
            'format' => 'email'
        ]
    ],
    'required' => ['name', 'email']
]);
```

### Structured Logging

```php
use RpcPhpToolkit\Logger\Logger;
use RpcPhpToolkit\Logger\FileTransport;

$logger = new Logger([
    'level' => Logger::INFO,
    'transports' => [
        new FileTransport([
            'filename' => 'logs/rpc.log',
            'format' => 'json'
        ])
    ]
]);

$rpc = new RpcEndpoint('/api/rpc', $context, [
    'logger' => $logger
]);
```

## JavaScript Client

### Browser

```html
<!DOCTYPE html>
<html>
<head>
    <script src="rpc-client.js"></script>
</head>
<body>
    <script>
        const client = new RpcClient('http://localhost:8000/api/rpc');
        
        client.call('getTime').then(result => {
            console.log('Server time:', result);
        });
    </script>
</body>
</html>
```

### Node.js

```javascript
const RpcClient = require('./rpc-client.js');

const client = new RpcClient('http://localhost:8000/api/rpc');

async function test() {
    try {
        const result = await client.call('getTime');
        console.log(result);
    } catch (error) {
        console.error('RPC Error:', error.message);
    }
}
```

### ES Modules

```javascript
import RpcClient from './rpc-client.mjs';

const client = new RpcClient('http://localhost:8000/api/rpc');
const result = await client.call('getTime');
```

## Examples

The `examples/` folder contains:

- **`basic-server.php`** - Complete RPC server with all middleware
- **`client.php`** - Example PHP client with all tests
- **`browser-test.html`** - Interactive browser testing

### Quick start test server

```bash
cd examples
php -S localhost:8000 basic-server.php
```

Then open `browser-test.html` in your browser to test the interface.

## API Reference

### RpcEndpoint

#### Constructor
```php
new RpcEndpoint(string $endpoint = '/rpc', mixed $context = null, array $options = [])
```

#### Main Methods
- `addMethod(string $name, callable $handler, array $schema = null, array $middleware = []): self`
- `removeMethod(string $name): self`
- `handleRequest(string $input): string`
- `getLogger(): ?Logger`
- `getMiddleware(): ?MiddlewareManager`

### Configuration Options

```php
$options = [
    'sanitizeErrors' => true,        // Sanitize errors in production
    'enableBatch' => true,           // Enable batch requests
    'enableLogging' => true,         // Enable logging
    'enableValidation' => true,      // Enable schema validation
    'enableMiddleware' => true,      // Enable middleware system
    'maxBatchSize' => 100,          // Maximum batch size
    'timeout' => 30,                // Timeout in seconds
    'errorProperties' => [...]      // Error properties to include
];
```

### JSON-RPC Error Codes

- `-32600` - Invalid Request
- `-32601` - Method not found
- `-32602` - Invalid params
- `-32603` - Internal error
- `-32000` to `-32099` - Implementation specific errors

## Security

- **Error Sanitization**: In production, set `sanitizeErrors: true`
- **Rate Limiting**: Use `RateLimitMiddleware` to limit requests
- **Authentication**: Implement `AuthMiddleware` for protected methods
- **Input Validation**: Use schema validation for all parameters
- **CORS**: Configure CORS appropriately for browser clients

## Performance

- **Batch Processing**: Use batch requests for multiple operations
- **Efficient Middleware**: Middleware is executed in optimized order
- **Async Logging**: Configurable logger for different levels
- **Caching**: Implement caching at middleware level if needed

## Contributing

1. Fork the project
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is distributed under the MIT License. See the `LICENSE` file for details.


#### ⚠️ SSL and self-signed certificates in development (PHP)

If you need to connect to a server with a self-signed certificate during development in PHP, you can disable SSL verification for testing purposes (not recommended in production). For example, with cURL:

```php
$ch = curl_init('https://localhost:8000/api/rpc');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable certificate verification (development only)
$response = curl_exec($ch);
curl_close($ch);
```

Or with Guzzle:

```php
$client = new \GuzzleHttp\Client([
    'verify' => false // Disable certificate verification (development only)
]);
$response = $client->get('https://localhost:8000/api/rpc');
```

**Warning:** Disabling SSL verification exposes you to security risks. Use only in local development environments.
## Contributing

1. Fork the project
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is distributed under the MIT License. See the `LICENSE` file for details.

---

**RPC PHP Toolkit** - A professional JSON-RPC 2.0 implementation for PHP with enterprise features.
