<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\Test\Fixture\ProfileDto;
use Lucian\FieldsOptions\Validator;
use Lucian\FieldsOptions\ValidatorInterface;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private ValidatorInterface $validator;
    public function setUp(): void
    {
        $this->validator = new Validator(ProfileDto::getSampleDto());
        parent::setUp();
    }

    /**
     * @dataProvider invalidFieldsProvider
     * @return void
     */
    public function testValidateInvalidField(string $field)
    {
        $this->expectExceptionMessage("Invalid field path '$field");
        $this->validator->validateField($field);
    }

    /**
     * @dataProvider validFieldsProvider
     * @doesNotPerformAssertions
     * @return void
     */
    public function testValidateValidField(string $field)
    {
        $this->validator->validateField($field);
    }

    /**
     * @dataProvider validDataProvider
     * @doesNotPerformAssertions
     * @param $data
     * @return void
     */
    public function testValidateValidData($data)
    {
        $this->validator->validateData($data);
    }

    /**
     * @dataProvider invalidDataProvider
     * @param $data
     * @return void
     */
    public function testValidateInvalidData($data)
    {
        $this->expectException(\RuntimeException::class);
        $this->validator->validateData($data);
    }

    public function invalidFieldsProvider(): array
    {
        return [
            ['missing'],
            ['location.missing'],
            ['location._customGroup'], // we don't treat groups as fields unless defined as properties
            ['location.cityId.missing'],
            ['education.missing'],
            ['location2.missing'],
            ['location2.cityId.missing'],
            //['__exportedProperties'],
            ['dateCreated.missing'],
        ];
    }

    public function validFieldsProvider(): array
    {
        return [
            ['id'],
            ['location'],
            ['_text'], // not a group, actually a defined property
            ['location.cityId'],
            ['education'], // array
            ['education.institutionId'], // nested field
            ['location2'],   // field set with no type-hinting
            ['location2.cityId'],   // nested field, parent has no type-hinting
        ];
    }

    public function validDataProvider(): array
    {
        return [
            [[]],
            [['_defaults' => false, '_customGroup' => true, '_all' => false]],
            [['id' => true]],
            [['id' => false]],
            [['id' => true, 'education' => ['institutionId' => true]]],
            [['education' => ['_defaults' => true, '_opt' => ['someOpt' => 1]]]],
            [['education' => ['_defaults' => true, 'institutionId' => false]]],
            [['education' => ['_customGroup' => true]]],
        ];
    }

    public function invalidDataProvider(): array
    {
        return [
            [['id' => 'invalid']],
            [['dateCreated' => ['missing' => true]]],
            [['education' => ['institutionId' => 'invalid']]],
            [['education' => ['missing' => true]]],
            [['location' => ['cityId' => 'invalid']]],
            [['location' => ['cityId' => ['missing' => true]]]],
            [['location2' => ['cityId' => 'invalid']]],
            [['location2' => ['cityId' => ['missing' => true]]]],
            [['_defaults' => ['someOpt' => 1]]],
        ];
    }
}
