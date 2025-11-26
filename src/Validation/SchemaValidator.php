<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Validation;

use RpcPhpToolkit\Exceptions\InvalidParamsException;

/**
 * Schema validator for RPC parameters
 */
class SchemaValidator
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'strict' => true,
            'allowAdditionalProperties' => false
        ], $options);
    }

    /**
     * Validates parameters against a schema
     */
    public function validateParams(array $params, array $schema): void
    {
        $errors = $this->validate($params, $schema);

        if (!empty($errors)) {
            throw new InvalidParamsException(
                'Parameter validation failed',
                ['validation_errors' => $errors]
            );
        }
    }

    /**
     * Validates a value against a schema
     */
    public function validate(mixed $value, array $schema): array
    {
        $errors = [];

        // Type validation
        if (isset($schema['type'])) {
            $typeErrors = $this->validateType($value, $schema['type']);
            $errors = array_merge($errors, $typeErrors);
        }

        // If basic type is not valid, don't continue
        if (!empty($errors)) {
            return $errors;
        }

        // Type-specific validations
        if (is_array($value)) {
            $errors = array_merge($errors, $this->validateArray($value, $schema));
        } elseif (is_string($value)) {
            $errors = array_merge($errors, $this->validateString($value, $schema));
        } elseif (is_numeric($value)) {
            $errors = array_merge($errors, $this->validateNumber($value, $schema));
        }

        // Enum validation
        if (isset($schema['enum'])) {
            $errors = array_merge($errors, $this->validateEnum($value, $schema['enum']));
        }

        return $errors;
    }

    private function validateType(mixed $value, string $expectedType): array
    {
        $actualType = $this->getValueType($value);

        // Allow 'integer' to match 'number' type
        if ($expectedType === 'number' && ($actualType === 'integer' || $actualType === 'number')) {
            return [];
        }

        if ($actualType !== $expectedType) {
            return ["Expected type '{$expectedType}', received '{$actualType}'"];
        }

        return [];
    }

    private function validateArray(array $value, array $schema): array
    {
        $errors = [];

        // Minimum length validation
        if (isset($schema['minItems']) && count($value) < $schema['minItems']) {
            $errors[] = "Array must have at least {$schema['minItems']} items";
        }

        // Maximum length validation
        if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
            $errors[] = "Array can have at most {$schema['maxItems']} items";
        }

        // Items validation
        if (isset($schema['items'])) {
            foreach ($value as $index => $item) {
                $itemErrors = $this->validate($item, $schema['items']);
                foreach ($itemErrors as $error) {
                    $errors[] = "Item [$index]: $error";
                }
            }
        }

        // Properties validation (for objects)
        if (isset($schema['properties'])) {
            $errors = array_merge($errors, $this->validateObject($value, $schema));
        }

        return $errors;
    }

    private function validateObject(array $value, array $schema): array
    {
        $errors = [];

        // Required properties validation
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $requiredProp) {
                if (!array_key_exists($requiredProp, $value)) {
                    $errors[] = "Required property '$requiredProp' is missing";
                }
            }
        }

        // Properties validation
        if (isset($schema['properties'])) {
            foreach ($value as $prop => $propValue) {
                if (isset($schema['properties'][$prop])) {
                    $propErrors = $this->validate($propValue, $schema['properties'][$prop]);
                    foreach ($propErrors as $error) {
                        $errors[] = "Property '$prop': $error";
                    }
                } elseif (!$this->options['allowAdditionalProperties']) {
                    $errors[] = "Property '$prop' is not allowed";
                }
            }
        }

        return $errors;
    }

    private function validateString(string $value, array $schema): array
    {
        $errors = [];

        // Minimum length validation
        if (isset($schema['minLength']) && strlen($value) < $schema['minLength']) {
            $errors[] = "String must be at least {$schema['minLength']} characters long";
        }

        // Maximum length validation
        if (isset($schema['maxLength']) && strlen($value) > $schema['maxLength']) {
            $errors[] = "String can be at most {$schema['maxLength']} characters long";
        }

        // Pattern validation
        if (isset($schema['pattern'])) {
            if (!preg_match($schema['pattern'], $value)) {
                $errors[] = "String does not match the required pattern";
            }
        }

        // Format validation
        if (isset($schema['format'])) {
            $errors = array_merge($errors, $this->validateFormat($value, $schema['format']));
        }

        return $errors;
    }

    private function validateNumber(int|float $value, array $schema): array
    {
        $errors = [];

        // Minimum validation
        if (isset($schema['minimum']) && $value < $schema['minimum']) {
            $errors[] = "Value must be at least {$schema['minimum']}";
        }

        // Maximum validation
        if (isset($schema['maximum']) && $value > $schema['maximum']) {
            $errors[] = "Value can be at most {$schema['maximum']}";
        }

        // Multiple validation
        if (isset($schema['multipleOf']) && fmod($value, $schema['multipleOf']) !== 0.0) {
            $errors[] = "Value must be a multiple of {$schema['multipleOf']}";
        }

        return $errors;
    }

    private function validateEnum(mixed $value, array $enum): array
    {
        if (!in_array($value, $enum, true)) {
            $enumStr = implode(', ', array_map('json_encode', $enum));
            return ["Value must be one of: $enumStr"];
        }

        return [];
    }

    private function validateFormat(string $value, string $format): array
    {
        return match ($format) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? [] : ['Invalid email'],
            'uri' => filter_var($value, FILTER_VALIDATE_URL) ? [] : ['Invalid URI'],
            'date' => $this->validateDate($value),
            'datetime' => $this->validateDateTime($value),
            'uuid' => $this->validateUuid($value),
            default => []
        };
    }

    private function validateDate(string $value): array
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? [] : ['Invalid date (format: Y-m-d)'];
    }

    private function validateDateTime(string $value): array
    {
        $date = \DateTime::createFromFormat(\DateTime::ISO8601, $value);
        return $date ? [] : ['Invalid DateTime (ISO8601 format)'];
    }

    private function validateUuid(string $value): array
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $value) ? [] : ['Invalid UUID'];
    }

    private function getValueType(mixed $value): string
    {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'array',
            is_array($value) => 'object',
            is_object($value) => 'object',
            default => 'unknown'
        };
    }
}
