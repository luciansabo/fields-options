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

    public $untypedNoDoc;

    public object $untypedObject;

    public array $untypedArray = [];

    public ?array $nullableUntypedArray = null;

    public iterable $untypedIterable;

    /** @var int[] */
    public array $intList = [];

    public string|int $scalarUnion = 1;

    public LocationDto|EducationDto $objectUnion;

    public LocationDto|string $objectOrString;

    /** @var LocationDto&EducationDto */
    public mixed $objectIntersection;

    public LocationDto&EducationDto $nativeIntersection;
}
