# Introspection

Introspection exposes method metadata through reserved `__rpc.*` methods. It is disabled by default.

## Enable Introspection

```php
use RpcPhpToolkit\RpcEndpoint;

$rpc = new RpcEndpoint('/api/rpc', null, [
    'enableIntrospection' => true,
    'introspectionPrefix' => '__rpc',
]);
```

## Expose Method Metadata

```php
$rpc->addMethod('math.add', function($params, $context) {
    return $params['a'] + $params['b'];
}, [
    'schema' => [
        'type' => 'object',
        'properties' => [
            'a' => ['type' => 'number'],
            'b' => ['type' => 'number'],
        ],
        'required' => ['a', 'b'],
    ],
    'exposeSchema' => true,
    'description' => 'Add two numbers',
]);
```

## Methods

- `__rpc.listMethods` lists registered user methods.
- `__rpc.describe` returns metadata for one exposed method.
- `__rpc.describeAll` returns metadata for all exposed methods.
- `__rpc.version` returns toolkit and PHP version information.
- `__rpc.capabilities` returns endpoint capabilities such as batch, validation, introspection, and Safe Mode.

## Security Notes

- Methods with `exposeSchema` disabled are hidden from `__rpc.describe`.
- Introspection methods cannot describe themselves.
- User methods cannot be registered under the introspection prefix.
- Use a custom `introspectionPrefix` if your application already uses `__rpc.*`.

See [`examples/introspection/`](../examples/introspection/) for runnable examples.
