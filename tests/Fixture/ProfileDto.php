<?php

namespace Lucian\FieldsOptions\Test\Fixture;

/** @property LocationDto $location2 */
class ProfileDto extends AbstractDto
{
    protected ?array $__exportedProperties = ['id' => true, 'name' => true];
    public int $id;
    public string $name;
    public ?string $description = null;

    /** @var \iterable|EducationDto[]|null  */
    public ?iterable $education;

    /** @var \iterable|WorkplaceDto[]|null  */
    public ?iterable $workHistory;

    // test protected properties too
    protected ?LocationDto $location = null;

    /**
     * @var LocationDto
     * Leave it without type-hinting, so we know we can inspect such nested objects by value
     */
    protected $location2 = null;

    public ?\DateTimeImmutable $dateCreated = null;

    /** needed to set protected location property */
    protected function setLocation(LocationDto $location)
    {
        $this->location = $location;
    }

    /**
     * Needed only to make sure we don't set anything else than a LocationDto
     * We want to test a mixed type property and leave it without type-hinting
     *
     * @param LocationDto $location
     * @return void
     */
    protected function setLocation2(LocationDto $location)
    {
        $this->location2 = $location;
    }
}
