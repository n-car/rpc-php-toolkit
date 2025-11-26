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
        if (!empty($this->allowedMethods) && in_array($method, $this->allowedMethods)) {
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
            }
        }
        return $context;
    }
    private function extractToken(array $context): ?string
    {
        // Check context headers first (for testing)
        if (isset($context['request']['headers']['Authorization'])) {
            $authHeader = $context['request']['headers']['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        // Authorization header from $_SERVER
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        // Query parameter
        return $_GET['token'] ?? null;
    }
}
