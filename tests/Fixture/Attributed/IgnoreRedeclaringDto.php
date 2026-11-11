<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Attribute\Ignore;

class IgnoreRedeclaringDto extends IgnoreParentDto
{
    #[DefaultField]
    public int $id = 1;

    #[DefaultField]
    public string $parentInternal = 'redeclared';

    #[Ignore]
    protected ?array $bookkeeping = ['id' => true];
}
