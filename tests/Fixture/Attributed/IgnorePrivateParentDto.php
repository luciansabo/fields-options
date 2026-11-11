<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\Ignore;

abstract class IgnorePrivateParentDto
{
    /** @psalm-suppress UnusedProperty */
    #[Ignore]
    private string $secret = 'parent';
}
