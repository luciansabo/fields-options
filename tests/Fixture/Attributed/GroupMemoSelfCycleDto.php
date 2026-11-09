<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\Group;

class GroupMemoSelfCycleDto
{
    #[Group('_basicInfo')]
    public string $name = 'x';

    public ?self $parent = null;

    /** @var list<GroupMemoSelfCycleDto> */
    public array $children = [];
}
