<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Test\Fixture\AbstractDto;

class MixedDto extends AbstractDto
{
    #[DefaultField]
    public int $id;

    /** @var mixed */
    public mixed $mystery;
}
