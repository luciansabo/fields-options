<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreGrandchildDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnorePrivateChildDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreRedeclaringDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreTraitRedeclaringDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreTraitUserChildDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreTraitUserDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\ProfileDto as AttributedProfileDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\StaticPropertyDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\WorkplaceDto as AttributedWorkplaceDto;
use Lucian\FieldsOptions\Test\Fixture\ProfileDto;
use Lucian\FieldsOptions\Test\Fixture\WorkplaceDto;
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
            ['__exportedProperties'],
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

    public function testValidateNestedCollectionFromClassString(): void
    {
        $validator = new Validator(ProfileDto::class);
        $validator->validateField('education.institutionName');
        $validator->validateField('location2.city');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid field path 'education.missing'");
        $validator->validateField('education.missing');
    }

    public function testValidateUnresolvedNestedTypeIsPermissive(): void
    {
        $validator = new Validator(\Lucian\FieldsOptions\Test\Fixture\TypeResolutionDto::class);
        $this->expectNotToPerformAssertions();
        $validator->validateField('mystery.foo');
        $validator->validateField('untypedNoDoc.foo');
        $validator->validateField('untypedObject.foo');
        $validator->validateField('untypedArray.foo');
        $validator->validateField('nullableUntypedArray.foo');
        $validator->validateField('untypedIterable.foo');
        $validator->validateField('objectUnion.foo');
        $validator->validateField('objectIntersection.foo');
        $validator->validateField('nativeIntersection.foo');
    }

    public function testValidateScalarAndTypedCollectionNestedPathsStayInvalid(): void
    {
        $validator = new Validator(\Lucian\FieldsOptions\Test\Fixture\TypeResolutionDto::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid field path 'intList.foo'");
        $validator->validateField('intList.foo');
    }

    public function testValidateFieldRejectsStaticProperty(): void
    {
        $validator = new Validator(StaticPropertyDto::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid field path 'secret'");
        $validator->validateField('secret');
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('ignoredFieldsProvider')]
    public function testValidateFieldRejectsIgnoredProperty(string $class, string $field): void
    {
        $validator = new Validator($class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid field path '$field'");
        $validator->validateField($field);
    }

    public static function ignoredFieldsProvider(): array
    {
        return [
            [IgnoreDto::class, 'internal'],
            [IgnoreDto::class, 'ignoredDefault'],
            [IgnoreDto::class, 'parentInternal'],
            [IgnoreDto::class, 'ignoredInvalidChild'],
            [IgnoreDto::class, 'ignoredInvalidChild.name'],
            [IgnoreRedeclaringDto::class, 'parentInternal'],
            [IgnoreRedeclaringDto::class, 'bookkeeping'],
            [IgnoreGrandchildDto::class, '__state'],
            [IgnoreGrandchildDto::class, '__flags'],
            [IgnoreTraitUserDto::class, '__traitState'],
            [IgnoreTraitRedeclaringDto::class, '__traitState'],
            [IgnoreTraitUserChildDto::class, '__traitState'],
            [ProfileDto::class, '__exportedProperties'],
            [WorkplaceDto::class, '__exportedProperties'],
            [AttributedProfileDto::class, '__exportedProperties'],
            [AttributedWorkplaceDto::class, '__exportedProperties'],
            [AttributedProfileDto::class, 'education.__exportedProperties'],
        ];
    }

    public function testValidateFieldAcceptsPropertyShadowingPrivateIgnoredParent(): void
    {
        $this->expectNotToPerformAssertions();
        (new Validator(IgnorePrivateChildDto::class))->validateField('secret');
        (new Validator(IgnoreGrandchildDto::class))->validateField('secret');
    }

    public function testValidateDataRejectsIgnoredProperty(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid field path 'internal'");
        (new Validator(IgnoreDto::class))->validateData(['id' => true, 'internal' => true]);
    }

    public function testValidateFieldAcceptsDoubleUnderscorePropertyWithoutIgnore(): void
    {
        $this->expectNotToPerformAssertions();
        (new Validator(IgnoreDto::class))->validateField('__legacy');
    }

    public function testGetPrototype(): void
    {
        $dto = ProfileDto::getSampleDto();
        $this->assertSame($dto, (new Validator($dto))->getPrototype());
        $this->assertSame(ProfileDto::class, (new Validator(ProfileDto::class))->getPrototype());
    }

    public function testUnresolvableClassStringThrowsInsteadOfDisablingValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unresolvable prototype class 'App\\Dto\\Typo'");
        new Validator('App\\Dto\\Typo');
    }

    public function testNullPrototypeStillSkipsFieldValidation(): void
    {
        $this->expectNotToPerformAssertions();
        (new Validator(null))->validateField('anything.at.all');
        (new Validator())->validateField('anything.at.all');
    }
}
