<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Batch;

use RpcPhpToolkit\Logger\Logger;
use RpcPhpToolkit\Exceptions\InvalidRequestException;

/**
 * Handler for JSON-RPC batch requests
 */
class BatchHandler
{
    private int $maxBatchSize;
    private ?Logger $logger;

    public function __construct(int $maxBatchSize = 100, ?Logger $logger = null)
    {
        $this->maxBatchSize = $maxBatchSize;
        $this->logger = $logger;
    }

    /**
     * Handles a batch request
     */
    public function handleBatch(array $requests, callable $processor): array
    {
        if (empty($requests)) {
            throw new InvalidRequestException('Empty batch request');
        }

        if (count($requests) > $this->maxBatchSize) {
            throw new InvalidRequestException(
                "Batch too large: maximum {$this->maxBatchSize} requests"
            );
        }

        $this->logger?->info('Batch processing started', [
            'requests_count' => count($requests),
            'max_batch_size' => $this->maxBatchSize
        ]);

        $startTime = microtime(true);
        $responses = [];

        foreach ($requests as $index => $request) {
            try {
                $this->logger?->debug("Processing batch request #{$index}");

                $response = $processor($request);

                // Only add response if not empty (notifications have no response)
                if (!empty($response)) {
                    $responses[] = $response;
                }
            } catch (\Throwable $e) {
                $this->logger?->error("Error in batch request #{$index}", [
                    'error' => $e->getMessage(),
                    'request' => $request
                ]);

                // For parsing errors, use null as id
                $id = is_array($request) ? ($request['id'] ?? null) : null;

                $responses[] = [
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32603,
                        'message' => $e->getMessage()
                    ],
                    'id' => $id
                ];
            }
        }

        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->logger?->info('Batch processing completed', [
            'requests_processed' => count($requests),
            'responses_generated' => count($responses),
            'execution_time_ms' => round($executionTime, 2)
        ]);

        return $responses;
    }

    /**
     * Processes batch requests concurrently (simulated)
     */
    public function handleBatchConcurrent(array $requests, callable $processor): array
    {
        if (empty($requests)) {
            throw new InvalidRequestException('Empty batch request');
        }

        if (count($requests) > $this->maxBatchSize) {
            throw new InvalidRequestException(
                "Batch too large: maximum {$this->maxBatchSize} requests"
            );
        }

        $this->logger?->info('Concurrent batch processing started', [
            'requests_count' => count($requests)
        ]);

        $startTime = microtime(true);

        // Simulate concurrent processing by dividing into chunks
        $chunkSize = min(10, ceil(count($requests) / 4));
        $chunks = array_chunk($requests, $chunkSize, true);
        $responses = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            $this->logger?->debug("Processing chunk #{$chunkIndex}", [
                'chunk_size' => count($chunk)
            ]);

            foreach ($chunk as $index => $request) {
                try {
                    $response = $processor($request);

                    if (!empty($response)) {
                        $responses[] = $response;
                    }
                } catch (\Throwable $e) {
                    $this->logger?->error("Error in request #{$index}", [
                        'error' => $e->getMessage()
                    ]);

                    $id = is_array($request) ? ($request['id'] ?? null) : null;

                    $responses[] = [
                        'jsonrpc' => '2.0',
                        'error' => [
                            'code' => -32603,
                            'message' => $e->getMessage()
                        ],
                        'id' => $id
                    ];
                }
            }

            // Simulate delay for next chunk
            if ($chunkIndex < count($chunks) - 1) {
                usleep(1000); // 1ms
            }
        }

        $executionTime = (microtime(true) - $startTime) * 1000;

        $this->logger?->info('Concurrent batch processing completed', [
            'chunks_processed' => count($chunks),
            'total_responses' => count($responses),
            'execution_time_ms' => round($executionTime, 2)
        ]);

        return $responses;
    }

    /**
     * Validates a batch request
     */
    public function validateBatchRequest(array $requests): array
    {
        $errors = [];

        if (empty($requests)) {
            $errors[] = 'Batch cannot be empty';
            return $errors;
        }

        if (count($requests) > $this->maxBatchSize) {
            $errors[] = "Batch too large: maximum {$this->maxBatchSize} requests";
        }

        // Check for duplicate IDs
        $ids = [];
        foreach ($requests as $index => $request) {
            if (!is_array($request)) {
                $errors[] = "Request #{$index}: must be an object";
                continue;
            }

            if (isset($request['id']) && $request['id'] !== null) {
                if (in_array($request['id'], $ids)) {
                    $errors[] = "Duplicate ID: {$request['id']}";
                } else {
                    $ids[] = $request['id'];
                }
            }
        }

        return $errors;
    }

    public function getMaxBatchSize(): int
    {
        return $this->maxBatchSize;
    }

    public function setMaxBatchSize(int $maxBatchSize): self
    {
        $this->maxBatchSize = $maxBatchSize;
        return $this;
    }
}
