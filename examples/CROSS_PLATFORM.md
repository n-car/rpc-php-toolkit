# Cross-Platform RPC Examples

This folder contains examples of calling RPC servers across different platforms.

## PHP Server Examples

### 1. Basic Server
```bash
php -S localhost:8000 examples/basic-server.php
```

### 2. CORS-enabled Server
```bash
php -S localhost:8000 examples/cors-server.php
```

## Client Examples

### PHP Client → PHP Server
```bash
# Start PHP server
php -S localhost:8000 examples/basic-server.php

# Run PHP client (in another terminal)
php examples/client.php
```

### Node.js Client → PHP Server
```bash
# Start PHP server
php -S localhost:8000 examples/cors-server.php

# Run Node.js client (in another terminal)
node examples/node-to-php-client.mjs
```

### PHP Client → Node.js Express Server
```bash
# Start Express server (from rpc-express-toolkit)
cd ../rpc-express-toolkit
node examples/basic-server.js

# Run PHP client (in another terminal)
cd ../rpc-php-toolkit
php examples/php-to-express-client.php
```

### Browser Client → PHP Server
```bash
# Start PHP server with CORS
php -S localhost:8000 examples/cors-server.php

# Open in browser
# File: examples/test-client.html
# Or: examples/browser-test.html
```

## Cross-Platform Architecture

```
┌─────────────────┐
│   Browser JS    │
│  (rpc-client)   │
└────────┬────────┘
         │
         ↓
┌─────────────────┐      ┌─────────────────┐
│   Node.js JS    │ ←──→ │   PHP Server    │
│  (rpc-client)   │      │  (RpcEndpoint)  │
└────────┬────────┘      └────────┬────────┘
         │                         │
         ↓                         ↓
┌─────────────────┐      ┌─────────────────┐
│ Express Server  │ ←──→ │   PHP Client    │
│  (RpcEndpoint)  │      │  (RpcClient)    │
└─────────────────┘      └─────────────────┘
```

## Features Working Cross-Platform

✅ **JSON-RPC 2.0 Protocol** - Full compliance
✅ **Single Calls** - Regular method invocation
✅ **Batch Requests** - Multiple calls in one request
✅ **Notifications** - Fire-and-forget calls
✅ **Error Handling** - Proper error codes and messages
✅ **BigInt Support** - Large integer handling
✅ **Date Serialization** - DateTime/Date objects
✅ **Safe Mode** - Type-safe S: and D: prefixes (when enabled on both sides)
✅ **Authentication** - Bearer tokens
✅ **CORS** - Cross-origin requests from browser

## Safe Mode Cross-Platform

Enable safe mode on both client and server for maximum type safety:

**PHP Server:**
```php
$rpc = new RpcEndpoint('/api/rpc', null, [
    'safeEnabled' => true
]);
```

**PHP Client:**
```php
$client = new RpcClient('http://localhost:3000/api', [], [
    'safeEnabled' => true
]);
```

**JavaScript Client:**
```javascript
const client = new RpcClient('http://localhost:8000/api', {}, {
    safeEnabled: true
});
```

**Express Server:**
```javascript
const rpc = new RpcEndpoint(app, context, {
    safeEnabled: true
});
```

When both sides have `safeEnabled: true`, you get:
- Strings prefixed with `S:`
- Dates prefixed with `D:`
- BigInts suffixed with `n`
- No ambiguity in type detection

## Testing

1. Start a server (PHP or Express)
2. Run any client against it
3. Check that calls work correctly
4. Verify BigInt and Date handling
5. Test batch requests
6. Test error scenarios

All combinations should work seamlessly! 🚀
