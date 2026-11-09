<?php

namespace Lucian\FieldsOptions\Test\Fixture;

/**
 * Records every setExportedFields() call verbatim (no filtering, no dedup), then delegates
 * so DTO state and getExportedFields() behave exactly as with SampleExportApplier.
 */
class RecordingExportApplier extends SampleExportApplier
{
    /**
     * @var list<array{object|array, array|null}>
     */
    public array $calls = [];

    #[\Override]
    public function setExportedFields(object|array $data, ?array $fields)
    {
        $this->calls[] = [$data, $fields];
        parent::setExportedFields($data, $fields);
    }

    /**
     * @return list<array|null> raw field lists passed for $object, in call order
     */
    public function fieldsPassedFor(object $object): array
    {
        $passed = [];
        foreach ($this->calls as [$data, $fields]) {
            if ($data === $object) {
                $passed[] = $fields;
            }
        }

        return $passed;
    }
}
