<?php

namespace Lucian\FieldsOptions;

class FieldsOptionsBuilder
{
    private array $data = [];
    /**
     * @var array|object|null
     */
    private $prototype = null;

    /**
     * @param object|array $prototype
     */
    public function __construct($prototype = null)
    {
        // we can remove this in 8.1 with uniion types
        if ($prototype && !is_array($prototype) && !is_object($prototype)) {
            throw new \RuntimeException('$prototype must be either an array or an object');
        }
        $this->prototype = $prototype;
    }

    /**
     * Include fields from given path
     *
     * @param string|null $fieldPath Base path
     * @param array $fields Optional list of included field (you can use relative paths in dot notation too)
     * @return $this
     * @psalm-suppress LessSpecificReturnStatement
     * @psalm-suppress MoreSpecificReturnType
     */

    public function setFieldIncluded(?string $fieldPath, array $fields = []): self
    {
        return $this->setFieldInclusion($fieldPath, $fields, true);
    }

    /**
     * Exclude fields from given path
     *
     * @param string|null $fieldPath Base path
     * @param array $fields Optional list of excluded fields
     * @return $this
     * @psalm-suppress LessSpecificReturnStatement
     * @psalm-suppress MoreSpecificReturnType
     */
    public function setFieldExcluded(?string $fieldPath, array $fields = []): self
    {
        return $this->setFieldInclusion($fieldPath, $fields, false);
    }

    private function validateField(?string $fieldPath)
    {
        if (!$this->prototype || !$fieldPath) {
            return;
        }

        if (is_array($this->prototype)) {
            if (ArrayHelper::getValue($this->prototype, $fieldPath, false) === false) {
                throw new \RuntimeException("Invalid field path '$fieldPath'");
            }
        } else {
            if (!$this->objectPathExists($this->prototype, $fieldPath)) {
                throw new \RuntimeException("Invalid field path '$fieldPath'");
            }
        }
    }

    private function setFieldInclusion(
        ?string $fieldPath,
        array $fields,
        bool $isIncluded,
        bool $validateField = true
    ): self {
        if (empty($fieldPath) && empty($fields)) {
            throw new \LogicException('No fields provided');
        }

        if (empty($fields)) {
            if ($validateField) {
                $this->validateField($fieldPath);
            }

            ArrayHelper::setValue($this->data, $fieldPath, $isIncluded);
        } else {
            $basePath = $fieldPath ? "$fieldPath." : $fieldPath;

            foreach ($fields as $field) {
                if ($validateField) {
                    $this->validateField($basePath . $field);
                }
                ArrayHelper::setValue($this->data, $basePath . $field, $isIncluded);
            }
        }

        return $this;
    }

    public function setFieldOption(string $fieldPath, string $option, /*mixed*/ $value): self
    {
        $this->validateField($fieldPath);
        ArrayHelper::setValue($this->data, $fieldPath . '.' . FieldsOptions::OPTIONS_KEY . '.' . $option, $value);

        return $this;
    }

    public function setFieldOptions(string $fieldPath, array $options): self
    {
        $this->validateField($fieldPath);
        ArrayHelper::setValue(
            $this->data,
            $fieldPath,
            [FieldsOptions::OPTIONS_KEY => $options]
        );

        return $this;
    }

    public function setGroupFieldIncluded(string $groupField, ?string $fieldPath = null): self
    {
        $this->validateField($fieldPath);
        $this->setFieldInclusion($fieldPath, [$groupField], true, false);

        return $this;
    }

    public function setGroupFieldExcluded(string $groupField, ?string $fieldPath = null): self
    {
        $this->validateField($fieldPath);
        $this->setFieldInclusion($fieldPath, [$groupField], false, false);

        return $this;
    }

    public function setDefaultFieldsIncluded(?string $fieldPath = null): self
    {
        $this->setGroupFieldIncluded(FieldsOptions::FIELD_DEFAULTS, $fieldPath);

        return $this;
    }

    public function setAllFieldsIncluded(?string $fieldPath = null): self
    {
        $this->setGroupFieldIncluded(FieldsOptions::FIELD_ALL, $fieldPath);

        return $this;
    }

    public function build(): FieldsOptions
    {
        return new FieldsOptions($this->data);
    }

    /**
     * Rudimentary property exist check by dot notation
     * Works with nested object not with iterables of objects
     *
     * @param object $object
     * @param string $path
     * @return bool
     * @throws \ReflectionException
     */
    private function objectPathExists(object $object, string $path)
    {
        $pathComponents = explode('.', $path);
        $objectReflection = new \ReflectionClass($object);

        foreach ($pathComponents as $property) {
            if (!$objectReflection->hasProperty($property)) {
                return false;
            }

            $reflectionProperty = $objectReflection->getProperty($property);
            // needed for php 7.4, not needed for 8.1
            if (!$reflectionProperty->isPublic()) {
                $reflectionProperty->setAccessible(true);
            }
            $type = $reflectionProperty->getType();

            if ($type && $type->isBuiltin()) {
                return true;
            }

            if ($type && $type instanceof \ReflectionNamedType && class_exists($type->getName())) {
                $objectReflection = new \ReflectionClass($type->getName());
            } elseif ($reflectionProperty->isInitialized($object)) {
                // try to use value
                $value = $reflectionProperty->getValue($object);
                if (is_object($value)) {
                    $objectReflection = new \ReflectionClass($value);
                } elseif (is_array($value)) {
                    $firstValue = reset($value);
                    if (is_object($firstValue)) {
                        $objectReflection = new \ReflectionClass($firstValue);
                    }
                }
            } else {
                // we cannot reliably determine types for uninitialized iterables
                return true;
            }
        }

        return true;
    }
}
