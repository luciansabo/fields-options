<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\Group;

/**
 * $peer must stay declared before $bad: the peer's walk completes before this class fails.
 */
class GroupMemoCycleInvalidDto
{
    public ?GroupMemoCyclePeerDto $peer = null;

    #[Group('bad')]
    public string $bad = 'x';
}
