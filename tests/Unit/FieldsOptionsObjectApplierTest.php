<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\FieldsOptions;
use Lucian\FieldsOptions\FieldsOptionsObjectApplier;
use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\Test\Fixture\ProfileDto;
use Lucian\FieldsOptions\Test\Fixture\SampleExportApplier;
use Lucian\FieldsOptions\Validator;
use PHPUnit\Framework\TestCase;

class FieldsOptionsObjectApplierTest extends TestCase
{
    private FieldsOptionsObjectApplier $applier;
    private FieldsOptionsBuilder $builder;

    public function setUp(): void
    {
        $this->applier = new FieldsOptionsObjectApplier(new SampleExportApplier());
        $this->dto = $this->getSampleDto();
        $this->builder = new FieldsOptionsBuilder(new Validator($this->dto));
        parent::setUp();
    }

    public function testApplyOneProperty()
    {
        $fieldsOptions = $this->builder
            ->setFieldIncluded('id')
            ->build();

        $this->applier->apply($this->dto, $fieldsOptions);

        $this->assertEquals(['id' => 1], $this->dto->jsonSerialize());
    }

    public function testApplyEmptyFieldListGivesDefaults()
    {
        $fieldsOptions = $this->builder
            ->setFieldIncluded('education', [])
            ->build();

        // take defaults before applier
        $defaultFields = json_encode($this->dto->education);

        $this->applier->apply($this->dto, $fieldsOptions);

        $this->assertEquals(
            json_encode(['education' => json_decode($defaultFields, true)]),
            json_encode($this->dto)
        );
    }

    public function testApplyDefaultsFalse()
    {
        $fieldsOptions = $this->builder
            ->setGroupFieldExcluded('_defaults', 'education')
            ->build();

        $this->applier->apply($this->dto, $fieldsOptions);

        $this->assertEquals(json_encode(['education' => [null, null]]), json_encode($this->dto));
    }

    public function testApplyAllFalseGivesDefaults()
    {
        $fieldsOptions = $this->builder
            ->setGroupFieldExcluded('_all', 'education')
            ->build();

        // take defaults before applier
        $defaultFields = json_encode($this->dto->education);

        $this->applier->apply($this->dto, $fieldsOptions);

        $this->assertEquals(
            json_encode(['education' => json_decode($defaultFields, true)]),
            json_encode($this->dto)
        );
    }

    public function testApplyNestedProperty()
    {
        $fieldsOptions = $this->builder
            ->setFieldIncluded('id')
            ->setFieldIncluded('education', ['institutionName'])
            ->build();

        $this->applier->apply($this->dto, $fieldsOptions);

        $this->assertEquals(
            [
                'id' => 1,
                'education' => [
                    ['institutionName' => 'Columbia'],
                    ['institutionName' => 'MIT']
                ]
            ],
            json_decode(json_encode($this->dto), true)
        );
    }

    public function testApplyNestedPropertyWithCache()
    {
        $fieldsOptions = $this->builder
            ->setFieldIncluded('id')
            ->setFieldIncluded('education', ['institutionName'])
            ->setDefaultFieldsIncluded('workHistory')
            ->setFieldIncluded('workHistory', ['startYear'])
            ->build();

        // apply to a nested object to test cache
        $this->applier->apply($this->dto->education[0], new FieldsOptions($fieldsOptions->toArray('education')));

        $this->assertEquals(
            ['institutionName' => 'Columbia'],
            json_decode(json_encode($this->dto->education[0]), true)
        );

        // test with a generator
        $dto = $this->dto;
        $dto->workHistory = iterator_to_array($this->getWorkHistoryGenerator($dto));
        $this->applier->apply($this->dto, $fieldsOptions);

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
            json_decode(json_encode($this->dto), true)
        );
    }

    public function testApplyAllFields()
    {
        $sampleDto = $this->dto;
        $dto = clone $sampleDto;

        $fieldsOptions = $this->builder
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
        $fieldsOptions = $this->builder
            ->setAllFieldsIncluded()
            ->setAllFieldsIncluded('education')
            ->setFieldExcluded('education', ['institutionName'])
            ->setDefaultFieldsIncluded('workHistory')
            ->setFieldIncluded('workHistory', ['startYear'])
            ->setFieldExcluded('workHistory', ['employerName'])
            ->build();

        $this->applier->apply(
            $this->dto,
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
                'location2'   => [
                    'cityId' => 1,
                    'countryId' => 2,
                    'city' => 'Bucharest',
                    'country' => 'Romania',
                ],
                '_text' => null
            ],
            json_decode(json_encode($this->dto), true)
        );
    }

    public function testApplyNoDefaultsSpecified()
    {
        $fieldsOptions = $this->builder
            ->setAllFieldsIncluded()
            ->setAllFieldsIncluded('education')
            ->setFieldExcluded('education', ['institutionName'])
            ->setDefaultFieldsIncluded('workHistory')
            ->setFieldIncluded('workHistory', ['endYear'])
            ->setFieldExcluded('workHistory', ['startYear'])
            ->build();

        $this->applier->apply(
            $this->dto,
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
                'location2'   => [
                    'cityId' => 1,
                    'countryId' => 2,
                    'city' => 'Bucharest',
                    'country' => 'Romania',
                ],
                '_text' => null
            ],
            json_decode(json_encode($this->dto), true)
        );
    }

    public function testApplyWithDefaults()
    {
        $fieldsOptions = $this->builder
            ->setDefaultFieldsIncluded()
            ->setFieldExcluded(null, ['name', 'education'])
            ->setDefaultFieldsIncluded('workHistory')
            ->setFieldIncluded('workHistory', ['startYear'])
            ->setFieldExcluded('workHistory', ['employerName'])
            ->setFieldIncluded(null, ['dateCreated', 'location2.cityId'])
            ->build();

        // defaults mentioned
        $this->applier->apply(
            $this->dto,
            $fieldsOptions
        );

        $this->assertEquals(
            [
                'id'          => $this->dto->id,
                'workHistory' => json_decode(json_encode($this->dto->workHistory), true),
                'dateCreated' => '2023-01-01 00:00:00',
                'location2'   => ['cityId' => 1]
            ],
            json_decode(json_encode($this->dto), true)
        );
    }

    private function getWorkHistoryGenerator(ProfileDto $dto): \Generator
    {
        $fieldsOptions = (new FieldsOptionsBuilder())
            ->setFieldIncluded(null, ['id', 'startYear'])
            ->build();

        foreach ($dto->workHistory as $item) {
            $this->applier->apply($item, $fieldsOptions);
            yield $item;
        }
    }

    private function getSampleDto(): ProfileDto
    {
        return ProfileDto::getSampleDto();
    }
}
