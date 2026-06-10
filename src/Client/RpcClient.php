<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Client;

use RpcPhpToolkit\Exceptions\InternalErrorException;
use RpcPhpToolkit\Exceptions\MethodNotFoundException;
use RpcPhpToolkit\Exceptions\InvalidParamsException;
use RpcPhpToolkit\Exceptions\InvalidRequestException;
use RpcPhpToolkit\Exceptions\RemoteRpcException;

/**
 * RPC Client for making JSON-RPC 2.0 calls
 */
class RpcClient
{
    private string $url;
    private array $headers;
    private int $timeout;
    private array $options;

    /**
     * @param string $url The RPC endpoint URL
     * @param array $headers Additional HTTP headers
     * @param array $options Client options (timeout, etc.)
     */
    public function __construct(string $url, array $headers = [], array $options = [])
    {
        $this->url = $url;
        $this->headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json'
        ], $headers);
        $this->options = array_merge([
            'timeout' => 30,
            'verifySSL' => true,
            'safeEnabled' => false,
        ], $options);
        $this->timeout = $this->options['timeout'];
    }

    /**
     * Executes a single RPC call
     *
     * @param string $method The method name to call
     * @param array $params Parameters to pass to the method
     * @param mixed $id Request ID (null for notification)
     * @return mixed The result from the RPC call
     * @throws MethodNotFoundException
     * @throws InvalidParamsException
     * @throws InvalidRequestException
     * @throws InternalErrorException
     */
    public function call(string $method, array $params = [], $id = null)
    {
        if ($id === null) {
            $id = uniqid('rpc_', true);
        }

        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id
        ];

        $response = $this->sendRequest($request);

        if (isset($response['error'])) {
            $error = $response['error'];
            $errorCode = $error['code'] ?? -32603;
            $errorMessage = $error['message'] ?? 'Unknown RPC error';
            $errorData = $error['data'] ?? null;

            // Use appropriate exception based on error code
            if ($errorCode === -32601) {
                throw new MethodNotFoundException($errorMessage, $errorData);
            } elseif ($errorCode === -32602) {
                throw new InvalidParamsException($errorMessage, $errorData);
            } elseif ($errorCode === -32600) {
                throw new InvalidRequestException($errorMessage, $errorData);
            } else {
                throw new RemoteRpcException($errorMessage, $errorCode, $errorData);
            }
        }

        return $response['result'] ?? null;
    }

    /**
     * Executes an RPC notification (no response expected)
     *
     * @param string $method The method name
     * @param array $params Parameters
     */
    public function notify(string $method, array $params = []): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params
        ];

        $this->sendRequest($request, false);
    }

    /**
     * Executes batch requests
     *
     * @param array $requests Array of request objects
     * @return array Array of responses
     * @throws InternalErrorException
     */
    public function batch(array $requests): array
    {
        $batchRequest = [];

        foreach ($requests as $request) {
            $rpcRequest = [
                'jsonrpc' => '2.0',
                'method' => $request['method'],
                'params' => $request['params'] ?? []
            ];

            if (isset($request['id'])) {
                $rpcRequest['id'] = $request['id'];
            }

            $batchRequest[] = $rpcRequest;
        }

        return $this->sendRequest($batchRequest);
    }

    /**
     * Sends the HTTP request
     *
     * @param array $request The RPC request
     * @param bool $expectResponse Whether to expect a response
     * @return array The decoded response
     * @throws InternalErrorException
     */
    private function sendRequest(array $request, bool $expectResponse = true): array
    {
        $request = $this->prepareRequest($request);
        $jsonRequest = json_encode($request);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InternalErrorException('Failed to encode request: ' . json_last_error_msg());
        }

        // Add safe mode header if enabled
        $headers = $this->headers;
        if ($this->options['safeEnabled']) {
            $headers[] = 'X-RPC-Safe-Enabled: true';
        }

        $contextOptions = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $jsonRequest,
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ]
        ];

        // SSL options
        if (!$this->options['verifySSL']) {
            $contextOptions['ssl'] = [
                'verify_peer' => false,
                'verify_peer_name' => false
            ];
        }

        $context = stream_context_create($contextOptions);
        $response = @file_get_contents($this->url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new InternalErrorException('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
        }

        if (!$expectResponse) {
            return [];
        }

        if ($response === '') {
            return [];
        }

        $responseHeaders = $http_response_header;
        $safeHeader = $this->getResponseHeader($responseHeaders, 'X-RPC-Safe-Enabled');

        if ($this->options['safeEnabled'] && $safeHeader === null) {
            throw new InternalErrorException(
                'RPC Compatibility Error: Client has safe serialization enabled but server did not respond ' .
                'with compatibility header (X-RPC-Safe-Enabled).'
            );
        }

        $serverSafeEnabled = strtolower((string) $safeHeader) === 'true';
        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InternalErrorException('Invalid JSON response: ' . json_last_error_msg());
        }

        return $this->decodeResponse($decoded, $serverSafeEnabled);
    }

    private function prepareRequest(array $request): array
    {
        if (!$this->options['safeEnabled']) {
            return $request;
        }

        if ($this->isList($request)) {
            return array_map(fn($item) => is_array($item) ? $this->prepareRequest($item) : $item, $request);
        }

        if (array_key_exists('params', $request)) {
            $request['params'] = $this->serializeValue($request['params']);
        }

        return $request;
    }

    private function decodeResponse(array $response, bool $safeEnabled): array
    {
        if ($this->isList($response)) {
            return array_map(
                fn($item) => is_array($item) ? $this->decodeSingleResponse($item, $safeEnabled) : $item,
                $response
            );
        }

        return $this->decodeSingleResponse($response, $safeEnabled);
    }

    private function decodeSingleResponse(array $response, bool $safeEnabled): array
    {
        if (array_key_exists('result', $response)) {
            $response['result'] = $this->deserializeValue($response['result'], $safeEnabled);
        }

        if (isset($response['error']) && is_array($response['error']) && array_key_exists('data', $response['error'])) {
            $response['error']['data'] = $this->deserializeValue($response['error']['data'], $safeEnabled);
        }

        return $response;
    }

    private function serializeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return 'D:' . $value->format('c');
        }

        if (is_int($value) && PHP_INT_SIZE === 8 && abs($value) > 9007199254740991) {
            return (string) $value . 'n';
        }

        if (is_string($value)) {
            return 'S:' . $value;
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->serializeValue($item);
            }
            return $result;
        }

        if (is_object($value)) {
            if ($value instanceof \JsonSerializable) {
                return $this->serializeValue($value->jsonSerialize());
            }

            if (method_exists($value, 'toArray')) {
                return $this->serializeValue($value->toArray());
            }
        }

        return $value;
    }

    private function deserializeValue(mixed $value, bool $safeEnabled): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->deserializeValue($item, $safeEnabled);
            }
            return $result;
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($safeEnabled && str_starts_with($value, 'S:')) {
            return substr($value, 2);
        }

        if ($safeEnabled && str_starts_with($value, 'D:')) {
            try {
                return new \DateTimeImmutable(substr($value, 2));
            } catch (\Exception) {
                return $value;
            }
        }

        if (preg_match('/^-?\d+n$/', $value)) {
            return $value;
        }

        if (!$safeEnabled && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                return $value;
            }
        }

        return $value;
    }

    private function getResponseHeader(array $headers, string $name): ?string
    {
        $prefix = strtolower($name) . ':';

        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), $prefix)) {
                return trim(substr($header, strlen($prefix)));
            }
        }

        return null;
    }

    private function isList(array $array): bool
    {
        return $array === [] || array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Sets authentication token
     *
     * @param string $token The authentication token
     * @return self
     */
    public function setAuthToken(string $token): self
    {
        // Remove existing Authorization header
        $this->headers = array_filter($this->headers, function ($header) {
            return !str_starts_with($header, 'Authorization:');
        });

        // Add new token
        $this->headers[] = 'Authorization: Bearer ' . $token;

        return $this;
    }

    /**
     * Sets a custom header
     *
     * @param string $name Header name
     * @param string $value Header value
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[] = $name . ': ' . $value;
        return $this;
    }

    /**
     * Gets the endpoint URL
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Gets timeout setting
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Sets timeout
     *
     * @param int $timeout Timeout in seconds
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        $this->options['timeout'] = $timeout;
        return $this;
    }
}
