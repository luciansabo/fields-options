<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\Ignore;

abstract class IgnoreGrandparentDto
{
    #[Ignore]
    protected ?array $__state = null;

    #[Ignore]
    protected ?array $__flags = null;

    #[Ignore]
    private string $secret = 'grandparent';
}
