# Safe Mode

Standard JSON-RPC 2.0 is the default. Safe Mode is optional and should be enabled only when both sides are RPC Toolkit-compatible endpoints.

Safe Mode adds explicit markers for values that can be ambiguous across runtimes:

- strings: `S:value`
- dates: `D:2026-01-02T03:04:05+00:00`
- large integer markers: `9007199254740993n`

## Endpoint

```php
use RpcPhpToolkit\RpcSafeEndpoint;

$rpc = new RpcSafeEndpoint('/api/rpc');
```

Equivalent explicit configuration:

```php
$rpc = new RpcEndpoint('/api/rpc', null, [
    'safeEnabled' => true,
    'requireSafeHeader' => true,
]);
```

## Client

```php
use RpcPhpToolkit\Client\RpcSafeClient;

$client = new RpcSafeClient('http://localhost:8000/api/rpc');
```

Equivalent explicit configuration:

```php
$client = new RpcClient('http://localhost:8000/api/rpc', [], [
    'safeEnabled' => true,
]);
```

## HTTP Negotiation

Safe clients send `X-RPC-Safe-Enabled: true`.

Safe endpoints respond with `X-RPC-Safe-Enabled: true`. When `requireSafeHeader` is enabled, the endpoint rejects safe-mode requests that do not include the compatibility header.

## PHP Type Notes

- `DateTimeInterface` values are serialized as ISO strings, prefixed with `D:` in Safe Mode.
- Marker-like strings are protected with `S:` in Safe Mode.
- Large integer markers ending with `n` are preserved as strings on decode. PHP does not expose JavaScript `BigInt` semantics.
- Safe Mode applies recursively to arrays and nested objects represented as arrays.

See [`examples/safe-mode-example.php`](../examples/safe-mode-example.php) and [`examples/safe-mode-demo.php`](../examples/safe-mode-demo.php).
