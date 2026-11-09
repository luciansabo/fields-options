<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

class IgnoreTraitRedeclaringDto
{
    use IgnoreNestedStateTrait;

    public int $id = 1;

    protected ?array $__traitState = null;
}
