<?php

namespace Lucian\FieldsOptions;

/**
 * @psalm-suppress UnusedClass
 */
class FieldsOptionsObjectApplier
{
    protected const MAX_REFLECTION_CACHE_SIZE = 100;

    private ExportApplierInterface $applier;

    /**
     * Stores a map between fully qualified class name and its field properties
     * (see NestedTypeResolver::isFieldProperty())
     * Limited by MAX_REFLECTION_CACHE_SIZE
     * @var array<class-string, list<\ReflectionProperty>>
     */
    private array $fieldPropertiesCache = [];

    /**
     * Whether a class declares #[DefaultField] / #[Group] (attributed vs legacy apply).
     * Limited by MAX_REFLECTION_CACHE_SIZE
     * @var array<class-string, bool>
     */
    private array $classAttributeCache = [];

    /**
     * Stores a map between object instances and the last fields options unique hash
     * Uses WeakMap to ensure proper object identity tracking and automatic cleanup
     * when objects are garbage collected
     * @var \WeakMap
     */
    private \WeakMap $applyCache;

    public function __construct(ExportApplierInterface $applier)
    {
        $this->applier = $applier;
        $this->applyCache = new \WeakMap();
    }

    public function apply(object $object, FieldsOptions $fieldsOptions): void
    {
        // prevents applying more than once
        if ($this->cacheObject($object, $fieldsOptions)) {
            return;
        }

        try {
            if ($this->classDeclaresInclusionAttributes($object)) {
                $this->applyWithAttributes($object, $fieldsOptions->withPrototype($object));
            } else {
                $this->applyLegacy($object, $fieldsOptions);
            }
        } catch (\Throwable $e) {
            // part of the subtree may already carry the failed options, so no earlier hash describes it
            unset($this->applyCache[$object]);
            throw $e;
        }
    }

    /**
     * Attribute-aware path: inclusion comes from isFieldIncluded / DefaultField / Group.
     * $fieldsOptions is expected to already have a prototype (see apply() + withPrototype()).
     */
    private function applyWithAttributes(object $object, FieldsOptions $fieldsOptions): void
    {
        if ($this->isAllFieldsOnly($fieldsOptions)) {
            $this->applier->setExportedFields($object, null);
            return;
        }

        $includedProperties = [];
        $supportedClass = $this->resolveSupportedClass();

        foreach ($this->getFieldProperties($object) as $reflectionProperty) {
            $field = $reflectionProperty->getName();
            if (!$fieldsOptions->isFieldIncluded($field)) {
                continue;
            }

            $includedProperties[] = $field;
            if (!$reflectionProperty->isInitialized($object)) {
                continue;
            }

            $this->applyNestedValue(
                $reflectionProperty->getValue($object),
                fn(): FieldsOptions => $fieldsOptions->forField($field),
                $supportedClass
            );
        }

        $this->applier->setExportedFields($object, $includedProperties);
    }

    /**
     * Pre-2.0 path for classes without DefaultField / Group attributes.
     * Inclusion follows the request tree (isFieldExplicitlyIncluded), not calculated isFieldIncluded.
     */
    private function applyLegacy(object $object, FieldsOptions $fieldsOptions): void
    {
        if ($this->isAllFieldsOnly($fieldsOptions)) {
            // only all fields requested
            $this->applier->setExportedFields($object, null);
            return;
        }

        if (
            !$this->hasExplicitlyIncludedKey($fieldsOptions) &&
            !$fieldsOptions->isFieldSpecified(FieldsOptions::FIELD_DEFAULTS)
        ) {
            // nothing requested at this level, keep the object's own default export
            return;
        }

        // keep this out of the loop
        $hasDefaultFields = $fieldsOptions->hasDefaultFields();
        $hasAllFields = $fieldsOptions->hasAllFields();
        $defaults = $hasDefaultFields ? $this->applier->getExportedFields($object) : [];

        $includedProperties = [];
        $supportedClass = $this->resolveSupportedClass();

        foreach ($this->getFieldProperties($object) as $reflectionProperty) {
            $field = $reflectionProperty->getName();
            $isExplicitlyIncluded = $fieldsOptions->isFieldExplicitlyIncluded($field);
            if ($isExplicitlyIncluded && $reflectionProperty->isInitialized($object)) {
                $this->applyNestedValue(
                    $reflectionProperty->getValue($object),
                    fn(): FieldsOptions => $fieldsOptions->sliceWithoutSchema($field),
                    $supportedClass
                );
            }

            if ($hasAllFields) {
                if (!$fieldsOptions->isFieldSpecified($field)) {
                    $includedProperties[] = $field;
                }
            } elseif ($hasDefaultFields) {
                if (!$defaults && !$fieldsOptions->isFieldSpecified($field)) {
                    $includedProperties[] = $field;
                } elseif ($defaults && in_array($field, $defaults) && !$fieldsOptions->isFieldSpecified($field)) {
                    $includedProperties[] = $field;
                }
            }

            if ($isExplicitlyIncluded) {
                $includedProperties[] = $field;
            }
        }

        $this->applier->setExportedFields($object, $includedProperties);
    }

    /**
     * @return class-string|null
     */
    private function resolveSupportedClass(): ?string
    {
        $supportedClass = $this->applier->getSupportedClass();
        if (!class_exists($supportedClass)) {
            return null;
        }

        return $supportedClass;
    }

    /**
     * @param \Closure(): FieldsOptions $childOptions called once, right before the first nested apply():
     *     1.x re-validated legacy slices only when it recursed, so scalars and empty collections must not build one
     * @param class-string|null $supportedClass
     */
    private function applyNestedValue(mixed $propertyValue, \Closure $childOptions, ?string $supportedClass): void
    {
        if (
            is_object($propertyValue) &&
            (!is_iterable($propertyValue) ||
                ($supportedClass && is_subclass_of($propertyValue, $supportedClass)))
        ) {
            $this->apply($propertyValue, $childOptions());
            return;
        }

        if (is_iterable($propertyValue) && !$propertyValue instanceof \Generator) {
            $options = null;
            foreach ($propertyValue as $item) {
                if (is_object($item)) {
                    $this->apply($item, $options ??= $childOptions());
                }
            }
        }
    }

    private function isAllFieldsOnly(FieldsOptions $fieldsOptions): bool
    {
        if (!$fieldsOptions->hasAllFields()) {
            return false;
        }

        foreach (array_keys($fieldsOptions->toArray()) as $key) {
            if ($key === FieldsOptions::FIELD_ALL || $key === FieldsOptions::OPTIONS_KEY) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Whether any key at this level, meta keys like `_defaults` or custom groups included, is not `false`.
     * Data-only on purpose: getIncludedFields() is schema-calculated once a prototype is bound.
     */
    private function hasExplicitlyIncludedKey(FieldsOptions $fieldsOptions): bool
    {
        foreach (array_keys($fieldsOptions->toArray()) as $key) {
            if ($fieldsOptions->isFieldExplicitlyIncluded((string) $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keeps track if we applied the specific options to this object or not
     * Uses WeakMap for proper object identity tracking without hash collision issues
     *
     * The key is the request data hash only, which is correct as long as the output of apply()
     * is a function of (object, request data) alone: the legacy path must only read request data
     * and the attributed path must rebind its schema from the object (withPrototype()).
     * Also terminates recursion on cyclic object graphs.
     *
     * @param object $object
     * @param FieldsOptions $fieldsOptions
     * @return bool true if we already applied to options, false otherwise
     */
    private function cacheObject(object $object, FieldsOptions $fieldsOptions): bool
    {
        if (isset($this->applyCache[$object]) && $this->applyCache[$object] == $fieldsOptions->getHash()) {
            return true;
        }

        $this->applyCache[$object] = $fieldsOptions->getHash();

        return false;
    }

    private function classDeclaresInclusionAttributes(object $object): bool
    {
        $class = $object::class;
        if (array_key_exists($class, $this->classAttributeCache)) {
            return $this->classAttributeCache[$class];
        }

        return $this->rememberCapped(
            $this->classAttributeCache,
            $class,
            FieldSchema::classDeclaresInclusionAttributes($class)
        );
    }

    /**
     * @return list<\ReflectionProperty>
     */
    private function getFieldProperties(object $object): array
    {
        $objectClass = $object::class;

        if (isset($this->fieldPropertiesCache[$objectClass])) {
            return $this->fieldPropertiesCache[$objectClass];
        }

        $properties = array_values(array_filter(
            (new \ReflectionClass($object))->getProperties(),
            [NestedTypeResolver::class, 'isFieldProperty']
        ));

        return $this->rememberCapped($this->fieldPropertiesCache, $objectClass, $properties);
    }

    /**
     * @template T
     * @param array<class-string, T> $cache
     * @param class-string $key
     * @param T $value
     * @return T
     */
    private function rememberCapped(array &$cache, string $key, mixed $value): mixed
    {
        if (!array_key_exists($key, $cache) && count($cache) >= self::MAX_REFLECTION_CACHE_SIZE) {
            reset($cache);
            $oldest = key($cache);
            if (is_string($oldest)) {
                unset($cache[$oldest]);
            }
        }
        $cache[$key] = $value;

        return $value;
    }
}
