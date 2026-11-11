<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\Group;

class InvalidGroupNameDto
{
    #[Group('basicInfo')]
    public string $name = 'x';
}
