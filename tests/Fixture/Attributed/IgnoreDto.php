<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Attribute\Group;
use Lucian\FieldsOptions\Attribute\Ignore;

class IgnoreDto extends IgnoreParentDto
{
    #[DefaultField]
    public int $id = 1;

    public string $name = 'n';

    #[Ignore]
    protected string $internal = 'x';

    #[Ignore]
    #[DefaultField]
    public string $ignoredDefault = 'x';

    #[Ignore]
    #[Group('notUnderscored')]
    public string $ignoredInvalidGroup = 'x';

    #[Ignore]
    public ?InvalidGroupNameDto $ignoredInvalidChild = null;

    public ?string $__legacy = null;
}
