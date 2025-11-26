<?php
declare(strict_types=1);
namespace RpcPhpToolkit\Middleware;
/**
 * CORS Middleware - Handles Cross-Origin Resource Sharing
 *
 * This middleware adds the necessary CORS headers to allow requests
 * from different origins. It also handles preflight OPTIONS requests.
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $options;
    /**
     * @param array $options CORS configuration
     *   - origin: Allowed origin(s) (string or array), default '*'
     *   - methods: Allowed HTTP methods (array), default ['GET', 'POST', 'OPTIONS']
     *   - headers: Allowed headers (array), default ['Content-Type', 'Authorization']
     *   - credentials: Allow credentials (bool), default false
     *   - maxAge: Preflight cache time in seconds (int), default 86400 (24 hours)
     *   - exposeHeaders: Headers to expose to client (array), default []
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'origin' => '*',
            'methods' => ['GET', 'POST', 'OPTIONS'],
            'headers' => ['Content-Type', 'Authorization', 'X-RPC-Safe'],
            'credentials' => false,
            'maxAge' => 86400,
            'exposeHeaders' => []
        ], $options);
    }
    /**
     * Execute CORS middleware (alias for handle)
     *
     * @param array $context Middleware context
     * @return array Modified context
     */
    public function execute(array $context): array
    {
        return $this->handle($context);
    }
    /**
     * Handle CORS middleware
     *
     * @param array $context Middleware context
     * @return array Modified context
     */
    public function handle(array $context): array
    {
        // Get request origin
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        // Determine allowed origin
        $allowedOrigin = $this->getAllowedOrigin($requestOrigin);
        // Set CORS headers
        if ($allowedOrigin !== null) {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
            // If origin is specific (not *), we can allow credentials
            if ($allowedOrigin !== '*' && $this->options['credentials']) {
                header('Access-Control-Allow-Credentials: true');
            }
        }
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->handlePreflight();
            exit(0); // Stop execution after preflight
        }
        // Set other CORS headers for actual requests
        if (!empty($this->options['exposeHeaders'])) {
            header('Access-Control-Expose-Headers: ' . implode(', ', $this->options['exposeHeaders']));
        }
        return $context;
    }
    /**
     * Get allowed origin based on configuration
     *
     * @param string $requestOrigin The origin from the request
     * @return string|null The allowed origin or null if not allowed
     */
    private function getAllowedOrigin(string $requestOrigin): ?string
    {
        $configuredOrigin = $this->options['origin'];
        // Wildcard - allow all
        if ($configuredOrigin === '*') {
            return '*';
        }
        // Single origin string
        if (is_string($configuredOrigin)) {
            return $requestOrigin === $configuredOrigin ? $configuredOrigin : null;
        }
        // Array of allowed origins
        if (is_array($configuredOrigin)) {
            if (in_array($requestOrigin, $configuredOrigin, true)) {
                return $requestOrigin;
            }
            // Check for wildcard patterns (e.g., "https://*.example.com")
            foreach ($configuredOrigin as $pattern) {
                if ($this->matchOriginPattern($requestOrigin, $pattern)) {
                    return $requestOrigin;
                }
            }
        }
        return null;
    }
    /**
     * Match origin against pattern with wildcard support
     *
     * @param string $origin The origin to check
     * @param string $pattern The pattern to match against
     * @return bool True if matches
     */
    private function matchOriginPattern(string $origin, string $pattern): bool
    {
        // Exact match
        if ($origin === $pattern) {
            return true;
        }
        // Wildcard pattern
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$/';
            return preg_match($regex, $origin) === 1;
        }
        return false;
    }
    /**
     * Handle preflight OPTIONS request
     */
    private function handlePreflight(): void
    {
        // Allow methods
        header('Access-Control-Allow-Methods: ' . implode(', ', $this->options['methods']));
        // Allow headers
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->options['headers']));
        // Max age for preflight cache
        header('Access-Control-Max-Age: ' . $this->options['maxAge']);
        // Set content type
        header('Content-Type: text/plain');
        header('Content-Length: 0');
        // Return 204 No Content for preflight
        http_response_code(204);
    }
    /**
     * Get current options
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}

