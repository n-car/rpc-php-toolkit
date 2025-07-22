<?php

declare(strict_types=1);

/**
 * RPC PHP Toolkit - Libreria enterprise per JSON-RPC 2.0
 * 
 * @package RpcPhpToolkit
 * @version 1.0.0
 * @author Nicola Carpanese <nicola.carpanese@gmail.com>
 * @license MIT
 */

// Classe principale
require_once __DIR__ . '/RpcEndpoint.php';

// Eccezioni
require_once __DIR__ . '/Exceptions/RpcException.php';
require_once __DIR__ . '/Exceptions/InvalidRequestException.php';
require_once __DIR__ . '/Exceptions/MethodNotFoundException.php';
require_once __DIR__ . '/Exceptions/InvalidParamsException.php';
require_once __DIR__ . '/Exceptions/InternalErrorException.php';

// Logger
require_once __DIR__ . '/Logger/Logger.php';
require_once __DIR__ . '/Logger/TransportInterface.php';
require_once __DIR__ . '/Logger/FileTransport.php';

// Middleware
require_once __DIR__ . '/Middleware/MiddlewareInterface.php';
require_once __DIR__ . '/Middleware/MiddlewareManager.php';
require_once __DIR__ . '/Middleware/RateLimitMiddleware.php';
require_once __DIR__ . '/Middleware/AuthMiddleware.php';

// Validazione
require_once __DIR__ . '/Validation/SchemaValidator.php';

// Batch
require_once __DIR__ . '/Batch/BatchHandler.php';

// Esporta le classi principali
use RpcPhpToolkit\RpcEndpoint;
use RpcPhpToolkit\Logger\Logger;
use RpcPhpToolkit\Middleware\MiddlewareManager;
use RpcPhpToolkit\Validation\SchemaValidator;
use RpcPhpToolkit\Batch\BatchHandler;

// Middleware integrati
use RpcPhpToolkit\Middleware\RateLimitMiddleware;
use RpcPhpToolkit\Middleware\AuthMiddleware;

// Eccezioni
use RpcPhpToolkit\Exceptions\RpcException;
use RpcPhpToolkit\Exceptions\InvalidRequestException;
use RpcPhpToolkit\Exceptions\MethodNotFoundException;
use RpcPhpToolkit\Exceptions\InvalidParamsException;
use RpcPhpToolkit\Exceptions\InternalErrorException;
