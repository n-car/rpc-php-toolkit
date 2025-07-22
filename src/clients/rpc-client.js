/**
 * RPC Client JavaScript per browser e Node.js
 * Compatibile con RPC PHP Toolkit
 */

class RpcClient {
    constructor(url, options = {}) {
        this.url = url;
        this.options = {
            timeout: 30000,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            ...options
        };
        this.requestId = 1;
    }

    /**
     * Esegue una chiamata RPC singola
     */
    async call(method, params = [], id = null) {
        const request = {
            jsonrpc: '2.0',
            method: method,
            params: params
        };

        if (id !== null) {
            request.id = id;
        } else {
            request.id = this.requestId++;
        }

        return this.sendRequest(request);
    }

    /**
     * Esegue una notifica RPC (senza risposta)
     */
    async notify(method, params = []) {
        const request = {
            jsonrpc: '2.0',
            method: method,
            params: params
        };

        return this.sendRequest(request, false);
    }

    /**
     * Esegue richieste batch
     */
    async batch(requests) {
        const batchRequest = requests.map(req => ({
            jsonrpc: '2.0',
            method: req.method,
            params: req.params || [],
            ...(req.id !== undefined ? { id: req.id } : { id: this.requestId++ })
        }));

        return this.sendRequest(batchRequest);
    }

    /**
     * Invia la richiesta HTTP
     */
    async sendRequest(request, expectResponse = true) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.options.timeout);

        try {
            const response = await fetch(this.url, {
                method: 'POST',
                headers: this.options.headers,
                body: JSON.stringify(request),
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            if (!expectResponse) {
                return null;
            }

            const result = await response.json();
            
            // Gestione deserializzazione tipi speciali
            return this.deserializeResponse(result);

        } catch (error) {
            clearTimeout(timeoutId);
            
            if (error.name === 'AbortError') {
                throw new Error('Timeout della richiesta');
            }
            
            throw error;
        }
    }

    /**
     * Deserializza la risposta gestendo tipi speciali
     */
    deserializeResponse(data) {
        if (Array.isArray(data)) {
            return data.map(item => this.deserializeResponse(item));
        }

        if (data && typeof data === 'object') {
            // Gestione BigInt
            if (data.__type === 'BigInt') {
                return BigInt(data.value);
            }

            // Gestione Date
            if (data.__type === 'Date') {
                return new Date(data.value);
            }

            // Ricorsione per oggetti annidati
            const result = {};
            for (const [key, value] of Object.entries(data)) {
                result[key] = this.deserializeResponse(value);
            }
            return result;
        }

        return data;
    }

    /**
     * Imposta il token di autenticazione
     */
    setAuthToken(token) {
        this.options.headers['Authorization'] = `Bearer ${token}`;
        return this;
    }

    /**
     * Rimuove il token di autenticazione
     */
    clearAuth() {
        delete this.options.headers['Authorization'];
        return this;
    }

    /**
     * Imposta headers personalizzati
     */
    setHeaders(headers) {
        this.options.headers = { ...this.options.headers, ...headers };
        return this;
    }

    /**
     * Imposta il timeout
     */
    setTimeout(timeout) {
        this.options.timeout = timeout;
        return this;
    }
}

// Esportazione per diversi ambienti
if (typeof module !== 'undefined' && module.exports) {
    // Node.js
    module.exports = RpcClient;
} else if (typeof window !== 'undefined') {
    // Browser
    window.RpcClient = RpcClient;
} else if (typeof self !== 'undefined') {
    // Web Workers
    self.RpcClient = RpcClient;
}
