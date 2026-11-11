<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Test\Fixture\AbstractDto;

class EducationDto extends AbstractDto
{
    #[DefaultField]
    public int $institutionId;

    #[DefaultField]
    public string $institutionName;
}
