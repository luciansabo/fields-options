<?php

namespace Lucian\FieldsOptions;

interface ExportApplierInterface
{
    /**
     * This is s used to mark the exported properties on the object.
     * It is up to the object and/or whatever serialization method you have to actually only export those.
     * The easiest way to do it is to implement the native PHP `JsonSerializable`interface and write the logic right
     * inside the object.
     *
     * @param object|array $data
     * @param array|null $fields
     * @return object|array $data with exported fields
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function setExportedFields(object|array $data, ?array $fields): object|array;

    /**
     * Returns the properties exported by default on the object.
     *
     * @param object|array $data
     * @return string[]
     */
    public function getExportedFields(object|array $data): array;

    /**
     * Should return the base class of your DTO
     * This helps
     *
     * @return string
     */
    public function getSupportedClass(): string;
}
