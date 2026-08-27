<?php

namespace Lucian\FieldsOptions;

class Validator implements ValidatorInterface
{
    /**
     * @var array|object|string|null
     */
    private $prototype;

    private NestedTypeResolver $typeResolver;

    public function __construct(array|object|string|null $prototype = null)
    {
        $this->prototype = $prototype;
        $this->typeResolver = new NestedTypeResolver();
    }

    public function getPrototype(): array|object|string|null
    {
        return $this->prototype;
    }

    #[\Override]
    public function validateData(array $data, ?string $keyPath = null): void
    {
        foreach ($data as $key => $datum) {
            if ($key == FieldsOptions::OPTIONS_KEY) {
                continue;
            } elseif (strpos($key, '_') === 0) {
                if (!is_bool($datum)) {
                    throw new \RuntimeException($key . ' does not support options');
                }
                continue;
            }

            $fieldPath = $keyPath ? "$keyPath.$key" : $key;
            $this->validateField($fieldPath);

            if (is_array($datum)) {
                $this->validateData($datum, $fieldPath);
            } elseif (!is_bool($datum)) {
                $errorPath = $keyPath ?? '';
                throw new \RuntimeException('Invalid field options ' . $errorPath);
            }
        }
    }

    #[\Override]
    public function validateField(?string $fieldPath): void
    {
        if (!$this->prototype || !$fieldPath) {
            return;
        }

        if (is_array($this->prototype)) {
            if (ArrayHelper::getValue($this->prototype, $fieldPath, false) === false) {
                throw new \RuntimeException("Invalid field path '$fieldPath'");
            }
            return;
        }

        $rootClass = is_object($this->prototype) ? $this->prototype::class : $this->prototype;
        if (!is_string($rootClass) || !class_exists($rootClass)) {
            return;
        }

        if (!$this->typeResolver->pathExists($rootClass, $fieldPath)) {
            throw new \RuntimeException("Invalid field path '$fieldPath'");
        }
    }
}
