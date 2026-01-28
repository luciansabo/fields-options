<?php

namespace Lucian\FieldsOptions;

interface ValidatorInterface
{
    /** @throws \RuntimeException */
    public function validateData(array $data, ?string $keyPath = null): void;
    /** @throws \RuntimeException */
    public function validateField(?string $fieldPath): void;
}
