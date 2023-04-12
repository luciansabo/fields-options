<?php

namespace Lucian\FieldsOptions;

interface ExportApplierInterface
{
    /**
     * @param object|array $data
     * @param array|null $fields
     * @return object|array $data with exported fields
     */
    public function setExportedFields(/*object|array*/ $data, ?array $fields);

    public function getExportedFields(/*object|array*/ $data): array;
}
