<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Logger;

/**
 * Structured logger for the RPC system
 */
class Logger
{
    public const EMERGENCY = 0;
    public const ALERT = 1;
    public const CRITICAL = 2;
    public const ERROR = 3;
    public const WARNING = 4;
    public const NOTICE = 5;
    public const INFO = 6;
    public const DEBUG = 7;

    private const LEVEL_NAMES = [
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT => 'ALERT',
        self::CRITICAL => 'CRITICAL',
        self::ERROR => 'ERROR',
        self::WARNING => 'WARNING',
        self::NOTICE => 'NOTICE',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG'
    ];

    private array $transports = [];
    private int $level;
    private array $context = [];

    public function __construct(array $options = [])
    {
        $this->level = $options['level'] ?? self::INFO;
        $this->context = $options['context'] ?? [];

        // Default transport: file
        if (empty($options['transports'])) {
            $this->addTransport(new FileTransport($options['file'] ?? []));
        } else {
            foreach ($options['transports'] as $transport) {
                $this->addTransport($transport);
            }
        }
    }

    public function addTransport(TransportInterface $transport): self
    {
        $this->transports[] = $transport;
        return $this;
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function log(int $level, string $message, array $context = []): void
    {
        if ($level > $this->level) {
            return;
        }

        $record = $this->createRecord($level, $message, $context);

        foreach ($this->transports as $transport) {
            $transport->write($record);
        }
    }

    private function createRecord(int $level, string $message, array $context): array
    {
        $record = [
            'timestamp' => new \DateTime(),
            'level' => $level,
            'level_name' => self::LEVEL_NAMES[$level],
            'message' => $message,
            'context' => array_merge($this->context, $context),
            'extra' => [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'pid' => getmypid(),
            ]
        ];

        // Add request information if available
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $record['extra']['request'] = [
                'method' => $_SERVER['REQUEST_METHOD'],
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ];
        }

        return $record;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function addContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
