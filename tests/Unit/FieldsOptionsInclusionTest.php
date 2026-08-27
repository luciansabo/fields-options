<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\Attribute\Group;
use Lucian\FieldsOptions\FieldsOptions;
use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\Test\Fixture\Attributed\EducationDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\MixedDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\ProfileDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\RootDto;
use Lucian\FieldsOptions\Test\Fixture\TypeResolutionDto;
use Lucian\FieldsOptions\Validator;
use PHPUnit\Framework\TestCase;

class FieldsOptionsInclusionTest extends TestCase
{
    public function testEmptyRequestIncludesDefaultFieldsOnly(): void
    {
        $options = new FieldsOptions([], null, ProfileDto::class);

        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertTrue($options->isFieldIncluded('name'));
        $this->assertFalse($options->isFieldIncluded('description'));
        $this->assertFalse($options->isFieldIncluded('education'));
        $this->assertEquals(['id', 'name'], $options->getIncludedFields());
    }

    public function testDefaultsFalseExcludesDefaultFieldsUnlessExplicit(): void
    {
        $options = new FieldsOptions(['_defaults' => false], null, ProfileDto::class);
        $this->assertFalse($options->isFieldIncluded('id'));
        $this->assertFalse($options->isFieldIncluded('name'));

        $options = new FieldsOptions(
            ['_defaults' => false, 'name' => true],
            null,
            ProfileDto::class
        );
        $this->assertFalse($options->isFieldIncluded('id'));
        $this->assertTrue($options->isFieldIncluded('name'));
    }

    public function testFieldListTurnsDefaultsOff(): void
    {
        $options = new FieldsOptions(['id' => true], null, ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertFalse($options->isFieldIncluded('name'));
        $this->assertFalse($options->isFieldIncluded('description'));
    }

    public function testAllIncludesUnmarkedFieldsAndHonorsExclude(): void
    {
        $options = new FieldsOptions(
            ['_all' => true, 'description' => false],
            null,
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertTrue($options->isFieldIncluded('education'));
        $this->assertTrue($options->isFieldIncluded('_text'));
        $this->assertFalse($options->isFieldIncluded('description'));
        $this->assertFalse($options->isFieldIncluded('missing'));
    }

    public function testAllWinsOverDefaultsFalse(): void
    {
        $options = new FieldsOptions(
            ['_all' => true, '_defaults' => false],
            null,
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('description'));
        $this->assertTrue($options->isFieldIncluded('id'));
    }

    public function testCustomGroupsAreAdditive(): void
    {
        $options = new FieldsOptions(['_basicInfo' => true], null, ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertTrue($options->isFieldIncluded('name'));
        $this->assertFalse($options->isFieldIncluded('description'));

        $options = new FieldsOptions(
            ['_extended' => true, '_defaults' => false],
            null,
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('description'));
        $this->assertFalse($options->isFieldIncluded('id'));

        $options = new FieldsOptions(
            ['_extended' => false, '_defaults' => true],
            null,
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertFalse($options->isFieldIncluded('description'));
    }

    public function testRepeatableGroupsEitherGroupIsEnough(): void
    {
        $options = new FieldsOptions(['_basicInfo' => true], null, ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('id'));
    }

    public function testParentFalseExcludesDescendants(): void
    {
        $options = new FieldsOptions(
            ['education' => false],
            null,
            ProfileDto::class
        );
        $this->assertFalse($options->isFieldIncluded('education'));
        $this->assertFalse($options->isFieldIncluded('education.institutionName'));
    }

    public function testUnderscorePropertyUsesSchemaLogic(): void
    {
        $options = new FieldsOptions(['_all' => true], null, ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('_text'));
        $this->assertTrue($options->isFieldExplicitlyIncluded('_all'));
        $this->assertFalse($options->isFieldIncluded('_basicInfo'));
    }

    public function testOptionOnlyFieldIsIncluded(): void
    {
        $options = new FieldsOptions(
            ['education' => ['_opt' => ['limit' => 1]]],
            null,
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('education'));
        $this->assertTrue($options->hasDefaultFields('education'));
    }

    public function testNoPrototypeKeepsExplicitBehavior(): void
    {
        $options = new FieldsOptions(['id' => true, 'seo' => false]);
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertFalse($options->isFieldIncluded('seo'));
        $this->assertFalse($options->isFieldIncluded('name'));
        $this->assertEquals(['id'], $options->getIncludedFields());
    }

    public function testWithPrototypeEnablesCalculatedInclusionWithoutMutatingOriginal(): void
    {
        $options = new FieldsOptions([]);
        $this->assertFalse($options->isFieldIncluded('id'));
        $this->assertEquals([], $options->getIncludedFields());

        $bound = $options->withPrototype(ProfileDto::class);
        $this->assertTrue($bound->isFieldIncluded('id'));
        $this->assertTrue($bound->isFieldIncluded('name'));
        $this->assertFalse($bound->isFieldIncluded('description'));
        $this->assertEquals(['id', 'name'], $bound->getIncludedFields());
        $this->assertSame($options->toArray(), $bound->toArray());
        $this->assertSame($options->getHash(), $bound->getHash());
        $this->assertFalse($options->isFieldIncluded('id'));
    }

    public function testWithPrototypeOnExplicitListDoesNotAddDefaults(): void
    {
        $options = (new FieldsOptions(['id' => true]))->withPrototype(ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertFalse($options->isFieldIncluded('name'));
        $this->assertEquals(['id'], $options->getIncludedFields());
    }

    public function testNestedTrueUsesChildDefaultsFromPhpDoc(): void
    {
        $options = new FieldsOptions(['education' => true], null, ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('education'));
        $this->assertTrue($options->isFieldIncluded('education.institutionName'));
        $this->assertTrue($options->isFieldIncluded('education.institutionId'));
        $this->assertEquals(['institutionId', 'institutionName'], $options->getIncludedFields('education'));

        $child = $options->forField('education');
        $this->assertTrue($child->isFieldIncluded('institutionName'));
        $this->assertFalse($child->isFieldIncluded('missing'));
    }

    public function testNestedObjectTrueOnRootProfile(): void
    {
        $options = new FieldsOptions(['profile' => true], null, RootDto::class);
        $this->assertTrue($options->isFieldIncluded('profile'));
        $this->assertTrue($options->isFieldIncluded('profile.id'));
        $this->assertTrue($options->isFieldIncluded('profile.name'));
        $this->assertFalse($options->isFieldIncluded('profile.education'));
        $this->assertFalse($options->isFieldIncluded('seo'));
        $this->assertEquals(['id', 'name'], $options->getIncludedFields('profile'));
    }

    public function testNestedFieldListDisablesChildDefaults(): void
    {
        $options = new FieldsOptions(
            ['education' => ['institutionName' => true]],
            null,
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('education.institutionName'));
        $this->assertFalse($options->isFieldIncluded('education.institutionId'));
    }

    public function testLocationTypedAndUntypedWithVar(): void
    {
        $options = new FieldsOptions(
            ['location' => true, 'location2' => true],
            null,
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('location.city'));
        $this->assertTrue($options->isFieldIncluded('location2.city'));
    }

    public function testUnresolvedNestedTypeFallsBackToExplicit(): void
    {
        $options = new FieldsOptions(
            ['mystery' => ['foo' => true]],
            null,
            MixedDto::class
        );
        $this->assertTrue($options->isFieldIncluded('mystery'));
        $this->assertTrue($options->isFieldIncluded('mystery.foo'));
        $this->assertFalse($options->isFieldIncluded('mystery.bar'));
        $this->assertFalse($options->isFieldIncluded('id'));

        $options = new FieldsOptions(
            ['_defaults' => true, 'mystery' => ['foo' => true]],
            null,
            MixedDto::class
        );
        $this->assertTrue($options->isFieldIncluded('id'));
    }

    public function testUntypedCollectionNestedPathsStayExplicit(): void
    {
        $options = new FieldsOptions(
            ['_all' => true, 'untypedArray' => ['foo' => true]],
            null,
            TypeResolutionDto::class
        );
        $this->assertTrue($options->isFieldIncluded('untypedArray'));
        $this->assertTrue($options->isFieldIncluded('untypedArray.foo'));
        $this->assertFalse($options->isFieldIncluded('untypedArray.bar'));
    }

    public function testDateCreatedChildrenStayExplicit(): void
    {
        $options = new FieldsOptions(['dateCreated' => true], null, ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('dateCreated'));
        $this->assertFalse($options->isFieldIncluded('dateCreated.timezone'));
    }

    public function testBuilderForwardsPrototype(): void
    {
        $builder = new FieldsOptionsBuilder(new Validator(ProfileDto::class));
        $options = $builder->setDefaultFieldsIncluded()->build();
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertFalse($options->isFieldIncluded('description'));
    }

    public function testGroupAttributeRejectsReservedNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Group('_defaults');
    }

    public function testHasGroupFieldStillUsesExplicitCheck(): void
    {
        $options = new FieldsOptions(['id' => true], null, ProfileDto::class);
        $this->assertFalse($options->hasDefaultFields());
        $this->assertFalse($options->hasAllFields());
        $this->assertFalse($options->isFieldExplicitlyIncluded('name'));
        $this->assertFalse($options->isFieldSpecified('name'));
        $this->assertTrue($options->isFieldSpecified('id'));
    }
}
