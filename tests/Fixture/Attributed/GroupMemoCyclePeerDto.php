<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\Group;

class GroupMemoCyclePeerDto
{
    #[Group('_peer')]
    public string $name = 'x';

    public ?GroupMemoCycleInvalidDto $back = null;
}
