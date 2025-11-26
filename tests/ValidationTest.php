<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Tests;

use PHPUnit\Framework\TestCase;
use RpcPhpToolkit\Validation\SchemaValidator;
use RpcPhpToolkit\Exceptions\InvalidParamsException;

class ValidationTest extends TestCase
{
    private SchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SchemaValidator();
    }

    public function testValidateString(): void
    {
        $schema = [
            'type' => 'string',
            'minLength' => 2,
            'maxLength' => 10
        ];

        $result = $this->validator->validate('hello', $schema);
        $this->assertEmpty($result);

        $result = $this->validator->validate('a', $schema);
        $this->assertNotEmpty($result);

        $result = $this->validator->validate('verylongstring', $schema);
        $this->assertNotEmpty($result);
    }

    public function testValidateNumber(): void
    {
        $schema = [
            'type' => 'number',
            'minimum' => 0,
            'maximum' => 100
        ];

        $result = $this->validator->validate(50, $schema);
        $this->assertEmpty($result);

        $result = $this->validator->validate(-1, $schema);
        $this->assertNotEmpty($result);

        $result = $this->validator->validate(101, $schema);
        $this->assertNotEmpty($result);
    }

    public function testValidateObject(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer']
            ],
            'required' => ['name']
        ];

        $result = $this->validator->validate(['name' => 'John', 'age' => 30], $schema);
        $this->assertEmpty($result);

        $result = $this->validator->validate(['age' => 30], $schema);
        $this->assertNotEmpty($result);
    }

    public function testValidateArray(): void
    {
        $schema = [
            'type' => 'array',
            'items' => ['type' => 'integer'],
            'minItems' => 1,
            'maxItems' => 5
        ];

        $result = $this->validator->validate([1, 2, 3], $schema);
        $this->assertEmpty($result);

        $result = $this->validator->validate([], $schema);
        $this->assertNotEmpty($result);

        $result = $this->validator->validate([1, 2, 3, 4, 5, 6], $schema);
        $this->assertNotEmpty($result);
    }

    public function testValidateEnum(): void
    {
        $schema = [
            'type' => 'string',
            'enum' => ['red', 'green', 'blue']
        ];

        $result = $this->validator->validate('red', $schema);
        $this->assertEmpty($result);

        $result = $this->validator->validate('yellow', $schema);
        $this->assertNotEmpty($result);
    }

    public function testValidateEmailFormat(): void
    {
        $schema = [
            'type' => 'string',
            'format' => 'email'
        ];

        $result = $this->validator->validate('test@example.com', $schema);
        $this->assertEmpty($result);

        $result = $this->validator->validate('invalid-email', $schema);
        $this->assertNotEmpty($result);
    }

    public function testValidateParamsWithSchema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'username' => ['type' => 'string', 'minLength' => 3],
                'email' => ['type' => 'string', 'format' => 'email']
            ],
            'required' => ['username', 'email']
        ];

        // Valid params
        $this->validator->validateParams([
            'username' => 'john',
            'email' => 'john@example.com'
        ], $schema);

        $this->assertTrue(true); // If no exception, validation passed

        // Invalid params should throw
        $this->expectException(InvalidParamsException::class);
        $this->validator->validateParams([
            'username' => 'jo'
        ], $schema);
    }
}
