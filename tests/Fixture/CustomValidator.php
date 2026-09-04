<?php

namespace Lucian\FieldsOptions\Test\Fixture;

use Lucian\FieldsOptions\ValidatorInterface;

/**
 * ValidatorInterface implementor that is not the library Validator.
 * Used to assert prototype extraction is not tied to the concrete class.
 */
class CustomValidator implements ValidatorInterface
{
    public function __construct(private readonly array|object|string|null $prototype = null)
    {
    }

    public function validateData(array $data, ?string $keyPath = null): void
    {
    }

    public function validateField(?string $fieldPath): void
    {
    }

    public function getPrototype(): array|object|string|null
    {
        return $this->prototype;
    }
}
