<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Attribute\Ignore;

abstract class IgnoreParentDto
{
    #[Ignore]
    #[DefaultField]
    public string $parentInternal = 'p';

    #[Ignore]
    protected ?array $bookkeeping = null;
}
