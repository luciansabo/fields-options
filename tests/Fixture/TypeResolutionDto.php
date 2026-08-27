<?php

namespace Lucian\FieldsOptions\Test\Fixture;

class TypeResolutionDto
{
    public int $id;

    public LocationDto $location;

    public ?LocationDto $nullableLocation = null;

    /** @var EducationDto[] */
    public array $educationList = [];

    /** @var \iterable|EducationDto[]|null */
    public ?iterable $educationUnion;

    /** @var iterable<WorkplaceDto>|null */
    public ?iterable $workHistory;

    /**
     * @var LocationDto
     */
    public $untypedLocation;

    /** @var mixed */
    public mixed $mystery;

    public string|int $scalarUnion = 1;

    public LocationDto|EducationDto $objectUnion;

    /** @var LocationDto&EducationDto */
    public mixed $objectIntersection;
}
