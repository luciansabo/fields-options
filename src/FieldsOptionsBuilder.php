<?php

namespace Lucian\FieldsOptions;

class FieldsOptionsBuilder
{
    private array $data;

    private ?ValidatorInterface $validator;

    /**
     * @param ValidatorInterface|null $validator
     * @param array $data
     */
    public function __construct(ValidatorInterface $validator = null, array $data = [])
    {
        if ($data) {
            $validator ??= new Validator();
            $validator->validateData($data);
        }

        $this->validator = $validator;
        $this->data = $data;
    }

    /**
     * Include fields from given path
     *
     * @param string|null $fieldPath Base path
     * @param array $fields Optional list of included field (you can use relative paths in dot notation too)
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

    private function setFieldInclusion(
        ?string $fieldPath,
        array $fields,
        bool $isIncluded,
        bool $validateField = true
    ): self {
        if (empty($fieldPath) && empty($fields)) {
            throw new \LogicException('No fields provided');
        }

        if (empty($fields)) {
            if ($validateField) {
                $this->validateField($fieldPath);
            }

            ArrayHelper::setValue($this->data, $fieldPath, $isIncluded);
        } else {
            $basePath = $fieldPath ? "$fieldPath." : $fieldPath;

            foreach ($fields as $field) {
                if ($validateField) {
                    $this->validateField($basePath . $field);
                }
                ArrayHelper::setValue($this->data, $basePath . $field, $isIncluded);
            }
        }

        return $this;
    }

    public function setFieldOption(string $fieldPath, string $option, /*mixed*/ $value): self
    {
        $this->validateField($fieldPath);
        ArrayHelper::setValue($this->data, $fieldPath . '.' . FieldsOptions::OPTIONS_KEY . '.' . $option, $value);

        return $this;
    }

    public function setFieldOptions(string $fieldPath, array $options): self
    {
        $this->validateField($fieldPath);
        ArrayHelper::setValue(
            $this->data,
            $fieldPath,
            [FieldsOptions::OPTIONS_KEY => $options]
        );

        return $this;
    }

    public function setGroupFieldIncluded(string $groupField, ?string $fieldPath = null): self
    {
        $this->validateField($fieldPath);
        $this->setFieldInclusion($fieldPath, [$groupField], true, false);

        return $this;
    }

    public function setGroupFieldExcluded(string $groupField, ?string $fieldPath = null): self
    {
        $this->validateField($fieldPath);
        $this->setFieldInclusion($fieldPath, [$groupField], false, false);

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

    public function validateField(?string $fieldPath): void
    {
        if (isset($this->validator)) {
            $this->validator->validateField($fieldPath);
        }
    }

    public function build(): FieldsOptions
    {
        return new FieldsOptions($this->data);
    }
}
