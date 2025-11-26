<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Exceptions;

/**
 * Exception thrown when authentication fails
 */
class AuthException extends RpcException
{
    public function __construct(string $message = 'Authentication failed', mixed $data = null)
    {
        parent::__construct($message, -32001, $data);
    }
}
