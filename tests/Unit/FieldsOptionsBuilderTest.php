<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\FieldsOptions;
use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\Test\Fixture\LocationDto;
use Lucian\FieldsOptions\Test\Fixture\ProfileDto;
use Lucian\FieldsOptions\Validator;
use PHPUnit\Framework\TestCase;

class FieldsOptionsBuilderTest extends TestCase
{
    private FieldsOptionsBuilder $builder;

    public function setUp(): void
    {
        $dto = new ProfileDto();
        $dto->location2 = new LocationDto();
        $this->builder = new FieldsOptionsBuilder(new Validator($dto));
        parent::setUp();
    }

    public function testSetFieldIncluded()
    {
        $fieldsOptions = $this->builder
            ->setFieldIncluded(null, ['id', 'location', 'location2'])
            ->setFieldIncluded('education.institutionId')
            ->setFieldIncluded('location.city')
            ->setFieldIncluded('location2.city')
            ->build();

        // test public field
        $this->assertTrue($fieldsOptions->isFieldIncluded('id'));
        // test protected field
        $this->assertTrue($fieldsOptions->isFieldIncluded('location'));
        // test field with no type-hinting in prototype, but with initialized value
        $this->assertTrue($fieldsOptions->isFieldIncluded('location2'));
        $this->assertFalse($fieldsOptions->isFieldIncluded('name'));

        $this->assertTrue($fieldsOptions->isFieldIncluded('location.city'));
        $this->assertTrue($fieldsOptions->isFieldIncluded('location2.city'));
        $this->assertTrue($fieldsOptions->isFieldIncluded('education.institutionId'));
        $this->assertEquals(['id', 'location', 'location2', 'education'], $fieldsOptions->getIncludedFields());

        // we currently can't properly validate iterables but this should work
        $this->builder->setFieldIncluded('education.institutionId');

        $this->expectException(\LogicException::class);
        $this->builder
            ->setFieldIncluded(null);
    }

    public function testSetFieldsIncludedWithEmptyFieldListAssumesDefault()
    {
        $fieldsOptions = $this->builder
            ->setFieldIncluded('education', [])
            ->build();
        $this->assertEquals(['education' => true], $fieldsOptions->toArray());
    }

    public function testBuildWithInitialValidData()
    {
        $initialData = [
            'id' => true,
            'education' => [
                '_opt' => ['param1' => 1],
                'institutionId' => true
            ]
        ];
        $builder = new FieldsOptionsBuilder(new Validator(ProfileDto::getSampleDto()), $initialData);
        $this->assertInstanceOf(FieldsOptionsBuilder::class, $builder);

        $this->assertEquals($initialData, $builder->build()->toArray());
        // add some options
        $options = $builder
            ->setFieldIncluded('location')
            ->build();

        $this->assertInstanceOf(FieldsOptions::class, $options);
        $this->assertTrue($options->isFieldIncluded('location'));
    }

    public function testBuildWithInitialInvalidData()
    {
        $initialData = [
            'id' => true,
            'education' => [
                '_opt' => ['param1' => 1],
                'missing' => true
            ]
        ];
        $this->expectExceptionMessage("Invalid field path 'education.missing'");
        new FieldsOptionsBuilder(new Validator(ProfileDto::getSampleDto()), $initialData);
    }

    /**
     * @dataProvider invalidFieldsProvider
     * @return void
     */
    public function testSetInvalidFieldIncluded(?string $path, array $fields = [], ?string $invalidFieldPath = null)
    {
        $message = 'Invalid field path';
        if ($invalidFieldPath) {
            $message .= " '$invalidFieldPath'";
        }
        $this->expectExceptionMessage($message);
        $this->builder->setFieldIncluded($path, $fields);
    }

    public function invalidFieldsProvider(): array
    {
        // sub-array structure:
        // ?string $path, array $fields = [], ?string $invalidFieldPath = null
        return [
            ['missing'],
            ['location.missing'],
            ['location2.missing', [], 'location2.missing'],
            //['__exportedProperties'],
            ['dateCreated.missing', [], 'dateCreated.missing'],
            [null, ['missing']],
            ['location', ['cityId', 'missing'], 'location.missing'],
        ];
    }

    public function testSetFieldsIncluded()
    {
        // root fields
        $fieldsOptions = $this->builder
            ->setFieldIncluded(null, ['id', 'location', 'education.institutionId'])
            ->build();

        $this->assertTrue($fieldsOptions->isFieldIncluded('id'));
        $this->assertTrue($fieldsOptions->isFieldIncluded('location'));
        $this->assertTrue($fieldsOptions->isFieldIncluded('education.institutionId'));

        // child fields
        $fieldsOptions = $this->builder
            ->setFieldIncluded('education', ['institutionId'])
            ->build();

        $this->assertTrue($fieldsOptions->isFieldIncluded('education.institutionId'));
    }

    public function testSetFieldExcluded()
    {
        $fieldsOptions = $this->builder
            ->setFieldExcluded('name')
            ->setFieldExcluded('education')
            ->build();

        $this->assertFalse($fieldsOptions->isFieldIncluded('name'));
        $this->assertFalse($fieldsOptions->isFieldIncluded('education'));

        // child fields
        $fieldsOptions = $this->builder
            ->setFieldExcluded('education', ['institutionName'])
            ->build();

        $this->assertFalse($fieldsOptions->isFieldIncluded('education.institutionName'));

        // complex + nested child fields
        $fieldsOptions = $this->builder
            ->setFieldExcluded(null, ['name', 'location2.country'])
            ->setFieldExcluded('education', ['institutionId'])
            ->setFieldExcluded('location', ['cityId'])
            ->build();

        $this->assertFalse($fieldsOptions->isFieldIncluded('name'));
        $this->assertFalse($fieldsOptions->isFieldIncluded('location2.country'));
        $this->assertFalse($fieldsOptions->isFieldIncluded('education.institutionId'));
        $this->assertFalse($fieldsOptions->isFieldIncluded('location.cityId'));
    }

    public function testSetFieldOption()
    {
        $fieldsOptions = $this->builder
            ->setFieldOption('location', 'withName', 1)
            ->setFieldOption('education', 'limit', 2)
            ->setFieldOption('workHistory.employerName', 'fullName', 1)
            ->build();

        $this->assertEquals(1, $fieldsOptions->getFieldOption('location', 'withName'));
        $this->assertEquals(2, $fieldsOptions->getFieldOption('education', 'limit'));
        $this->assertEquals(1, $fieldsOptions->getFieldOption('workHistory.employerName', 'fullName'));

        $this->expectExceptionMessage('Invalid field path');
        $this->builder->setFieldOption('location.missing', 'option1', 1);
    }

    public function testSetFieldOptions()
    {
        $educationOptions = ['limit' => 2, 'offset' => 5];
        $fieldsOptions = $this->builder
            ->setFieldOptions('education', $educationOptions)
            ->setFieldOptions('workHistory.employerName', ['fullName' => 1])
            ->build();

        $this->assertEquals($educationOptions, $fieldsOptions->getFieldOptions('education'));
        $this->assertEquals(['fullName' => 1], $fieldsOptions->getFieldOptions('workHistory.employerName'));

        $this->expectExceptionMessage("Invalid field path 'location.missing'");
        $this->builder->setFieldOptions('location.missing', ['option1' => 1]);
    }


    public function testSetGroupFields()
    {
        $fieldsOptions = $this->builder
            ->setDefaultFieldsIncluded('education')
            ->setAllFieldsIncluded('workHistory')
            ->setGroupFieldIncluded('_basicInfo', 'location2')
            ->build();

        // when some fields are specified defaults is not assumed
        $this->assertFalse($fieldsOptions->hasDefaultFields());
        $this->assertFalse($fieldsOptions->hasAllFields()); // implicit
        $this->assertTrue($fieldsOptions->hasDefaultFields('education'));
        $this->assertTrue($fieldsOptions->hasAllFields('workHistory'));
        $this->assertTrue($fieldsOptions->hasGroupField('_basicInfo', 'location2'));
    }
}
