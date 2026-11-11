<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

class IgnorePrivateChildDto extends IgnorePrivateParentDto
{
    public int $id = 1;

    public string $secret = 'child';
}
