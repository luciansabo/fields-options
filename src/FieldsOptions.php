<?php

namespace Lucian\FieldsOptions;

/**
 * Holds a structure of wanted fields and their options
 */
class FieldsOptions
{
    public const OPTIONS_KEY = '_opt';
    public const FIELD_DEFAULTS = '_defaults';
    public const FIELD_ALL = '_all';
    private array $data;
    private ?FieldSchema $schema;

    public const DEFAULT_GROUPS = [
        self::FIELD_DEFAULTS,
        self::FIELD_ALL
    ];

    public function __construct(
        array $data = [],
        ?ValidatorInterface $validator = null,
        object|string|null $prototype = null,
    ) {
        if ($prototype === null && $validator instanceof Validator) {
            $extracted = $validator->getPrototype();
            if (is_object($extracted) || is_string($extracted)) {
                $prototype = $extracted;
            }
        }

        if ($data) {
            $validator ??= new Validator($prototype);
            $validator->validateData($data);
        }
        $this->data = $data;
        $this->schema = FieldSchema::fromPrototype($prototype);
    }

    /**
     * Export an array structure for a given path
     *
     * @param string|null $fieldPath
     * @return array
     */
    public function toArray(?string $fieldPath = null): array
    {
        $value = ArrayHelper::getValue($this->data, $fieldPath);

        return is_array($value) ? $value : [];
    }

    /**
     * Slice options for a nested field, carrying the resolved child prototype.
     */
    public function forField(string $fieldPath): self
    {
        $nestedPrototype = $this->schema?->resolveClass($fieldPath);

        return new self($this->toArray($fieldPath), null, $nestedPrototype);
    }

    /**
     * Copy with $prototype bound so isFieldIncluded() can resolve DefaultField / Group.
     * Request data is unchanged and not re-validated.
     */
    public function withPrototype(object|string $prototype): self
    {
        $copy = clone $this;
        $copy->schema = FieldSchema::fromPrototype($prototype);

        return $copy;
    }

    /**
     * Effective inclusion: explicit request, _all, groups, and DefaultField at this scope.
     */
    public function isFieldIncluded(string $fieldPath): bool
    {
        if ($this->isMetaKey($fieldPath)) {
            return $this->isFieldExplicitlyIncluded($fieldPath);
        }

        if ($this->schema === null) {
            if ($this->isFieldSpecified($fieldPath)) {
                return $this->isFieldExplicitlyIncluded($fieldPath);
            }

            return $this->scopeHasAllFields($this->parentPath($fieldPath));
        }

        $parent = $this->parentPath($fieldPath);
        if ($parent !== null && !$this->isFieldIncluded($parent)) {
            return false;
        }

        if ($this->isFieldSpecified($fieldPath)) {
            return $this->isFieldExplicitlyIncluded($fieldPath);
        }

        if (!$this->schema->hasField($fieldPath)) {
            return false;
        }

        if (!$this->schema->canResolveDeclaringClass($fieldPath)) {
            return $this->isFieldExplicitlyIncluded($fieldPath);
        }

        if ($this->scopeHasAllFields($parent)) {
            return true;
        }

        foreach ($this->schema->getGroups($fieldPath) as $group) {
            if ($this->isFieldExplicitlyIncluded($this->groupPath($group, $parent))) {
                return true;
            }
        }

        return $this->scopeHasDefaultFields($parent) && $this->schema->isDefaultField($fieldPath);
    }

    /**
     * Previous isFieldIncluded(): the path is present and not `false`.
     */
    public function isFieldExplicitlyIncluded(string $fieldPath): bool
    {
        return ArrayHelper::getValue($this->data, $fieldPath, false) !== false;
    }

    /**
     * This is simply a way to determine if the field was specified or not on the options,
     * either with `true` or `false`.
     *
     * @param string $fieldPath
     * @return bool
     */
    public function isFieldSpecified(string $fieldPath): bool
    {
        return ArrayHelper::getValue($this->data, $fieldPath) !== null;
    }

    /**
     * Return an array of options specified on the field path indexed by option name
     *
     * @param string $fieldPath
     * @return array
     */
    public function getFieldOptions(?string $fieldPath): array
    {
        if ($fieldPath) {
            $this->assertFieldExists($fieldPath);
        }
        $finalPath = $fieldPath ? ($fieldPath . '.' . FieldsOptions::OPTIONS_KEY) : FieldsOptions::OPTIONS_KEY;

        return ArrayHelper::getValue($this->data, $finalPath, []);
    }

    /**
     * Returns the option value for a field, a default value or null if the option was not provided
     * If $default is provided and the option does not have a value, it will return the value in $default
     *
     * @param string $fieldPath
     * @param string $option
     * @param $default
     * @return mixed|null
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getFieldOption(?string $fieldPath, string $option, mixed $default = null): mixed
    {
        $options = $this->getFieldOptions($fieldPath);
        return $options[$option] ?? $default;
    }

    /**
     * WIll check if the options contains the group by explicit inclusion
     * Useful for the custom groups
     *
     * @param string $group
     * @param string|null $fieldPath
     * @return bool true if _defaults is not specified or specified and is not false, false otherwise
     * @see hasAllFields()
     *
     * @see hasDefaultFields()
     */
    public function hasGroupField(string $group, ?string $fieldPath = null): bool
    {
        $path = $fieldPath ? ($fieldPath . '.' . $group) : $group;

        if ($fieldPath && !$this->fieldExists($fieldPath)) {
            throw new \InvalidArgumentException(sprintf('Field path "%s" is not available', $fieldPath));
        } elseif (!in_array($group, self::DEFAULT_GROUPS) && !$this->fieldExists($path)) {
            throw new \InvalidArgumentException(sprintf('Field "%s" is not available', $path));
        }

        return $this->isFieldExplicitlyIncluded($path);
    }

    /**
     * WIll check if the options contain the default fields either by implicit or explicit inclusion
     *
     * @param string|null $fieldPath
     * @return bool true if _defaults is not specified or specified and is not false, false otherwise
     */
    public function hasDefaultFields(?string $fieldPath = null): bool
    {
        // the special _opt key or the _all field need to be removed to detect if we have fields or not
        $fieldsArray = $this->toArray($fieldPath);
        if (isset($fieldsArray[static::OPTIONS_KEY])) {
            unset($fieldsArray[static::OPTIONS_KEY]);
        }

        if (isset($fieldsArray[static::FIELD_ALL]) && $fieldsArray[static::FIELD_ALL] === false) {
            unset($fieldsArray[static::FIELD_ALL]);
        }

        return $this->hasGroupField(self::FIELD_DEFAULTS, $fieldPath) || $fieldsArray === [];
    }

    /**
     * WIll check if the options contain all fields either by implicit or explicit inclusion
     *
     * @param string|null $fieldPath
     * @return bool false if _all is not specified or specified and is not false, true otherwise
     */
    public function hasAllFields(?string $fieldPath = null): bool
    {
        return $this->hasGroupField(self::FIELD_ALL, $fieldPath);
    }

    /**
     * Returns included field names for a path.
     * With a schema this is the effective list (defaults, _all, groups, minus exclusions).
     * Without a schema this is the explicitly included keys in the request data.
     *
     * @param string|null $fieldPath
     * @return array
     */
    public function getIncludedFields(?string $fieldPath = null): array
    {
        if ($this->schema !== null) {
            if ($fieldPath && $this->schema->resolveClass($fieldPath) === null) {
                return $this->getExplicitlyIncludedFields($fieldPath);
            }

            $fields = [];
            foreach ($this->schema->getFieldNames($fieldPath) as $field) {
                $path = $fieldPath ? ($fieldPath . '.' . $field) : $field;
                if ($this->isFieldIncluded($path)) {
                    $fields[] = $field;
                }
            }

            return $fields;
        }

        return $this->getExplicitlyIncludedFields($fieldPath);
    }

    /**
     * @return list<string>
     */
    private function getExplicitlyIncludedFields(?string $fieldPath): array
    {
        if ($fieldPath) {
            $this->assertFieldExists($fieldPath);
        }

        $data = ArrayHelper::getValue($this->data, $fieldPath);
        $fields = [];
        if (is_array($data)) {
            foreach ($data as $field => $_) {
                $_fieldPath = $fieldPath ? ($fieldPath . '.' . $field) : $field;
                if ($this->isFieldExplicitlyIncluded($_fieldPath)) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    private function fieldExists(string $fieldPath): bool
    {
        return ArrayHelper::getValue($this->data, $fieldPath, '-123qwerty') !== '-123qwerty';
    }

    /**
     * @param string $fieldPath
     * @return void
     * @throws \InvalidArgumentException
     */
    private function assertFieldExists(string $fieldPath)
    {
        if (!$this->fieldExists($fieldPath)) {
            throw new \InvalidArgumentException(sprintf('Field "%s" is not available', $fieldPath));
        }
    }

    /**
     * Returns a unique hash (md5) that for a set of fields options
     * The hash will be the same for the same options
     *
     * @return string
     */
    public function getHash(): string
    {
        $encoded = json_encode($this->data);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode data for hashing');
        }
        return md5($encoded);
    }

    private function isMetaKey(string $fieldPath): bool
    {
        $field = $this->lastSegment($fieldPath);
        if ($field === self::OPTIONS_KEY) {
            return true;
        }
        if (!str_starts_with($field, '_')) {
            return false;
        }
        if ($this->schema !== null && $this->schema->hasField($fieldPath)) {
            return false;
        }

        return true;
    }

    private function scopeHasDefaultFields(?string $parentPath): bool
    {
        if ($parentPath === null) {
            return $this->hasDefaultFields(null);
        }
        if (!$this->fieldExists($parentPath)) {
            return true;
        }
        $value = ArrayHelper::getValue($this->data, $parentPath);
        if ($value === true) {
            return true;
        }
        if ($value === false) {
            return false;
        }

        return $this->hasDefaultFields($parentPath);
    }

    private function scopeHasAllFields(?string $parentPath): bool
    {
        if ($parentPath === null) {
            return $this->hasAllFields(null);
        }
        if (!$this->fieldExists($parentPath)) {
            return false;
        }
        $value = ArrayHelper::getValue($this->data, $parentPath);
        if (!is_array($value)) {
            return false;
        }

        return $this->hasAllFields($parentPath);
    }

    private function groupPath(string $group, ?string $parentPath): string
    {
        return $parentPath ? ($parentPath . '.' . $group) : $group;
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
