# Introspection Example

This example demonstrates the RPC introspection feature that allows clients to discover available methods, their schemas, and server capabilities.

## What is Introspection?

Introspection provides a set of special methods (prefixed with `__rpc.`) that allow clients to:
- Discover available RPC methods
- Get method schemas and descriptions
- Check server capabilities
- Understand API structure dynamically

## Available Introspection Methods

### `__rpc.listMethods`
Returns an array of all user-defined method names (excludes introspection methods).

**Example:**
```json
{
  "jsonrpc": "2.0",
  "method": "__rpc.listMethods",
  "id": 1
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": ["add", "multiply", "echo", "internalCalculation"],
  "id": 1
}
```

### `__rpc.describe`
Get schema and description of a specific method.

**Parameters:**
- `method` (string, required): Name of the method to describe

**Example:**
```json
{
  "jsonrpc": "2.0",
  "method": "__rpc.describe",
  "params": { "method": "add" },
  "id": 1
}
```

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "name": "add",
    "schema": {
      "type": "object",
      "properties": {
        "a": { "type": "number" },
        "b": { "type": "number" }
      },
      "required": ["a", "b"]
    },
    "description": "Add two numbers"
  },
  "id": 1
}
```

### `__rpc.describeAll`
Get all methods with exposed schemas.

**Example:**
```json
{
  "jsonrpc": "2.0",
  "method": "__rpc.describeAll",
  "id": 1
}
```

### `__rpc.version`
Get server version information.

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "toolkit": "rpc-php-toolkit",
    "version": "1.0.0",
    "phpVersion": "8.2.0"
  },
  "id": 1
}
```

### `__rpc.capabilities`
Get server capabilities and features.

**Response:**
```json
{
  "jsonrpc": "2.0",
  "result": {
    "batch": true,
    "introspection": true,
    "validation": true,
    "middleware": true,
    "safeMode": false,
    "methodCount": 4
  },
  "id": 1
}
```

## Running the Example

### Start the Server
```bash
cd examples/introspection
php -S localhost:8080 server.php
```

### Test with cURL

**List methods:**
```bash
curl -X POST http://localhost:8080/rpc \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"__rpc.listMethods","id":1}'
```

**Describe a method:**
```bash
curl -X POST http://localhost:8080/rpc \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"__rpc.describe","params":{"method":"add"},"id":1}'
```

**Get capabilities:**
```bash
curl -X POST http://localhost:8080/rpc \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"__rpc.capabilities","id":1}'
```

### Use the PHP Client
```bash
php client.php
```

## Configuration

Enable introspection in your RpcEndpoint:

```php
$endpoint = new RpcEndpoint('/rpc', null, [
    'enableIntrospection' => true,      // Enable introspection (default: false)
    'introspectionPrefix' => '__rpc',   // Prefix for introspection methods (default: '__rpc')
]);
```

## Exposing Method Schemas

Use `exposeSchema: true` to make a method discoverable:

```php
$endpoint->addMethod(
    'calculate',
    function ($params, $context) {
        return $params['a'] + $params['b'];
    },
    [
        'schema' => [
            'type' => 'object',
            'properties' => [
                'a' => ['type' => 'number'],
                'b' => ['type' => 'number']
            ],
            'required' => ['a', 'b']
        ],
        'exposeSchema' => true,             // Make schema public
        'description' => 'Calculate sum'    // Optional description
    ]
);
```

## Security Notes

- Introspection methods cannot describe themselves
- Private methods (with `exposeSchema: false`) are hidden from `__rpc.describe`
- Users cannot register methods with the introspection prefix (e.g., `__rpc.*`)
- The introspection prefix is configurable to avoid conflicts
