<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Exceptions;

/**
 * Exception for internal server errors (-32603)
 */
class InternalErrorException extends RpcException
{
    public function __construct(string $message = 'Internal error', mixed $data = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, -32603, $data, $previous);
    }
}
