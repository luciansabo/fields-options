<?php

namespace Lucian\FieldsOptions;

class Validator implements ValidatorInterface
{
    /**
     * @var array|object|null
     */
    private $prototype;

    public function __construct($prototype = null)
    {
        // we can remove this in 8.1 with union types
        if ($prototype && !is_array($prototype) && !is_object($prototype)) {
            throw new \RuntimeException('$prototype must be either an array or an object');
        }

        $this->prototype = $prototype;
    }

    public function validateData(array $data, string $keyPath = null): void
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
                throw new \RuntimeException('Invalid field options ' . $keyPath);
            }
        }
    }

    public function validateField(?string $fieldPath): void
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

    /**
     * Rudimentary property exist check by dot notation
     * Works with nested object not with iterables of objects
     *
     * @param object $object
     * @param string $path
     * @return bool
     * @throws \ReflectionException
     */
    private function objectPathExists(object $object, string $path): bool
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

            if ($type instanceof \ReflectionNamedType && class_exists($type->getName())) {
                $objectReflection = new \ReflectionClass($type->getName());
                $object = $objectReflection->newInstanceWithoutConstructor();
            } elseif ($reflectionProperty->isInitialized($object)) {
                // try to use value
                $value = $reflectionProperty->getValue($object);
                if (is_object($value)) {
                    $objectReflection = new \ReflectionClass($value);
                    $object = $value;
                } elseif (is_array($value)) {
                    $firstValue = reset($value);
                    if (is_object($firstValue)) {
                        $objectReflection = new \ReflectionClass($firstValue);
                        $object = $firstValue;
                    } else {
                        // we cannot reliably determine types for uninitialized iterables
                        return true;
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
