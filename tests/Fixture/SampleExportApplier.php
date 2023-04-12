<?php

namespace Lucian\FieldsOptions\Test\Fixture;

use Lucian\FieldsOptions\ExportApplierInterface;

class SampleExportApplier implements ExportApplierInterface
{
    public function setExportedFields($data, ?array $fields)
    {
        if ($data instanceof AbstractDto) {
            // keep valid properties only
            if ($fields) {
                $fields = array_filter($fields, [$data, 'propertyExists']);
            }
            $data->setExportedProperties($fields);
        }
    }

    public function getExportedFields($data): array
    {
        if ($data instanceof AbstractDto) {
            return array_keys(iterator_to_array($data->getIterator()));
        }
    }
}
