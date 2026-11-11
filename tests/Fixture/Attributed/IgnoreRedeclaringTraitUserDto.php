<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

class IgnoreRedeclaringTraitUserDto
{
    use IgnoreRedeclaringStateTrait;

    public int $id = 1;
}
