<?php

namespace Lucian\FieldsOptions;

class FieldsOptionsBuilder
{
    private array $data = [];

    /**
     * Include fields from given path
     *
     * @param string|null $fieldPath Base path
     * @param array $fields Optional list of included fields
     * @return $this
     * @psalm-suppress LessSpecificReturnStatement
     * @psalm-suppress MoreSpecificReturnType
     */

    public function setFieldIncluded(?string $fieldPath, array $fields = []): self
    {
        return $this->setFieldInclusion($fieldPath, $fields, true);
    }

    /**
     * Exclude fields from given path
     *
     * @param string|null $fieldPath Base path
     * @param array $fields Optional list of excluded fields
     * @return $this
     * @psalm-suppress LessSpecificReturnStatement
     * @psalm-suppress MoreSpecificReturnType
     */
    public function setFieldExcluded(?string $fieldPath, array $fields = []): self
    {
        return $this->setFieldInclusion($fieldPath, $fields, false);
    }

    private function setFieldInclusion(?string $fieldPath, array $fields, bool $isIncluded): self
    {
        if (empty($fieldPath) && empty($fields)) {
            throw new \LogicException('No fields provided');
        }

        if (empty($fields)) {
            ArrayHelper::setValue($this->data, $fieldPath, $isIncluded);
        } else {
            $basePath = $fieldPath ? "$fieldPath." : $fieldPath;
            foreach ($fields as $field) {
                ArrayHelper::setValue($this->data, $basePath . $field, $isIncluded);
            }
        }

        return $this;
    }

    public function setFieldOption(string $fieldPath, string $option, /*mixed*/ $value): self
    {
        ArrayHelper::setValue($this->data, $fieldPath . '.' . FieldsOptions::OPTIONS_KEY . '.' . $option, $value);

        return $this;
    }

    public function setFieldOptions(string $fieldPath, array $options): self
    {
        ArrayHelper::setValue(
            $this->data, $fieldPath,
            [FieldsOptions::OPTIONS_KEY => $options]
        );

        return $this;
    }

    public function setGroupFieldIncluded(string $groupField, ?string $fieldPath = null): self
    {
        if (empty($fieldPath)) {
            $fieldPath = $groupField;
        } else {
            $fieldPath = $fieldPath . '.' . $groupField;
        }
        $this->setFieldIncluded($fieldPath);

        return $this;
    }

    public function setDefaultFieldsIncluded(?string $fieldPath = null): self
    {
        $this->setGroupFieldIncluded(FieldsOptions::FIELD_DEFAULTS, $fieldPath);

        return $this;
    }

    public function setAllFieldsIncluded(?string $fieldPath = null): self
    {
        $this->setGroupFieldIncluded(FieldsOptions::FIELD_ALL, $fieldPath);

        return $this;
    }

    public function build(): FieldsOptions
    {
        return new FieldsOptions($this->data);
    }
}
