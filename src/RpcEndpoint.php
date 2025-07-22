<?php

declare(strict_types=1);

namespace RpcPhpToolkit;

use RpcPhpToolkit\Logger\Logger;
use RpcPhpToolkit\Middleware\MiddlewareManager;
use RpcPhpToolkit\Validation\SchemaValidator;
use RpcPhpToolkit\Batch\BatchHandler;
use RpcPhpToolkit\Exceptions\RpcException;
use RpcPhpToolkit\Exceptions\InvalidRequestException;
use RpcPhpToolkit\Exceptions\MethodNotFoundException;
use RpcPhpToolkit\Exceptions\InvalidParamsException;
use RpcPhpToolkit\Exceptions\InternalErrorException;

/**
 * RPC Endpoint - Main class for handling JSON-RPC 2.0 endpoints
 */
class RpcEndpoint
{
    private string $endpoint;
    private array $methods = [];
    private Logger $logger;
    private MiddlewareManager $middleware;
    private SchemaValidator $validator;
    private BatchHandler $batchHandler;
    private array $options;
    private mixed $context;

    /**
     * Default properties to include in error serialization
     */
    private const DEFAULT_ERROR_PROPERTIES = [
        'code', 'message', 'file', 'line', 'trace', 'previous'
    ];

    public function __construct(
        string $endpoint = '/rpc',
        mixed $context = null,
        array $options = []
    ) {
        $this->endpoint = $endpoint;
        $this->context = $context;
        $this->options = array_merge([
            'sanitizeErrors' => true,
            'enableBatch' => true,
            'enableLogging' => true,
            'enableValidation' => true,
            'enableMiddleware' => true,
            'maxBatchSize' => 100,
            'timeout' => 30,
            'errorProperties' => self::DEFAULT_ERROR_PROPERTIES
        ], $options);

        $this->initializeComponents();
    }

    /**
     * Initializes system components
     */
    private function initializeComponents(): void
    {
        // Logger
        if ($this->options['enableLogging']) {
            $this->logger = new Logger($this->options['logger'] ?? []);
        }

        // Middleware Manager
        if ($this->options['enableMiddleware']) {
            $this->middleware = new MiddlewareManager($this->logger ?? null);
        }

        // Schema Validator
        if ($this->options['enableValidation']) {
            $this->validator = new SchemaValidator($this->options['validation'] ?? []);
        }

        // Batch Handler
        if ($this->options['enableBatch']) {
            $this->batchHandler = new BatchHandler(
                $this->options['maxBatchSize'],
                $this->logger ?? null
            );
        }
    }

    /**
     * Adds an RPC method
     */
    public function addMethod(
        string $name,
        callable $handler,
        ?array $schema = null,
        array $middleware = []
    ): self {
        $this->methods[$name] = [
            'handler' => $handler,
            'schema' => $schema,
            'middleware' => $middleware
        ];

        $this->logger?->info("RPC method added: {$name}");
        
        return $this;
    }

    /**
     * Removes an RPC method
     */
    public function removeMethod(string $name): self
    {
        unset($this->methods[$name]);
        $this->logger?->info("RPC method removed: {$name}");
        
        return $this;
    }

    /**
     * Handles an RPC request
     */
    public function handleRequest(string $input): string
    {
        $startTime = microtime(true);
        
        try {
            // Decode JSON
            $request = $this->decodeJson($input);
            
            // Log request
            $this->logger?->info('RPC request received', [
                'endpoint' => $this->endpoint,
                'request_id' => $request['id'] ?? null,
                'method' => $request['method'] ?? null
            ]);

            // Handle batch or single request
            if (is_array($request) && isset($request[0])) {
                // Batch request
                if (!$this->options['enableBatch']) {
                    throw new InvalidRequestException('Batch requests not enabled');
                }
                
                $response = $this->batchHandler->handleBatch($request, [$this, 'processSingleRequest']);
            } else {
                // Single request
                $response = $this->processSingleRequest($request);
            }

            // Log execution time
            $executionTime = (microtime(true) - $startTime) * 1000;
            $this->logger?->info('RPC request completed', [
                'execution_time_ms' => round($executionTime, 2),
                'response_id' => $response['id'] ?? null
            ]);

            return $this->encodeJson($response);
            
        } catch (\Throwable $e) {
            $this->logger?->error('Error handling RPC request', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            $errorResponse = $this->createErrorResponse(null, $e);
            return $this->encodeJson($errorResponse);
        }
    }

    /**
     * Processes a single RPC request
     */
    public function processSingleRequest(array $request): array
    {
        try {
            // Basic request validation
            $this->validateRequest($request);
            
            $method = $request['method'];
            $params = $request['params'] ?? [];
            $id = $request['id'] ?? null;

            // Check method existence
            if (!isset($this->methods[$method])) {
                throw new MethodNotFoundException("Method not found: {$method}");
            }

            $methodConfig = $this->methods[$method];

            // Execute pre-processing middleware
            if ($this->middleware) {
                $this->middleware->executeMiddleware('before', [
                    'method' => $method,
                    'params' => $params,
                    'id' => $id,
                    'context' => $this->context
                ]);
            }

            // Validate parameters with schema
            if ($this->validator && $methodConfig['schema']) {
                $this->validator->validateParams($params, $methodConfig['schema']);
            }

            // Execute method
            $result = $this->executeMethod($methodConfig['handler'], $params);

            // Execute post-processing middleware
            if ($this->middleware) {
                $this->middleware->executeMiddleware('after', [
                    'method' => $method,
                    'params' => $params,
                    'result' => $result,
                    'id' => $id,
                    'context' => $this->context
                ]);
            }

            // Notification request (no id)
            if ($id === null) {
                return []; // No response for notifications
            }

            return $this->createSuccessResponse($id, $result);
            
        } catch (RpcException $e) {
            return $this->createErrorResponse($request['id'] ?? null, $e);
        } catch (\Throwable $e) {
            $rpcError = new InternalErrorException($e->getMessage(), $e);
            return $this->createErrorResponse($request['id'] ?? null, $rpcError);
        }
    }

    /**
     * Executes an RPC method
     */
    private function executeMethod(callable $handler, array $params): mixed
    {
        // If parameters are associative array, pass as named arguments
        if ($this->isAssociativeArray($params)) {
            return $handler($params, $this->context);
        }
        
        // Otherwise pass as positional array + context
        $args = array_merge($params, [$this->context]);
        return $handler(...$args);
    }

    /**
     * Validates a basic RPC request
     */
    private function validateRequest(array $request): void
    {
        if (!isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0') {
            throw new InvalidRequestException('Invalid JSON-RPC version');
        }

        if (!isset($request['method']) || !is_string($request['method'])) {
            throw new InvalidRequestException('Method not specified or invalid');
        }

        if (isset($request['params']) && !is_array($request['params'])) {
            throw new InvalidRequestException('Invalid parameters');
        }
    }

    /**
     * Creates a success response
     */
    private function createSuccessResponse($id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => $this->serializeValue($result),
            'id' => $id
        ];
    }

    /**
     * Creates an error response
     */
    private function createErrorResponse($id, \Throwable $error): array
    {
        $errorData = [
            'code' => $error instanceof RpcException ? $error->getCode() : -32603,
            'message' => $error->getMessage()
        ];

        // Add additional data if present
        if ($error instanceof RpcException && $error->getData()) {
            $errorData['data'] = $error->getData();
        }

        // Serialize complete error if not sanitized
        if (!$this->options['sanitizeErrors']) {
            $errorData['data'] = $this->serializeError($error);
        }

        return [
            'jsonrpc' => '2.0',
            'error' => $errorData,
            'id' => $id
        ];
    }

    /**
     * Serializes an error
     */
    private function serializeError(\Throwable $error): array
    {
        $result = [];
        $properties = $this->options['errorProperties'];

        foreach ($properties as $property) {
            if (property_exists($error, $property)) {
                $result[$property] = $error->$property;
            }
        }

        $result['class'] = get_class($error);
        
        if ($error->getPrevious()) {
            $result['previous'] = $this->serializeError($error->getPrevious());
        }

        return $result;
    }

    /**
     * Serializes a value (handles BigInt, Date, etc.)
     */
    private function serializeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTime) {
            return [
                '__type' => 'Date',
                'value' => $value->format('c'),
                'timezone' => $value->getTimezone()->getName()
            ];
        }

        if (is_int($value) && PHP_INT_SIZE === 8 && abs($value) > 9007199254740991) {
            return [
                '__type' => 'BigInt',
                'value' => (string)$value
            ];
        }

        if (is_array($value)) {
            return array_map([$this, 'serializeValue'], $value);
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $this->serializeValue($value->toArray());
            }
            if (method_exists($value, 'jsonSerialize')) {
                return $this->serializeValue($value->jsonSerialize());
            }
        }

        return $value;
    }

    /**
     * Decodes JSON
     */
    private function decodeJson(string $json): array
    {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidRequestException('Invalid JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Encodes JSON
     */
    private function encodeJson(mixed $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InternalErrorException('JSON encoding error: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Checks if an array is associative
     */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Serves static client files
     */
    public static function serveClientScripts(string $basePath = '/vendor/rpc-client'): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        if (strpos($requestUri, $basePath) === 0) {
            $file = substr($requestUri, strlen($basePath));
            $clientPath = __DIR__ . '/clients' . $file;
            
            if (file_exists($clientPath) && is_file($clientPath)) {
                $ext = pathinfo($clientPath, PATHINFO_EXTENSION);
                $contentType = match($ext) {
                    'js' => 'application/javascript',
                    'mjs' => 'application/javascript',
                    'css' => 'text/css',
                    default => 'text/plain'
                };
                
                header('Content-Type: ' . $contentType);
                readfile($clientPath);
                exit;
            }
        }
    }

    // Getters
    public function getEndpoint(): string { return $this->endpoint; }
    public function getMethods(): array { return array_keys($this->methods); }
    public function getLogger(): ?Logger { return $this->logger ?? null; }
    public function getMiddleware(): ?MiddlewareManager { return $this->middleware ?? null; }
    public function getValidator(): ?SchemaValidator { return $this->validator ?? null; }
    public function getContext(): mixed { return $this->context; }
    public function getOptions(): array { return $this->options; }
}
