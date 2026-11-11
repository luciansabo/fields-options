<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\Ignore;

trait IgnoreStateTrait
{
    #[Ignore]
    protected ?array $__traitState = null;

    public string $traitField = 't';
}
