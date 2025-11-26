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
    private string $introspectionPrefix = '__rpc';
    private bool $isInternalRegistration = false;

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
            'errorProperties' => self::DEFAULT_ERROR_PROPERTIES,
            'safeEnabled' => false,  // Safe serialization disabled by default (like Express)
            'warnOnUnsafe' => true,  // Warn when BigInt/Date serialized without safe mode
            'enableIntrospection' => false,  // Introspection disabled by default
            'introspectionPrefix' => '__rpc',  // Prefix for introspection methods
        ], $options);

        // Set introspection prefix from options
        if (isset($options['introspectionPrefix'])) {
            $this->introspectionPrefix = $options['introspectionPrefix'];
        }

        $this->initializeComponents();

        // Register introspection methods if enabled
        if ($this->options['enableIntrospection']) {
            $this->registerIntrospectionMethods();
        }
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
     *
     * @param string $name Method name
     * @param callable $handler Method handler
     * @param array|null $schema JSON Schema for parameter validation (or options array)
     * @param array $middleware Middleware stack (or can be in options)
     */
    public function addMethod(
        string $name,
        callable $handler,
        $schema = null,
        array $middleware = []
    ): self {
        // Prevent users from registering introspection methods
        if (str_starts_with($name, $this->introspectionPrefix . '.') && !$this->isInternalRegistration) {
            throw new \InvalidArgumentException(
                "Method names starting with '{$this->introspectionPrefix}.' are reserved for RPC introspection"
            );
        }

        // Support both old signature (schema, middleware) and new signature (options array)
        $options = [];
        if (is_array($schema) && isset($schema['exposeSchema'])) {
            // New format: options object containing schema, exposeSchema, description, middleware
            $options = $schema;
            $schema = $options['schema'] ?? null;
            $middleware = $options['middleware'] ?? [];
        } elseif (is_array($schema) && isset($schema['type'])) {
            // Old format: schema directly
            $options['schema'] = $schema;
        } elseif ($schema === null) {
            // No schema
            $options['schema'] = null;
        } else {
            // Assume it's a schema
            $options['schema'] = $schema;
            $schema = $options['schema'];
        }

        $this->methods[$name] = [
            'handler' => $handler,
            'schema' => $schema,
            'middleware' => $middleware,
            'exposeSchema' => $options['exposeSchema'] ?? false,
            'description' => $options['description'] ?? ''
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
     * Register introspection methods (__rpc.*)
     */
    private function registerIntrospectionMethods(): void
    {
        $this->isInternalRegistration = true;

        $introspectionPrefix = $this->introspectionPrefix;
        $methods = &$this->methods;
        $options = &$this->options;

        // __rpc.listMethods - List all user methods
        $this->addMethod("{$this->introspectionPrefix}.listMethods", function ($params, $context) use ($introspectionPrefix, &$methods) {
            $userMethods = [];
            foreach (array_keys($methods) as $name) {
                if (!str_starts_with($name, $introspectionPrefix . '.')) {
                    $userMethods[] = $name;
                }
            }
            return $userMethods;
        });

        // __rpc.describe - Get schema and description of a specific method
        $this->addMethod(
            "{$this->introspectionPrefix}.describe",
            function ($params, $context) use ($introspectionPrefix, &$methods) {
                if (!isset($params['method'])) {
                    throw new InvalidParamsException('Method name required');
                }

                $methodName = $params['method'];

                // Prevent introspection of __rpc.* methods
                if (str_starts_with($methodName, $introspectionPrefix . '.')) {
                    throw new MethodNotFoundException('Cannot describe introspection methods');
                }

                if (!isset($methods[$methodName])) {
                    throw new MethodNotFoundException("Method not found: {$methodName}");
                }

                $methodConfig = $methods[$methodName];

                // Check if schema is exposed
                if (!isset($methodConfig['exposeSchema']) || !$methodConfig['exposeSchema']) {
                    throw new MethodNotFoundException('Method schema not available');
                }

                return [
                    'name' => $methodName,
                    'schema' => $methodConfig['schema'] ?? null,
                    'description' => $methodConfig['description'] ?? ''
                ];
            },
            [
                'type' => 'object',
                'properties' => [
                    'method' => ['type' => 'string']
                ],
                'required' => ['method']
            ]
        );

        // __rpc.describeAll - Get all methods with public schemas
        $this->addMethod("{$this->introspectionPrefix}.describeAll", function ($params, $context) use ($introspectionPrefix, &$methods) {
            $publicMethods = [];

            foreach ($methods as $name => $methodConfig) {
                // Skip introspection methods
                if (str_starts_with($name, $introspectionPrefix . '.')) {
                    continue;
                }

                if (isset($methodConfig['exposeSchema']) && $methodConfig['exposeSchema']) {
                    $publicMethods[] = [
                        'name' => $name,
                        'schema' => $methodConfig['schema'] ?? null,
                        'description' => $methodConfig['description'] ?? ''
                    ];
                }
            }

            return $publicMethods;
        });

        // __rpc.version - Get version information
        $this->addMethod("{$this->introspectionPrefix}.version", function ($params, $context) {
            return [
                'toolkit' => 'rpc-php-toolkit',
                'version' => '1.1.0',
                'phpVersion' => PHP_VERSION
            ];
        });

        // __rpc.capabilities - Get server capabilities
        $this->addMethod("{$this->introspectionPrefix}.capabilities", function ($params, $context) use ($introspectionPrefix, &$methods, &$options) {
            return [
                'batch' => $options['enableBatch'],
                'introspection' => true,
                'validation' => $options['enableValidation'],
                'middleware' => $options['enableMiddleware'],
                'safeMode' => $options['safeEnabled'],
                'methodCount' => count(array_filter(
                    array_keys($methods),
                    fn($name) => !str_starts_with($name, $introspectionPrefix . '.')
                ))
            ];
        });

        $this->isInternalRegistration = false;
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

            // Check for safe mode header from client
            $clientSafeEnabled = ($_SERVER['HTTP_X_RPC_SAFE'] ?? 'false') === 'true';

            // Log request
            $this->logger?->info('RPC request received', [
                'endpoint' => $this->endpoint,
                'request_id' => $request['id'] ?? null,
                'method' => $request['method'] ?? null,
                'safe_mode' => $clientSafeEnabled
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

            // Add safe mode header to response
            header('X-RPC-Safe-Enabled: ' . ($this->options['safeEnabled'] ? 'true' : 'false'));

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
        // Handle DateTime objects
        if ($value instanceof \DateTime) {
            $isoString = $value->format('c');

            // Warn if safe mode is disabled
            if (!$this->options['safeEnabled'] && $this->options['warnOnUnsafe']) {
                $this->logger?->warning('Date detected in serialization. Consider enabling safeEnabled option.');
            }

            // Add D: prefix if safe mode enabled
            return $this->options['safeEnabled'] ? 'D:' . $isoString : $isoString;
        }

        // Handle large integers (BigInt equivalent in PHP)
        if (is_int($value) && PHP_INT_SIZE === 8 && abs($value) > 9007199254740991) {
            // Warn if safe mode is disabled
            if (!$this->options['safeEnabled'] && $this->options['warnOnUnsafe']) {
                $this->logger?->warning('Large integer detected in serialization. Consider enabling safeEnabled option.');
            }

            return (string)$value . 'n';
        }

        // Handle strings - add S: prefix if safe mode enabled
        if (is_string($value)) {
            return $this->options['safeEnabled'] ? 'S:' . $value : $value;
        }

        // Handle arrays
        if (is_array($value)) {
            return array_map([$this, 'serializeValue'], $value);
        }

        // Handle objects
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
     * Deserializes a value (handles BigInt, Date with safe mode)
     */
    private function deserializeValue(mixed $value, bool $safeEnabled = false): mixed
    {
        if (!is_string($value)) {
            // Recursively deserialize arrays
            if (is_array($value)) {
                return array_map(fn($v) => $this->deserializeValue($v, $safeEnabled), $value);
            }
            return $value;
        }

        // Safe string check
        if ($safeEnabled && str_starts_with($value, 'S:')) {
            return substr($value, 2);
        }

        // Safe date check
        if ($safeEnabled && str_starts_with($value, 'D:')) {
            $dateStr = substr($value, 2);
            try {
                return new \DateTime($dateStr);
            } catch (\Exception $e) {
                return $value; // Return original if parsing fails
            }
        }

        // BigInt check (ends with 'n')
        if (str_ends_with($value, 'n') && is_numeric(substr($value, 0, -1))) {
            return (int)substr($value, 0, -1);
        }

        // ISO date detection (if not safe mode)
        if (!$safeEnabled && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
            try {
                return new \DateTime($value);
            } catch (\Exception $e) {
                return $value;
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
