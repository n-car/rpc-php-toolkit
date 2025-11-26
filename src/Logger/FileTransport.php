<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Logger;

/**
 * Transport for writing logs to files
 */
class FileTransport implements TransportInterface
{
    private string $filename;
    private string $format;
    private bool $includeContext;

    public function __construct(array $options = [])
    {
        $this->filename = $options['filename'] ?? 'logs/rpc.log';
        $this->format = $options['format'] ?? 'json';
        $this->includeContext = $options['includeContext'] ?? true;

        // Create directory if it doesn't exist
        $dir = dirname($this->filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function write(array $record): void
    {
        $formatted = $this->format($record);

        $bytes = file_put_contents(
            $this->filename,
            $formatted . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($bytes === false) {
            throw new \RuntimeException("Unable to write to log file: {$this->filename}");
        }
    }

    private function format(array $record): string
    {
        if ($this->format === 'json') {
            return $this->formatJson($record);
        }

        return $this->formatText($record);
    }

    private function formatJson(array $record): string
    {
        $data = [
            'timestamp' => $record['timestamp']->format('c'),
            'level' => $record['level_name'],
            'message' => $record['message'],
        ];

        if ($this->includeContext && !empty($record['context'])) {
            $data['context'] = $record['context'];
        }

        if (!empty($record['extra'])) {
            $data['extra'] = $record['extra'];
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '{"error":"JSON encoding failed"}';
        }
        return $json;
    }

    private function formatText(array $record): string
    {
        $timestamp = $record['timestamp']->format('Y-m-d H:i:s');
        $level = str_pad($record['level_name'], 9);
        $message = $record['message'];

        $line = "[{$timestamp}] {$level}: {$message}";

        if ($this->includeContext && !empty($record['context'])) {
            $line .= ' ' . json_encode($record['context'], JSON_UNESCAPED_UNICODE);
        }

        return $line;
    }
}
