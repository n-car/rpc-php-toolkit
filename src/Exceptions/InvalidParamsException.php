<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Exceptions;

/**
 * Exception for invalid parameters (-32602)
 */
class InvalidParamsException extends RpcException
{
    public function __construct(string $message = 'Invalid params', mixed $data = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, -32602, $data, $previous);
    }
}
