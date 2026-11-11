<?php

namespace Lucian\FieldsOptions\Test\Fixture;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Attribute\Group;
use Lucian\FieldsOptions\Attribute\Ignore;

class AttributedFieldFilterDto extends AbstractDto
{
    #[DefaultField]
    public static string $counter = 'static';

    #[DefaultField]
    public int $id = 1;

    #[DefaultField]
    #[Group('_extra')]
    public string $name = 'n';

    #[DefaultField]
    #[Ignore]
    public string $secret = 'hidden';

    #[DefaultField]
    public ?string $__marker = 'kept';
}
