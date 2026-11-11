<?php

namespace Lucian\FieldsOptions;

/** @psalm-api */
interface ValidatorInterface
{
    /** @throws \RuntimeException */
    public function validateData(array $data, ?string $keyPath = null): void;
    /** @throws \RuntimeException */
    public function validateField(?string $fieldPath): void;

    /**
     * Prototype used for field-path validation and calculated inclusion.
     * An object or class-string enables schema; an array or null does not.
     */
    public function getPrototype(): array|object|string|null;
}
