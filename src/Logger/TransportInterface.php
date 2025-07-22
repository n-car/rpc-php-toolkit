<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Logger;

/**
 * Interface for logger transports
 */
interface TransportInterface
{
    public function write(array $record): void;
}
