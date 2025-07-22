<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Exceptions;

/**
 * Exception for methods not found (-32601)
 */
class MethodNotFoundException extends RpcException
{
    public function __construct(string $message = 'Method not found', mixed $data = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, -32601, $data, $previous);
    }
}
