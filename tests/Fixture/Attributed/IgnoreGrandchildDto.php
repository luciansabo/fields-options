<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;

class IgnoreGrandchildDto extends IgnoreMiddleDto
{
    #[DefaultField]
    public int $id = 1;

    protected ?array $__state = ['id' => true];

    protected ?array $__flags = ['x' => true];

    public string $secret = 'grandchild';
}
