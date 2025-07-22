<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Exceptions;

/**
 * Base exception for RPC errors
 */
abstract class RpcException extends \Exception
{
    protected mixed $data = null;

    public function __construct(string $message = '', int $code = 0, mixed $data = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    public function setData(mixed $data): self
    {
        $this->data = $data;
        return $this;
    }
}
