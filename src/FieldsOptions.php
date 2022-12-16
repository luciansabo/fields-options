<?php

namespace Lucian\FieldsOptions;

/**
 * Holds a structure of wanted fields and their options
 */
class FieldsOptions
{
    private const OPTIONS_KEY = '_opt';
    private const FIELD_DEFAULTS = '_defaults';
    private const FIELD_ALL = '_all';
    private array $data = [];

    public const DEFAULT_GROUPS = [
        self::FIELD_DEFAULTS,
        self::FIELD_ALL
    ];

    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->data = $data;

        return $instance;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function isFieldIncluded(string $fieldPath): bool
    {
        return ArrayExtractor::getValue($this->data, $fieldPath, false) !== false;
    }

    public function getFieldOptions(string $fieldPath): array
    {
        $this->assertFieldExists($fieldPath);

        return ArrayExtractor::getValue($this->data, $fieldPath . '.' . self::OPTIONS_KEY, []);
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

        $data = ArrayExtractor::getValue($this->data, $fieldPath);
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

    private function fieldExists(string $fieldPath): bool
    {
        return ArrayExtractor::getValue($this->data, $fieldPath, '-123qwerty') !== '-123qwerty';
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
