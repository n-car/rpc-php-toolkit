<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Client;

use RpcPhpToolkit\Exceptions\InternalErrorException;
use RpcPhpToolkit\Exceptions\MethodNotFoundException;
use RpcPhpToolkit\Exceptions\InvalidParamsException;
use RpcPhpToolkit\Exceptions\InvalidRequestException;

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
                throw new InvalidRequestException($errorMessage);
            } else {
                throw new InternalErrorException($errorMessage);
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
        $jsonRequest = json_encode($request);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InternalErrorException('Failed to encode request: ' . json_last_error_msg());
        }

        // Add safe mode header if enabled
        $headers = $this->headers;
        if ($this->options['safeEnabled']) {
            $headers[] = 'X-RPC-Safe: true';
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

        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InternalErrorException('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decoded;
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
        $this->headers = array_filter($this->headers, function($header) {
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
