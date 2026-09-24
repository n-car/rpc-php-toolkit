<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Middleware;

use RpcPhpToolkit\Exceptions\AuthException;

/**
 * Middleware for basic authentication
 */
class AuthMiddleware implements MiddlewareInterface
{
    private array $allowedMethods;
    /** @var callable */
    private $authenticator;
    private bool $required;
    public function __construct(
        callable $authenticator,
        array $allowedMethods = [],
        bool $required = true
    ) {
        $this->authenticator = $authenticator;
        $this->allowedMethods = $allowedMethods;
        $this->required = $required;
    }
    public function handle(array $context): array
    {
        $method = $context['method'] ?? '';
        // If method is in whitelist, pass through
        if (!empty($this->allowedMethods) && in_array($method, $this->allowedMethods, true)) {
            return $context;
        }
        // Extract authentication token
        $token = $this->extractToken($context);
        if (!$token && $this->required) {
            throw new AuthException(
                'Authentication required',
                ['reason' => 'Missing authentication token']
            );
        }
        if ($token) {
            // Authenticate user
            $user = ($this->authenticator)($token);
            if (!$user && $this->required) {
                throw new AuthException(
                    'Authentication failed',
                    ['reason' => 'Invalid authentication token']
                );
            }
            if ($user) {
                $context['authenticated_user'] = $user;

                // RpcEndpoint passes this nested application context to handlers.
                if (!isset($context['context'])) {
                    $context['context'] = [];
                }
                if (is_array($context['context'])) {
                    $context['context']['authenticated_user'] = $user;
                }
            }
        }
        return $context;
    }
    private function extractToken(array $context): ?string
    {
        // Check context headers first (for testing)
        foreach (($context['request']['headers'] ?? []) as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0 && is_string($value)) {
                return $this->parseBearerToken($value);
            }
        }

        // Authorization header from the web server. Some CGI/FastCGI setups
        // expose it through REDIRECT_HTTP_AUTHORIZATION.
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        return is_string($authHeader) ? $this->parseBearerToken($authHeader) : null;
    }

    private function parseBearerToken(string $header): ?string
    {
        if (preg_match('/^Bearer[ \t]+([^\s]+)$/i', trim($header), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
