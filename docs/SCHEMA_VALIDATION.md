# Schema Validation

RPC PHP Toolkit supports schema-style validation for method parameters. Invalid parameters return JSON-RPC error `-32602`.

```php
$rpc->addMethod('user.create', function($params, $context) {
    return [
        'id' => 123,
        'name' => $params['name'],
    ];
}, [
    'type' => 'object',
    'properties' => [
        'name' => [
            'type' => 'string',
            'minLength' => 2,
            'maxLength' => 50,
        ],
        'email' => [
            'type' => 'string',
            'format' => 'email',
        ],
    ],
    'required' => ['name', 'email'],
]);
```

## Supported Validation Areas

- basic types
- required object properties
- additional property checks
- string length and pattern checks
- number minimum, maximum, and multiple checks
- enum checks
- common formats such as email, URI, date, datetime, and UUID

The validator is intentionally lightweight. Treat it as method parameter validation for RPC endpoints, not as a full replacement for every JSON Schema feature.
