<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Test\Fixture\AbstractDto;

class RootDto extends AbstractDto
{
    #[DefaultField]
    public int $id;

    #[DefaultField]
    public ProfileDto $profile;

    public ?string $seo = null;
}
