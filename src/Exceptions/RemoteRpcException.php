<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Exceptions;

/**
 * Exception for non-standard JSON-RPC errors returned by a remote endpoint.
 */
class RemoteRpcException extends RpcException
{
    public function __construct(string $message, int $code, mixed $data = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $data, $previous);
    }
}
