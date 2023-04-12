<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\ExportApplierInterface;
use Lucian\FieldsOptions\FieldsOptions;
use Lucian\FieldsOptions\FieldsOptionsObjectApplier;
use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\Test\Fixture;
use PHPUnit\Framework\TestCase;

class FieldsOptionsObjectApplierTest extends TestCase
{
    private FieldsOptionsObjectApplier $applier;

    public function setUp(): void
    {
        $this->applier = new FieldsOptionsObjectApplier(new SampleExportApplier());
        parent::setUp();
    }

    public function testApplyOneProperty()
    {
        $dto = $this->getSampleDto();

        $fieldsOptions = (new FieldsOptionsBuilder())
            ->setFieldIncluded('id')
            ->build();

        $this->applier->apply($dto, $fieldsOptions);

        $this->assertEquals(['id' => 1], $dto->jsonSerialize());
    }

    public function testApplyNestedProperty()
    {
        $dto = $this->getSampleDto();

        $fieldsOptions = (new FieldsOptionsBuilder())
            ->setFieldIncluded('id')
            ->setFieldIncluded('education', ['institutionName'])
            ->build();

        $this->applier->apply($dto, $fieldsOptions);

        $this->assertEquals(
            [
                'id' => 1,
                'education' => [
                    ['institutionName' => 'Columbia'],
                    ['institutionName' => 'MIT']
                ]
            ],
            json_decode(json_encode($dto), true)
        );
    }

    public function testApplyNestedPropertyWithCache()
    {
        $dto = $this->getSampleDto();

        $fieldsOptions = (new FieldsOptionsBuilder())
            ->setFieldIncluded('id')
            ->setFieldIncluded('education', ['institutionName'])
            ->setDefaultFieldsIncluded('workHistory')
            ->setFieldIncluded('workHistory', ['startYear'])
            ->build();

        // apply to a nested object to test cache
        $this->applier->apply($dto->education[0], new FieldsOptions($fieldsOptions->toArray('education')));

        $this->assertEquals(
            ['institutionName' => 'Columbia'],
            json_decode(json_encode($dto->education[0]), true)
        );

        // test with a generator
        $dto->workHistory = iterator_to_array($this->getWorkHistoryGenerator($dto));
        $this->applier->apply($dto, $fieldsOptions);

        $this->assertEquals(
            [
                'id' => 1,
                'education' => [
                    ['institutionName' => 'Columbia'],
                    ['institutionName' => 'MIT']
                ],
                'workHistory' => [
                    ['id' => 1, 'startYear' => 2019],
                    ['id' => 2, 'startYear' => 2020],
                ]
            ],
            json_decode(json_encode($dto), true)
        );
    }

    public function testApplyAllFields()
    {
        $sampleDto = $this->getSampleDto();
        $dto = clone $sampleDto;

        $fieldsOptions = (new FieldsOptionsBuilder())
            ->setAllFieldsIncluded()
            ->build();

        $this->applier->apply($dto, $fieldsOptions);
        $sampleDto->setExportedProperties(null);

        $this->assertEquals(
            json_decode(json_encode($sampleDto), true),
            json_decode(json_encode($dto), true)
        );
    }

    public function testApplyAllFieldsExceptOne()
    {
        $dto = $this->getSampleDto();

        $fieldsOptions = (new FieldsOptionsBuilder($dto))
            ->setAllFieldsIncluded()
            ->setAllFieldsIncluded('education')
            ->setFieldExcluded('education', ['institutionName'])
            ->setDefaultFieldsIncluded('workHistory')
            ->setFieldIncluded('workHistory', ['startYear'])
            ->setFieldExcluded('workHistory', ['employerName'])
            ->build();

        $this->applier->apply(
            $dto,
            $fieldsOptions
        );

        $this->assertEquals(
            [
                'id'          => 1,
                'name'        => 'John',
                'education'   => [
                    ['institutionId' => 3],
                    ['institutionId' => 4]
                ],
                'description' => 'test',
                'workHistory' => [
                    ['id' => 1, 'startYear' => 2019],
                    ['id' => 2, 'startYear' => 2020],
                ],
                'dateCreated' => '2023-01-01 00:00:00',
                'location'    => null,
                'location2'   => null,
            ],
            json_decode(json_encode($dto), true)
        );
    }

    public function testApplyNoDefaultsSpecified()
    {
        $dto = $this->getSampleDto();

        $fieldsOptions = (new FieldsOptionsBuilder($dto))
            ->setAllFieldsIncluded()
            ->setAllFieldsIncluded('education')
            ->setFieldExcluded('education', ['institutionName'])
            ->setDefaultFieldsIncluded('workHistory')
            ->setFieldIncluded('workHistory', ['endYear'])
            ->setFieldExcluded('workHistory', ['startYear'])
            ->build();

        $this->applier->apply(
            $dto,
            $fieldsOptions
        );

        $this->assertEquals(
            [
                'id'          => 1,
                'name'        => 'John',
                'education'   => [
                    ['institutionId' => 3],
                    ['institutionId' => 4]
                ],
                'description' => 'test',
                'workHistory' => [
                    ['id' => 1, 'employerName' => 'CNN', 'endYear' => 2020],
                    ['id' => 2, 'employerName' => 'BBC', 'endYear' => 2021],
                ],
                'dateCreated' => '2023-01-01 00:00:00',
                'location'    => null,
                'location2'   => null,
            ],
            json_decode(json_encode($dto), true)
        );
    }

    public function testApplyWithDefaults()
    {
        $dto = $this->getSampleDto();

        $fieldsOptions = (new FieldsOptionsBuilder($dto))
            ->setDefaultFieldsIncluded()
            ->setFieldExcluded(null, ['name', 'education'])
            ->setDefaultFieldsIncluded('workHistory')
            ->setFieldIncluded('workHistory', ['startYear'])
            ->setFieldExcluded('workHistory', ['employerName'])
            ->build();

        // defaults mentioned
        $this->applier->apply(
            $dto,
            $fieldsOptions
        );

        $this->assertEquals(
            [
                'id'          => $dto->id,
                'workHistory' => json_decode(json_encode($dto->workHistory), true),
            ],
            json_decode(json_encode($dto), true)
        );
    }

    private function getSampleDto()
    {
        $dto = new Fixture\ProfileDto();
        $dto->id = 1;
        $dto->name = 'John';
        $dto->description = 'test';
        $dto->dateCreated = new \DateTimeImmutable('2023-01-01');

        $dto->education = [];
        $education = new Fixture\EducationDto();
        $education->institutionId = 3;
        $education->institutionName = 'Columbia';
        $dto->education[] = $education;

        $education = new Fixture\EducationDto();
        $education->institutionId = 4;
        $education->institutionName = 'MIT';
        $dto->education[] = $education;

        $dto->workHistory = [];
        $workPlace = new Fixture\WorkplaceDto();
        $workPlace->id = 1;
        $workPlace->employerName = 'CNN';
        $workPlace->startYear = 2019;
        $workPlace->endYear = 2020;
        $dto->workHistory[] = $workPlace;

        $workPlace = new Fixture\WorkplaceDto();
        $workPlace->id = 2;
        $workPlace->employerName = 'BBC';
        $workPlace->startYear = 2020;
        $workPlace->endYear = 2021;
        $dto->workHistory[] = $workPlace;

        return $dto;
    }

    private function getWorkHistoryGenerator(Fixture\ProfileDto $dto)
    {
        $fieldsOptions = (new FieldsOptionsBuilder())
            ->setFieldIncluded(null, ['id', 'startYear'])
            ->build();

        foreach ($dto->workHistory as $item) {
            $this->applier->apply($item, $fieldsOptions);
            yield $item;
        }
    }
}

class SampleExportApplier implements ExportApplierInterface
{
    public function setExportedFields($data, ?array $fields)
    {
        if ($data instanceof Fixture\AbstractDto) {
            // keep valid properties only
            if ($fields) {
                $fields = array_filter($fields, [$data, 'propertyExists']);
            }
            $data->setExportedProperties($fields);
        }
    }

    public function getExportedFields($data): array
    {
        if ($data instanceof Fixture\AbstractDto) {
            return array_keys(iterator_to_array($data->getIterator()));
        }
    }
}
