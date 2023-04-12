<?php

namespace Lucian\FieldsOptions\Test\Fixture;

class WorkplaceDto extends AbstractDto
{
    protected ?array $__exportedProperties = ['id' => true, 'employerName' => true];
    public int $id;
    public string $employerName;
    public int $startYear;
    public ?int $endYear = null;
}
