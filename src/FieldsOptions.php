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
    private array $data = [];

    public const DEFAULT_GROUPS = [
        self::FIELD_DEFAULTS,
        self::FIELD_ALL
    ];

    public function __construct(array $data = [])
    {
        static::validate($data);
        $this->data = $data;
    }

    /**
     * Export an array structure for a given path
     *
     * @param string|null $fieldPath
     * @return array
     */
    public function toArray(string $fieldPath = null): array
    {
        return ArrayHelper::getValue($this->data, $fieldPath);
    }

    public function isFieldIncluded(string $fieldPath): bool
    {
        return ArrayHelper::getValue($this->data, $fieldPath, false) !== false;
    }

    public function getFieldOptions(string $fieldPath): array
    {
        $this->assertFieldExists($fieldPath);

        return ArrayHelper::getValue($this->data, $fieldPath . '.' . self::OPTIONS_KEY, []);
    }

    public function getFieldOption(string $fieldPath, string $option, /*mixed*/ $default = null): ?string
    {
        $options = $this->getFieldOptions($fieldPath);
        return $options[$option] ?? $default;
    }

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

    public function hasDefaultFields(?string $fieldPath = null): bool
    {
        return $this->hasGroupField(self::FIELD_DEFAULTS, $fieldPath);
    }

    public function hasAllFields(?string $fieldPath = null): bool
    {
        return $this->hasGroupField(self::FIELD_ALL, $fieldPath);
    }

    public function getIncludedFields(?string $fieldPath = null): array
    {
        if ($fieldPath) {
            $this->assertFieldExists($fieldPath);
        }

        $data = ArrayHelper::getValue($this->data, $fieldPath);
        $fields = [];
        if (is_array($data)) {
            foreach ($data as $field => $value) {
                $_fieldPath = $fieldPath ? ($fieldPath . '.' . $field) : $field;
                if ($this->isFieldIncluded($_fieldPath)) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    private static function validate(array $data): void
    {
        foreach ($data as $key => $datum) {
            if ($key == self::OPTIONS_KEY) {
                continue;
            }

            if (is_array($datum)) {
                static::validate($datum);
            } else if (!is_bool($datum)) {
                throw new \RuntimeException('Invalid field options: ' . $key);
            }
        }
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
}
