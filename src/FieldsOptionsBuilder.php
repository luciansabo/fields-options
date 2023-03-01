<?php

namespace Lucian\FieldsOptions;

class FieldsOptionsBuilder
{
    private array $data = [];
    public function setFieldIncluded(string $fieldPath): self
    {
        ArrayHelper::setValue($this->data, $fieldPath, true);

        return $this;
    }

    public function setFieldExcluded(string $fieldPath): self
    {
        ArrayHelper::setValue($this->data, $fieldPath, false);

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
