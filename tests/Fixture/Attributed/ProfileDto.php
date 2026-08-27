<?php

namespace Lucian\FieldsOptions\Test\Fixture\Attributed;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Attribute\Group;
use Lucian\FieldsOptions\Test\Fixture\AbstractDto;

/** @property LocationDto $location2 */
class ProfileDto extends AbstractDto
{
    protected ?array $__exportedProperties = ['id' => true, 'name' => true];

    #[DefaultField]
    #[Group('_basicInfo')]
    public int $id;

    #[DefaultField]
    #[Group('_basicInfo')]
    public string $name;

    #[Group('_extended')]
    public ?string $description = null;

    /** @var \iterable|EducationDto[]|null */
    public ?iterable $education;

    /** @var \iterable|WorkplaceDto[]|null */
    public ?iterable $workHistory;

    protected ?LocationDto $location = null;

    /**
     * @var LocationDto
     */
    protected $location2 = null;

    public ?\DateTimeImmutable $dateCreated = null;

    public ?string $_text = null;

    protected function setLocation(LocationDto $location)
    {
        $this->location = $location;
    }

    /**
     * @param LocationDto $location
     */
    protected function setLocation2(LocationDto $location)
    {
        $this->location2 = $location;
    }

    public static function getSampleDto(): self
    {
        $dto = new self();
        $dto->id = 1;
        $dto->name = 'John';
        $dto->description = 'test';
        $dto->dateCreated = new \DateTimeImmutable('2023-01-01');

        $dto->education = [];
        $education = new EducationDto();
        $education->institutionId = 3;
        $education->institutionName = 'Columbia';
        $dto->education[] = $education;

        $education = new EducationDto();
        $education->institutionId = 4;
        $education->institutionName = 'MIT';
        $dto->education[] = $education;

        $dto->workHistory = [];
        $workPlace = new WorkplaceDto();
        $workPlace->id = 1;
        $workPlace->employerName = 'CNN';
        $workPlace->startYear = 2019;
        $workPlace->endYear = 2020;
        $dto->workHistory[] = $workPlace;

        $workPlace = new WorkplaceDto();
        $workPlace->id = 2;
        $workPlace->employerName = 'BBC';
        $workPlace->startYear = 2020;
        $workPlace->endYear = 2021;
        $dto->workHistory[] = $workPlace;

        $location = new LocationDto();
        $location->cityId = 1;
        $location->countryId = 2;
        $location->city = 'Bucharest';
        $location->country = 'Romania';
        $dto->location2 = $location;

        return $dto;
    }
}
