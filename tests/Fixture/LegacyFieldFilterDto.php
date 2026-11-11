<?php

namespace Lucian\FieldsOptions\Test\Fixture;

use Lucian\FieldsOptions\Attribute\Ignore;

class LegacyFieldFilterDto extends AbstractDto
{
    public static int $instances = 0;

    #[Ignore]
    public ?string $internal = 'internal';

    public int $id;

    public ?string $label = null;

    public ?LocationDto $location;
}
