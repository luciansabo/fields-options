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
     * Process-wide set of classes whose whole reachable graph has well-formed Group names.
     * The only static state allowed here: attributes cannot change within a process and a class is
     * added only after its entire top-level walk succeeded, so invalid classes keep throwing on every bind.
     * Type-resolution caches must stay per instance (NestedTypeResolver / FieldSchemaCache).
     *
     * @var array<class-string, true>
     */
    private static array $validatedGroupClasses = [];

    /**
     * @param class-string $rootClass
     */
    public function __construct(
        private readonly string $rootClass,
        private readonly NestedTypeResolver $resolver = new NestedTypeResolver(),
        private readonly FieldSchemaCache $cache = new FieldSchemaCache(),
    ) {
        self::requireClass($rootClass);
        $this->assertValidGroups($rootClass);
    }

    /**
     * @param NestedTypeResolver|null $resolver Reuse an existing resolver so type caches survive
     *     forField() / withPrototype() copies. A new resolver is created only when omitted.
     * @param FieldSchemaCache|null $cache Reuse property group caches the same way.
     */
    public static function fromPrototype(
        object|string|array|null $prototype,
        ?NestedTypeResolver $resolver = null,
        ?FieldSchemaCache $cache = null,
    ): ?self {
        if (is_object($prototype)) {
            return new self(
                $prototype::class,
                $resolver ?? new NestedTypeResolver(),
                $cache ?? new FieldSchemaCache(),
            );
        }
        if (is_string($prototype)) {
            return new self(
                self::requireClass($prototype),
                $resolver ?? new NestedTypeResolver(),
                $cache ?? new FieldSchemaCache(),
            );
        }

        return null;
    }

    /**
     * @return class-string
     * @throws \InvalidArgumentException when $class is not autoloadable
     */
    public static function requireClass(string $class): string
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Unresolvable prototype class '$class'");
        }

        return $class;
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

        return $this->instantiateGroups($property);
    }

    /**
     * @return list<string>
     */
    public function getFieldNames(?string $parentPath = null): array
    {
        $class = $this->resolver->resolveClass($this->rootClass, $parentPath);

        return $class === null ? [] : $this->resolver->getFieldNames($class);
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

        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            if (!NestedTypeResolver::isFieldProperty($property)) {
                continue;
            }
            if (
                $property->getAttributes(DefaultField::class) !== [] ||
                $property->getAttributes(Group::class) !== []
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reject invalid Group names while walking reachable DTO types at bind time.
     *
     * @param class-string $rootClass
     */
    private function assertValidGroups(string $rootClass): void
    {
        if (isset(self::$validatedGroupClasses[$rootClass])) {
            return;
        }

        $walked = [];
        $this->walkGroups($rootClass, $walked);
        self::$validatedGroupClasses += $walked;
    }

    /**
     * @param class-string $class
     * @param array<class-string, true> $walked
     */
    private function walkGroups(string $class, array &$walked): void
    {
        if (isset(self::$validatedGroupClasses[$class]) || isset($walked[$class]) || !class_exists($class)) {
            return;
        }

        $walked[$class] = true;
        $reflection = new \ReflectionClass($class);
        if ($reflection->isInternal()) {
            return;
        }

        foreach ($reflection->getProperties() as $property) {
            if (!NestedTypeResolver::isFieldProperty($property)) {
                continue;
            }
            $this->instantiateGroups($property);
            $child = $this->resolver->resolvePropertyClass($class, $property->getName());
            if ($child !== null) {
                $this->walkGroups($child, $walked);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function instantiateGroups(\ReflectionProperty $property): array
    {
        $key = $property->class . '::' . $property->getName();
        $cached = $this->cache->getPropertyGroups($key);
        if ($cached !== null) {
            return $cached;
        }

        $groups = [];
        foreach ($property->getAttributes(Group::class) as $attribute) {
            $groups[] = $attribute->newInstance()->name;
        }

        return $this->cache->setPropertyGroups($key, $groups);
    }

    private function getReflectionProperty(string $fieldPath): ?\ReflectionProperty
    {
        $parent = $this->parentPath($fieldPath);
        $field = $this->lastSegment($fieldPath);
        $class = $this->resolver->resolveClass($this->rootClass, $parent);

        return $class === null ? null : $this->resolver->getFieldProperty($class, $field);
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
