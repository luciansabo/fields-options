<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Test\Fixture\AbstractDto;

class StaticPropertyDto extends AbstractDto
{
    #[DefaultField]
    public int $id = 1;

    #[DefaultField]
    public static string $secret = 'hidden';
}
