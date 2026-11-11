<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Attribute\Group;
use Lucian\FieldsOptions\Attribute\Ignore;

class IgnoreOnlyAttributesDto
{
    public int $id = 1;

    #[Ignore]
    #[DefaultField]
    #[Group('_basicInfo')]
    public string $internal = 'x';
}
