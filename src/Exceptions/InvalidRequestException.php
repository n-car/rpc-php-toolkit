<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Exceptions;

/**
 * Exception for invalid requests (-32600)
 */
class InvalidRequestException extends RpcException
{
    public function __construct(string $message = 'Invalid Request', mixed $data = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, -32600, $data, $previous);
    }
}
