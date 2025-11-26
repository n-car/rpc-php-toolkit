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
        $token = $this->extractToken();
        if (!$token && $this->required) {
            throw new AuthException(
                'Authentication required',
                -32001,
                ['reason' => 'Missing authentication token']
            );
        }
        
        if ($token) {
            // Authenticate user
            $user = ($this->authenticator)($token);
            if (!$user && $this->required) {
                throw new AuthException(
                    'Authentication failed',
                    -32002,
                    ['reason' => 'Invalid authentication token']
                );
            }
            
            if ($user) {
                $context['authenticated_user'] = $user;
            }
        }
        
        return $context;
    }

    private function extractToken(): ?string
    {
        // Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Query parameter
        return $_GET['token'] ?? null;
    }
}
