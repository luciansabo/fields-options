<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Test\Fixture\AbstractDto;

class LocationDto extends AbstractDto
{
    #[DefaultField]
    public ?int $cityId = null;

    #[DefaultField]
    public ?int $countryId = null;

    #[DefaultField]
    public ?string $city = null;

    #[DefaultField]
    public ?string $country = null;
}
