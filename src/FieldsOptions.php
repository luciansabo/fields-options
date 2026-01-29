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

    public const DEFAULT_GROUPS = [
        self::FIELD_DEFAULTS,
        self::FIELD_ALL
    ];

    public function __construct(array $data = [], ?ValidatorInterface $validator = null)
    {
        if ($data) {
            $validator ??= new Validator();
            $validator->validateData($data);
        }
        $this->data = $data;
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
     * In addition to isFieldSpecified() this will also check if the field is set to `true`.
     *
     * @param string $fieldPath
     * @return bool
     */
    public function isFieldIncluded(string $fieldPath): bool
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

        return $this->isFieldIncluded($path);
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
     * Returns the list of actually explicitly included fields
     * Does not know about defaults or groups. If a field is a default field it won't be returned here.
     * This will probably change in future versions to also include the default fields or coming from group fields
     * if they were included using the group
     *
     * @param string|null $fieldPath
     * @return array
     */
    public function getIncludedFields(?string $fieldPath = null): array
    {
        if ($fieldPath) {
            $this->assertFieldExists($fieldPath);
        }

        $data = ArrayHelper::getValue($this->data, $fieldPath);
        $fields = [];
        if (is_array($data)) {
            foreach ($data as $field => $_) {
                $_fieldPath = $fieldPath ? ($fieldPath . '.' . $field) : $field;
                if ($this->isFieldIncluded($_fieldPath)) {
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
}
