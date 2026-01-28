<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\Test\Fixture\ProfileDto;
use Lucian\FieldsOptions\Validator;
use Lucian\FieldsOptions\ValidatorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private ValidatorInterface $validator;
    public function setUp(): void
    {
        $this->validator = new Validator(ProfileDto::getSampleDto());
        parent::setUp();
    }

    #[DataProvider('invalidFieldsProvider')]
    public function testValidateInvalidField(string $field)
    {
        $this->expectExceptionMessage("Invalid field path '$field");
        $this->validator->validateField($field);
    }

    #[DataProvider('validFieldsProvider')]
    public function testValidateValidField(string $field)
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validateField($field);
    }

    #[DataProvider('validDataProvider')]
    public function testValidateValidData($data)
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validateData($data);
    }

    #[DataProvider('invalidDataProvider')]
    public function testValidateInvalidData($data)
    {
        $this->expectException(\RuntimeException::class);
        $this->validator->validateData($data);
    }

    public static function invalidFieldsProvider(): array
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

    public static function validFieldsProvider(): array
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

    public static function validDataProvider(): array
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

    public static function invalidDataProvider(): array
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
