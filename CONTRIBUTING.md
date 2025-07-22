# Contributing to RPC PHP Toolkit

We welcome contributions to the RPC PHP Toolkit! This document provides guidelines for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Making Changes](#making-changes)
- [Testing](#testing)
- [Code Style](#code-style)
- [Submitting Changes](#submitting-changes)

## Code of Conduct

By participating in this project, you agree to abide by our Code of Conduct. Please be respectful and professional in all interactions.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork locally
3. Set up the development environment
4. Create a feature branch for your changes

## Development Setup

### Prerequisites

- PHP 8.0 or higher
- Composer
- Git

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/rpc-php-toolkit.git
cd rpc-php-toolkit

# Install dependencies
composer install

# Install development dependencies
composer install --dev
```

## Making Changes

1. Create a new branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Make your changes following the coding standards
3. Add tests for any new functionality
4. Ensure all tests pass
5. Update documentation if needed

## Testing

Run the test suite:

```bash
# Run PHPUnit tests
composer test

# Run tests with coverage
composer test-coverage

# Run static analysis
composer phpstan

# Check code style
composer phpcs
```

## Code Style

This project follows PSR-12 coding standards. Please ensure your code adheres to these standards:

```bash
# Check code style
composer phpcs

# Auto-fix code style issues (if you have phpcbf installed)
./vendor/bin/phpcbf src/ --standard=PSR12
```

### Key Guidelines

- Use strict types: `declare(strict_types=1);`
- Follow PSR-4 autoloading standards
- Use meaningful variable and method names
- Add PHPDoc comments for all public methods
- Keep methods focused and single-purpose
- Use type hints for all parameters and return values

## Submitting Changes

1. Ensure all tests pass
2. Update CHANGELOG.md with your changes
3. Commit your changes with clear, descriptive messages
4. Push to your fork
5. Create a Pull Request

### Pull Request Guidelines

- Provide a clear description of the changes
- Reference any related issues
- Include tests for new functionality
- Update documentation as needed
- Ensure CI checks pass

### Commit Message Format

Use clear, descriptive commit messages:

```
type: brief description

Longer description if needed

- Specific change 1
- Specific change 2

Fixes #issue-number
```

Types:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

## Questions?

If you have questions about contributing, please:

1. Check existing issues and documentation
2. Open a new issue with the "question" label
3. Contact the maintainers

Thank you for contributing to RPC PHP Toolkit!
