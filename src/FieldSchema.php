<?php

namespace Lucian\FieldsOptions;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Attribute\Group;

/**
 * Reads DefaultField / Group attributes by walking nested DTO types.
 */
class FieldSchema
{
    /**
     * @var array<class-string, bool>
     */
    private static array $classAttributeCache = [];

    /**
     * @param class-string $rootClass
     */
    public function __construct(
        private readonly string $rootClass,
        private readonly NestedTypeResolver $resolver = new NestedTypeResolver(),
    ) {
    }

    public static function fromPrototype(object|string|array|null $prototype): ?self
    {
        if (is_object($prototype)) {
            return new self($prototype::class);
        }
        if (is_string($prototype) && class_exists($prototype)) {
            return new self($prototype);
        }

        return null;
    }

    /**
     * @return class-string|null
     */
    public function resolveClass(?string $fieldPath = null): ?string
    {
        return $this->resolver->resolveClass($this->rootClass, $fieldPath);
    }

    public function hasField(string $fieldPath): bool
    {
        $parent = $this->parentPath($fieldPath);
        $field = $this->lastSegment($fieldPath);
        $class = $this->resolver->resolveClass($this->rootClass, $parent);
        if ($class === null) {
            return false;
        }

        return $this->resolver->hasProperty($class, $field);
    }

    public function isDefaultField(string $fieldPath): bool
    {
        $property = $this->getReflectionProperty($fieldPath);

        return $property !== null && $property->getAttributes(DefaultField::class) !== [];
    }

    /**
     * @return list<string>
     */
    public function getGroups(string $fieldPath): array
    {
        $property = $this->getReflectionProperty($fieldPath);
        if ($property === null) {
            return [];
        }

        $groups = [];
        foreach ($property->getAttributes(Group::class) as $attribute) {
            $groups[] = $attribute->newInstance()->name;
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public function getFieldNames(?string $parentPath = null): array
    {
        $class = $this->resolver->resolveClass($this->rootClass, $parentPath);
        if ($class === null) {
            return [];
        }

        $names = [];
        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            if (str_starts_with($property->getName(), '__')) {
                continue;
            }
            $names[] = $property->getName();
        }

        return $names;
    }

    /**
     * Whether this path's declaring class could be resolved (needed for calculated inclusion).
     */
    public function canResolveDeclaringClass(string $fieldPath): bool
    {
        return $this->resolver->resolveClass($this->rootClass, $this->parentPath($fieldPath)) !== null;
    }

    /**
     * @param class-string|null $class
     */
    public static function classDeclaresInclusionAttributes(?string $class): bool
    {
        if ($class === null || !class_exists($class)) {
            return false;
        }
        if (array_key_exists($class, self::$classAttributeCache)) {
            return self::$classAttributeCache[$class];
        }

        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            if (
                $property->getAttributes(DefaultField::class) !== [] ||
                $property->getAttributes(Group::class) !== []
            ) {
                return self::$classAttributeCache[$class] = true;
            }
        }

        return self::$classAttributeCache[$class] = false;
    }

    private function getReflectionProperty(string $fieldPath): ?\ReflectionProperty
    {
        $parent = $this->parentPath($fieldPath);
        $field = $this->lastSegment($fieldPath);
        $class = $this->resolver->resolveClass($this->rootClass, $parent);
        if ($class === null || !$this->resolver->hasProperty($class, $field)) {
            return null;
        }

        return (new \ReflectionClass($class))->getProperty($field);
    }

    private function parentPath(string $fieldPath): ?string
    {
        $pos = strrpos($fieldPath, '.');

        return $pos === false ? null : substr($fieldPath, 0, $pos);
    }

    private function lastSegment(string $fieldPath): string
    {
        $pos = strrpos($fieldPath, '.');

        return $pos === false ? $fieldPath : substr($fieldPath, $pos + 1);
    }
}
