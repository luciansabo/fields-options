<?php

namespace Lucian\FieldsOptions;

/**
 * @psalm-suppress UnusedClass
 */
class FieldsOptionsObjectApplier
{
    protected const MAX_REFLECTION_CACHE_SIZE = 100; // reflection cache

    private ExportApplierInterface $applier;

    /**
     * Stores a map between fully qualified class name and the class reflection object
     * Limited by MAX_REFLECTION_CACHE_SIZE
     * @var array
     */
    private array $reflectionCache = [];

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

        if (FieldSchema::classDeclaresInclusionAttributes($object::class)) {
            $this->applyWithAttributes($object, $fieldsOptions->withPrototype($object));
            return;
        }

        $this->applyLegacy($object, $fieldsOptions);
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

        $reflection = $this->getReflection($object);
        $includedProperties = [];
        $supportedClass = $this->resolveSupportedClass();

        foreach ($reflection->getProperties() as $reflectionProperty) {
            $field = $reflectionProperty->getName();
            if (str_starts_with($field, '__')) {
                continue;
            }
            if (!$fieldsOptions->isFieldIncluded($field)) {
                continue;
            }

            $includedProperties[] = $field;
            $this->applyNestedValue(
                $reflectionProperty->getValue($object),
                $fieldsOptions->forField($field),
                $supportedClass
            );
        }

        $this->applier->setExportedFields($object, $includedProperties);
    }

    /**
     * Pre-2.0 path for classes without DefaultField / Group attributes.
     */
    private function applyLegacy(object $object, FieldsOptions $fieldsOptions): void
    {
        if ($fieldsOptions->getIncludedFields() == [FieldsOptions::FIELD_ALL => true]) {
            // only all fields requested
            $this->applier->setExportedFields($object, null);
        } elseif (
            (empty($fieldsOptions->getIncludedFields()) &&
                !$fieldsOptions->isFieldSpecified(FieldsOptions::FIELD_DEFAULTS)) ||
            $fieldsOptions->getIncludedFields() == [FieldsOptions::FIELD_DEFAULTS => true]
        ) {
            // only defaults requested
            return;
        } elseif (
            $fieldsOptions->getIncludedFields() == [FieldsOptions::FIELD_DEFAULTS => false]
        ) {
            // nothing requested
            $this->applier->setExportedFields($object, []);
            return;
        }

        $reflection = $this->getReflection($object);

        // keep this out of the loop
        $hasDefaultFields = $fieldsOptions->hasDefaultFields();
        $hasAllFields = $fieldsOptions->hasAllFields();
        $defaults = $hasDefaultFields ? $this->applier->getExportedFields($object) : [];

        $includedProperties = [];
        $supportedClass = $this->resolveSupportedClass();

        foreach ($reflection->getProperties() as $reflectionProperty) {
            $field = $reflectionProperty->getName();
            if ($fieldsOptions->isFieldIncluded($field)) {
                $propertyValue = $reflectionProperty->getValue($object);
                if (
                    is_object($propertyValue) &&
                    (!is_iterable($propertyValue) ||
                        ($supportedClass && is_subclass_of($propertyValue, $supportedClass)))
                ) {
                    $this->apply(
                        $propertyValue,
                        new FieldsOptions($fieldsOptions->toArray($field))
                    );
                } else {
                    // skip non rewindable iterators
                    if (is_iterable($propertyValue) && !$propertyValue instanceof \Generator) {
                        foreach ($propertyValue as $item) {
                            if (is_object($item)) {
                                $this->apply(
                                    $item,
                                    new FieldsOptions($fieldsOptions->toArray($field))
                                );
                            }
                        }
                    }
                }
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

            if ($fieldsOptions->isFieldIncluded($field)) {
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
     * @param class-string|null $supportedClass
     */
    private function applyNestedValue(mixed $propertyValue, FieldsOptions $childOptions, ?string $supportedClass): void
    {
        if (
            is_object($propertyValue) &&
            (!is_iterable($propertyValue) ||
                ($supportedClass && is_subclass_of($propertyValue, $supportedClass)))
        ) {
            $this->apply($propertyValue, $childOptions);
            return;
        }

        if (is_iterable($propertyValue) && !$propertyValue instanceof \Generator) {
            foreach ($propertyValue as $item) {
                if (is_object($item)) {
                    $this->apply($item, $childOptions);
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
     * Keeps track if we applied the specific options to this object or not
     * Uses WeakMap for proper object identity tracking without hash collision issues
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

    private function getReflection(object $object): \ReflectionClass
    {
        $objectClass = get_class($object);

        if (isset($this->reflectionCache[$objectClass])) {
            return $this->reflectionCache[$objectClass];
        }

        // keep a low memory usage
        if (count($this->reflectionCache) > self::MAX_REFLECTION_CACHE_SIZE) {
            reset($this->reflectionCache);
            $key = key($this->reflectionCache);
            if ($key !== null) {
                unset($this->reflectionCache[$key]);
            }
        }

        $reflection = new \ReflectionClass($object);
        $this->reflectionCache[$objectClass] = $reflection;

        return $reflection;
    }
}
