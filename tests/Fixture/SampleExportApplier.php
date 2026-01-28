<?php

namespace Lucian\FieldsOptions\Test\Fixture;

use Lucian\FieldsOptions\ExportApplierInterface;

class SampleExportApplier implements ExportApplierInterface
{
    public function setExportedFields(object|array $data, ?array $fields): object|array
    {
        if ($data instanceof AbstractDto) {
            // keep valid properties only
            if ($fields) {
                $fields = array_filter($fields, [$data, 'propertyExists']);
            }
            $data->setExportedProperties($fields);
        }

        return $data;
    }

    public function getExportedFields(object|array $data): array
    {
        if ($data instanceof AbstractDto) {
            return array_keys(iterator_to_array($data->getIterator()));
        }

        return [];
    }

    /**
     * @return string
     * @psalm-return class-string
     */
    public function getSupportedClass(): string
    {
        return AbstractDto::class;
    }
}
