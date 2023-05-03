<?php

namespace Lucian\FieldsOptions;

class FieldsOptionsObjectApplier
{
    protected const MAX_REFLECTION_CACHE_SIZE = 100; // reflection cache
    protected const MAX_APPLY_CACHE_SIZE = 1000;

    private ExportApplierInterface $applier;

    /**
     * Stores a map between fully qualified class name and the class reflection object
     * Limited by MAX_REFLECTION_CACHE_SIZE
     * @var array
     */
    private array $reflectionCache = [];

    /**
     * Stores a map between unique object hash and the last fields options unique hash
     * Limited by MAX_APPLY_CACHE_SIZE
     * @var array
     */
    private array $applyCache = [];

    public function __construct(ExportApplierInterface $applier)
    {
        $this->applier = $applier;
    }

    public function apply(object $object, FieldsOptions $fieldsOptions): void
    {
        // prevents applying more than once
        if ($this->cacheObject($object, $fieldsOptions)) {
            return;
        }

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
        $supportedClass = $this->applier->getSupportedClass();

        foreach ($reflection->getProperties() as $property) {
            $field = $property->getName();
            if ($fieldsOptions->isFieldIncluded($field)) {
                $propertyValue = $property->getValue($object);
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
     * Keeps track if we applied the specific options to this object or not
     *
     * @param object $object
     * @param FieldsOptions $fieldsOptions
     * @return bool true if we already applied to options, false otherwise
     */
    private function cacheObject(object $object, FieldsOptions $fieldsOptions): bool
    {
        $objectHash = spl_object_hash($object);
        if (isset($this->applyCache[$objectHash]) && $this->applyCache[$objectHash] == $fieldsOptions->getHash()) {
            return true;
        }

        // capped memory cache
        if (count($this->applyCache) > static::MAX_APPLY_CACHE_SIZE) {
            reset($this->applyCache);
            $key = key($this->applyCache);
            unset($this->applyCache[$key]);
        }

        $this->applyCache[$objectHash] = $fieldsOptions->getHash();

        return false;
    }

    private function getReflection(object $object)
    {
        $objectClass = get_class($object);

        if (isset($this->reflectionCache[$objectClass])) {
            return $this->reflectionCache[$objectClass];
        }

        // keep a low memory usage
        if (count($this->reflectionCache) > self::MAX_REFLECTION_CACHE_SIZE) {
            reset($this->reflectionCache);
            $key = key($this->reflectionCache);
            unset($this->reflectionCache[$key]);
        }

        $reflection = new \ReflectionClass($object);
        $this->reflectionCache[$objectClass] = $reflection;

        return $reflection;
    }
}
