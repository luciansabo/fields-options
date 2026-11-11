<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Test\Fixture\AbstractDto;

class WorkplaceDto extends AbstractDto
{
    protected ?array $__exportedProperties = ['id' => true, 'employerName' => true];

    #[DefaultField]
    public int $id;

    #[DefaultField]
    public string $employerName;

    public int $startYear;

    public ?int $endYear = null;
}
