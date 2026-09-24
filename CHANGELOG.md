# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.5] - 2026-09-24

### Added
- MySQL-backed, atomic rate-limit storage through `MySqlRateLimitStore`
- Executable per-method middleware with separate `before` and `after` phases

### Changed
- Middleware output now propagates parameters, handler context, authenticated users, and results
- Production defaults disable batch requests and introspection
- Wildcard CORS origins are rejected in production
- Authentication tokens are accepted only from the `Authorization: Bearer` header

### Fixed
- Method-level middleware registered by `addMethod()` is now executed
- Authenticated users are now available in the context received by RPC methods
- Introspection now reports the published package version

### Security
- Removed authentication token support from query strings
- Added persistent rate-limit counters for traditional PHP hosting

## [1.0.2] - 2026-06-11

### Added
- Official PHP client class in `src/Client/RpcClient.php` with full feature support
- CORS middleware (`CorsMiddleware`) with preflight OPTIONS handling
- Safe Mode serialization with type prefixes (S: for strings, D: for dates)
- Configuration options `safeEnabled` and `warnOnUnsafe` for type-safe serialization
- PHPUnit test suite for RpcEndpoint, Validation, and Middleware
- Client support for safe mode and SSL verification options
- CORS example server in `examples/cors-server.php`
- Safe mode demonstration in `examples/safe-mode-demo.php`

### Changed
- Simplified `examples/client.php` to use official RpcClient class
- Updated README with comprehensive client documentation
- Enhanced serialization to support safe mode with D: and S: prefixes
- Added `X-RPC-Safe-Enabled` header for client-server Safe Mode negotiation
- **Updated JavaScript clients to latest version from rpc-express-toolkit** (404 lines, full BigInt/Date support, safe mode)

### Fixed
- Logger method naming (warn → warning) for consistency
- JavaScript client now identical to Express version with all advanced features

## [1.0.1] - 2025-07-23

### Fixed
- Fixed duplicate sections in README.md
- Improved documentation structure and readability
- Corrected installation instructions

### Changed
- Updated composer.json with version field

## [1.0.0] - 2025-07-23

### Added
- Initial release of RPC PHP Toolkit
- Enterprise-ready JSON-RPC 2.0 library for PHP
- Simplified APIs with structured logging
- Middleware system with built-in rate limiting, CORS, auth
- Schema validation with JSON Schema support
- Batch processing with concurrent handling
- BigInt/Date serialization with timezone support
- JavaScript client library (browser and Node.js)
- Comprehensive examples and documentation

### Changed
- None

### Deprecated
- None

### Removed
- None

### Fixed
- Fixed deprecated nullable parameter declarations in PHP 8.0+

### Security
- None

## [1.0.0] - 2024-XX-XX

### Added
- Initial release
