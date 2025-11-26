/**
 * Node.js client calling PHP server
 * Run: node examples/node-to-php-client.mjs
 */

import fetch from 'node-fetch';

// Use the RpcClient from the toolkit (ES Module version)
class RpcClient {
    constructor(endpoint, defaultHeaders = {}, options = {}) {
        this.endpoint = endpoint;
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            ...defaultHeaders
        };
        this.requestId = Date.now();
        this.options = {
            safeEnabled: options.safeEnabled === true,
            warnOnUnsafe: options.warnOnUnsafe !== false,
            ...options
        };
    }

    async call(method, params = {}) {
        const request = {
            jsonrpc: '2.0',
            method: method,
            params: params,
            id: this.requestId++
        };

        const response = await fetch(this.endpoint, {
            method: 'POST',
            headers: this.defaultHeaders,
            body: JSON.stringify(request)
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();

        if (result.error) {
            throw new Error(`RPC Error ${result.error.code}: ${result.error.message}`);
        }

        return result.result;
    }

    async batch(requests) {
        const batchRequest = requests.map((req, idx) => ({
            jsonrpc: '2.0',
            method: req.method,
            params: req.params || {},
            id: req.id || this.requestId + idx
        }));

        const response = await fetch(this.endpoint, {
            method: 'POST',
            headers: this.defaultHeaders,
            body: JSON.stringify(batchRequest)
        });

        const result = await response.json();
        return result;
    }
}

// ==========================================
// Test Node.js → PHP Server
// ==========================================

async function main() {
    console.log('=== Node.js Client → PHP Server ===\n');

    // Create client pointing to PHP server
    const client = new RpcClient('http://localhost:8000/examples/cors-server.php', {}, {
        safeEnabled: false
    });

    try {
        // Test 1: Simple call
        console.log('1. Testing getTime...');
        const time = await client.call('public.getTime');
        console.log('   Result:', time);
        console.log('   ✓ Success\n');

        // Test 2: Call with parameters
        console.log('2. Testing echo with params...');
        const echo = await client.call('public.echo', {
            message: 'Hello from Node.js!'
        });
        console.log('   Result:', echo);
        console.log('   ✓ Success\n');

        // Test 3: Batch request
        console.log('3. Testing batch request...');
        const batch = await client.batch([
            { method: 'public.getTime', id: 1 },
            { method: 'public.echo', params: { message: 'Batch from Node' }, id: 2 }
        ]);
        console.log('   Results:', batch);
        console.log('   ✓ Success\n');

        console.log('=== All Tests Passed! ✓ ===');
        console.log('\nNode.js can successfully call PHP RPC server!');

    } catch (error) {
        console.error('✗ Error:', error.message);
        process.exit(1);
    }
}

main();
