<?php

namespace Lucian\FieldsOptions;

use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

/**
 * Walks DTO property types (reflection + phpdoc) to resolve nested object classes.
 */
class NestedTypeResolver
{
    /**
     * @var array<string, \ReflectionClass>
     */
    private array $classCache = [];

    /**
     * @var array<string, string|null>
     */
    private array $propertyClassCache = [];

    /**
     * @var array<string, 'object'|'scalar'|'unresolved'>
     */
    private array $propertyKindCache = [];

    /**
     * @var array<string, bool>
     */
    private array $propertyExistsCache = [];

    private TypeResolver $typeResolver;
    private TypeContextFactory $typeContextFactory;

    public function __construct(?TypeResolver $typeResolver = null)
    {
        $this->typeResolver = $typeResolver ?? TypeResolver::create();
        $this->typeContextFactory = new TypeContextFactory();
    }

    /**
     * @param class-string $rootClass
     * @return class-string|null
     */
    public function resolveClass(string $rootClass, ?string $fieldPath = null): ?string
    {
        if ($fieldPath === null || $fieldPath === '') {
            return class_exists($rootClass) ? $rootClass : null;
        }

        $class = $rootClass;
        foreach (explode('.', $fieldPath) as $segment) {
            if (!$this->hasProperty($class, $segment)) {
                return null;
            }
            $child = $this->resolvePropertyClass($class, $segment);
            if ($child === null) {
                return null;
            }
            $class = $child;
        }

        return $class;
    }

    /**
     * True when every segment exists on a resolved class, or a segment is unresolved
     * and remaining path is accepted (legacy permissive fallback).
     *
     * @param class-string $rootClass
     */
    public function pathExists(string $rootClass, string $fieldPath): bool
    {
        $class = $rootClass;
        $segments = explode('.', $fieldPath);
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if (!$this->hasProperty($class, $segment)) {
                return false;
            }
            if ($index === $lastIndex) {
                return true;
            }
            $child = $this->resolvePropertyClass($class, $segment);
            if ($child !== null) {
                $class = $child;
                continue;
            }
            return $this->isUnresolvedProperty($class, $segment);
        }

        return true;
    }

    /**
     * @param class-string $class
     */
    public function hasProperty(string $class, string $property): bool
    {
        $key = $class . '::' . $property;
        if (array_key_exists($key, $this->propertyExistsCache)) {
            return $this->propertyExistsCache[$key];
        }

        if (!class_exists($class)) {
            return $this->propertyExistsCache[$key] = false;
        }

        return $this->propertyExistsCache[$key] = $this->getClass($class)->hasProperty($property);
    }

    /**
     * @param class-string $class
     * @return class-string|null
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress LessSpecificReturnStatement
     */
    public function resolvePropertyClass(string $class, string $property): ?string
    {
        $key = $class . '::' . $property;
        if (array_key_exists($key, $this->propertyClassCache)) {
            return $this->propertyClassCache[$key];
        }

        if (!$this->hasProperty($class, $property)) {
            return $this->propertyClassCache[$key] = null;
        }

        $reflectionProperty = $this->getClass($class)->getProperty($property);
        $resolved = $this->resolveFromProperty($reflectionProperty, $key);

        return $this->propertyClassCache[$key] = $resolved;
    }

    /**
     * Property exists but its nested type cannot be determined (mixed, multi-object union, …).
     *
     * @param class-string $class
     */
    public function isUnresolvedProperty(string $class, string $property): bool
    {
        $this->resolvePropertyClass($class, $property);

        return ($this->propertyKindCache[$class . '::' . $property] ?? 'unresolved') === 'unresolved';
    }

    /**
     * @return class-string|null
     */
    private function resolveFromProperty(\ReflectionProperty $property, string $cacheKey): ?string
    {
        try {
            $type = $this->typeResolver->resolve($property);
            $class = $this->extractObjectClass($type);
            if ($class !== null) {
                $this->propertyKindCache[$cacheKey] = 'object';
                return $class;
            }
            if ($this->isUnresolvedType($type)) {
                $this->propertyKindCache[$cacheKey] = 'unresolved';
                return null;
            }
            $this->propertyKindCache[$cacheKey] = 'scalar';
            return null;
        } catch (UnsupportedException) {
            // TypeInfo cannot parse some union phpdoc forms; try each part.
        }

        $class = $this->resolveFromPhpDocParts($property);
        $this->propertyKindCache[$cacheKey] = $class !== null ? 'object' : 'unresolved';

        return $class;
    }

    /**
     * @return class-string|null
     */
    private function resolveFromPhpDocParts(\ReflectionProperty $property): ?string
    {
        $docComment = $property->getDocComment();
        if (!is_string($docComment) || !preg_match('/@var\s+(\S+)/', $docComment, $matches)) {
            return null;
        }

        $context = $this->typeContextFactory->createFromReflection($property);
        $classes = [];
        foreach (explode('|', $matches[1]) as $part) {
            $part = trim($part, " \t()");
            if ($part === '') {
                continue;
            }
            try {
                $type = $this->typeResolver->resolve($part, $context);
            } catch (UnsupportedException) {
                continue;
            }
            $class = $this->extractObjectClass($type);
            if ($class !== null) {
                $classes[$class] = true;
            }
        }

        $classes = array_keys($classes);

        return count($classes) === 1 ? $classes[0] : null;
    }

    /**
     * @return class-string|null
     */
    private function extractObjectClass(Type $type): ?string
    {
        if ($type instanceof NullableType) {
            return $this->extractObjectClass($type->getWrappedType());
        }

        if ($type instanceof IntersectionType) {
            return null;
        }

        if ($type instanceof UnionType) {
            $classes = [];
            foreach ($type->getTypes() as $part) {
                if ($this->isIgnorableBuiltin($part)) {
                    continue;
                }
                $class = $this->extractObjectClass($part);
                if ($class !== null) {
                    $classes[$class] = true;
                }
            }
            $classes = array_keys($classes);

            return count($classes) === 1 ? $classes[0] : null;
        }

        if ($type instanceof CollectionType) {
            $valueClass = $this->extractObjectClass($type->getCollectionValueType());
            if ($valueClass !== null) {
                return $valueClass;
            }
            $wrapped = $type->getWrappedType();
            if ($wrapped instanceof ObjectType) {
                return $wrapped->getClassName();
            }
            if ($wrapped instanceof GenericType) {
                return $this->extractObjectClass($wrapped);
            }

            return null;
        }

        if ($type instanceof GenericType) {
            $variables = $type->getVariableTypes();
            if (count($variables) === 1) {
                return $this->extractObjectClass($variables[0]);
            }
            if (count($variables) === 2) {
                return $this->extractObjectClass($variables[1]);
            }

            return $this->extractObjectClass($type->getWrappedType());
        }

        if ($type instanceof ObjectType) {
            return $type->getClassName();
        }

        return null;
    }

    private function isUnresolvedType(Type $type): bool
    {
        if ($type instanceof NullableType) {
            return $this->isUnresolvedType($type->getWrappedType());
        }

        if ($type instanceof BuiltinType) {
            return in_array(
                $type->getTypeIdentifier(),
                [TypeIdentifier::MIXED, TypeIdentifier::ITERABLE, TypeIdentifier::ARRAY],
                true
            );
        }

        if ($type instanceof IntersectionType) {
            return true;
        }

        if ($type instanceof UnionType) {
            $objectParts = 0;
            foreach ($type->getTypes() as $part) {
                if ($this->isUnresolvedType($part) && !$this->isIgnorableBuiltin($part)) {
                    return true;
                }
                if ($part instanceof ObjectType || $part instanceof CollectionType) {
                    $objectParts++;
                }
            }

            return $objectParts > 1;
        }

        return false;
    }

    private function isIgnorableBuiltin(Type $type): bool
    {
        if (!$type instanceof BuiltinType) {
            return false;
        }

        return in_array(
            $type->getTypeIdentifier(),
            [
                TypeIdentifier::NULL,
                TypeIdentifier::ITERABLE,
                TypeIdentifier::ARRAY,
                TypeIdentifier::MIXED,
            ],
            true
        );
    }

    /**
     * @param class-string $class
     */
    private function getClass(string $class): \ReflectionClass
    {
        return $this->classCache[$class] ??= new \ReflectionClass($class);
    }
}
