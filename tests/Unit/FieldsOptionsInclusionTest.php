<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Attribute\Group;
use Lucian\FieldsOptions\FieldSchema;
use Lucian\FieldsOptions\FieldSchemaCache;
use Lucian\FieldsOptions\FieldsOptions;
use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\NestedTypeResolver;
use Lucian\FieldsOptions\Test\Fixture\Attributed\EducationDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\GroupMemoCycleInvalidDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\GroupMemoCyclePeerDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\GroupMemoSelfCycleDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreGrandchildDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreGrandparentDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreOnlyAttributesDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnorePrivateChildDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreRedeclaringDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreTraitRedeclaringDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreTraitUserChildDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreTraitUserDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\InvalidGroupNameDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\LocationDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\MixedDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\NestedInvalidGroupDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\ProfileDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\RootDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\StaticPropertyDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\WorkplaceDto;
use Lucian\FieldsOptions\Test\Fixture\CustomValidator;
use Lucian\FieldsOptions\Test\Fixture\ProfileDto as PlainProfileDto;
use Lucian\FieldsOptions\Test\Fixture\TypeResolutionDto;
use Lucian\FieldsOptions\Test\Fixture\WorkplaceDto as PlainWorkplaceDto;
use Lucian\FieldsOptions\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FieldsOptionsInclusionTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetValidatedGroupClasses();
    }

    protected function tearDown(): void
    {
        $this->resetValidatedGroupClasses();
    }

    public function testStaticPropertiesAreNotRequestFields(): void
    {
        $options = $this->options([], StaticPropertyDto::class);

        $this->assertFalse($options->isFieldIncluded('secret'));
        $this->assertEquals(['id'], $options->getIncludedFields());
        $this->assertEquals(
            ['id'],
            $this->options(['_all' => true], StaticPropertyDto::class)->getIncludedFields()
        );
        $this->assertEquals(['id'], (new FieldSchema(StaticPropertyDto::class))->getFieldNames());
        $this->assertFalse(FieldSchema::classDeclaresInclusionAttributes(
            (new class {
                #[DefaultField]
                public static string $secret = 'hidden';
            })::class
        ));
    }

    public function testOnlyStaticStateIsAttributeDerivedMemos(): void
    {
        $this->assertSame(['validatedGroupClasses'], $this->staticPropertyNames(FieldSchema::class));
        $this->assertSame(['ignoredDeclarations'], $this->staticPropertyNames(NestedTypeResolver::class));
        $this->assertSame([], $this->staticPropertyNames(FieldSchemaCache::class));

        new FieldSchema(ProfileDto::class);
        $memo = $this->validatedGroupClasses();
        $this->assertNotSame([], $memo);
        foreach ($memo as $class => $flag) {
            $this->assertTrue(class_exists($class));
            $this->assertTrue($flag);
        }

        (new FieldSchema(IgnoreGrandchildDto::class))->getFieldNames();
        $ignored = (new \ReflectionProperty(NestedTypeResolver::class, 'ignoredDeclarations'))->getValue();
        $this->assertIsArray($ignored);
        $this->assertTrue($ignored[IgnoreGrandchildDto::class . '::__flags']);
        $this->assertTrue($ignored[IgnoreGrandparentDto::class . '::__flags']);
        $this->assertFalse($ignored[IgnoreGrandchildDto::class . '::secret']);
        $this->assertArrayNotHasKey(IgnoreGrandparentDto::class . '::secret', $ignored);
    }

    public function testSecondBindOfSameClassDoesNotRewalkGroups(): void
    {
        $first = new FieldSchemaCache();
        new FieldSchema(ProfileDto::class, new NestedTypeResolver(), $first);
        $this->assertNotSame([], $this->groupsByPropertyOf($first));
        $this->assertEqualsCanonicalizing(
            [
                ProfileDto::class,
                EducationDto::class,
                WorkplaceDto::class,
                LocationDto::class,
                \DateTimeImmutable::class,
            ],
            array_keys($this->validatedGroupClasses())
        );

        $second = new FieldSchemaCache();
        new FieldSchema(ProfileDto::class, new NestedTypeResolver(), $second);
        new FieldSchema(EducationDto::class, new NestedTypeResolver(), $second);
        $this->assertSame([], $this->groupsByPropertyOf($second));

        $schema = new FieldSchema(ProfileDto::class, new NestedTypeResolver(), $second);
        $this->assertSame(['_basicInfo'], $schema->getGroups('id'));
    }

    public function testInvalidGroupsThrowOnEveryBind(): void
    {
        foreach ([InvalidGroupNameDto::class, NestedInvalidGroupDto::class] as $class) {
            for ($i = 0; $i < 3; $i++) {
                try {
                    new FieldSchema($class);
                    $this->fail("Bind #$i of $class did not throw");
                } catch (\InvalidArgumentException $e) {
                    $this->assertSame('Group name "basicInfo" must start with an underscore', $e->getMessage());
                }
            }
        }
        $this->assertSame([], $this->validatedGroupClasses());
    }

    public function testCycleWithInvalidMemberMemoizesNothing(): void
    {
        try {
            new FieldSchema(GroupMemoCycleInvalidDto::class);
            $this->fail('Invalid cycle member did not throw');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Group name "bad" must start with an underscore', $e->getMessage());
        }
        $this->assertSame([], $this->validatedGroupClasses());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Group name "bad" must start with an underscore');
        new FieldSchema(GroupMemoCyclePeerDto::class);
    }

    public function testValidCycleIsWalkedOnceAndMemoized(): void
    {
        $schema = new FieldSchema(GroupMemoSelfCycleDto::class);

        $this->assertSame([GroupMemoSelfCycleDto::class], array_keys($this->validatedGroupClasses()));
        $this->assertSame(['_basicInfo'], $schema->getGroups('parent.children.name'));
    }

    public function testIgnoredPropertiesAreNotFields(): void
    {
        $schema = new FieldSchema(IgnoreDto::class);
        $this->assertSame(['id', 'name', '__legacy'], $schema->getFieldNames());
        $ignored = ['internal', 'ignoredDefault', 'ignoredInvalidGroup', 'ignoredInvalidChild', 'parentInternal'];
        foreach ($ignored as $field) {
            $this->assertFalse($schema->hasField($field), $field);
            $this->assertFalse($schema->isDefaultField($field), $field);
            $this->assertSame([], $schema->getGroups($field), $field);
        }

        $options = $this->options([], IgnoreDto::class);
        $this->assertSame(['id'], $options->getIncludedFields());
        $this->assertFalse($options->isFieldIncluded('ignoredDefault'));
        $this->assertFalse($options->isFieldIncluded('parentInternal'));

        $all = $this->options(['_all' => true], IgnoreDto::class);
        $this->assertSame(['id', 'name', '__legacy'], $all->getIncludedFields());
        $this->assertFalse($all->isFieldIncluded('internal'));
        $this->assertFalse($all->isFieldIncluded('ignoredInvalidChild'));
        $this->assertFalse($all->isFieldIncluded('parentInternal'));
    }

    public function testIgnoreWinsOverInclusionAttributes(): void
    {
        $this->assertFalse(FieldSchema::classDeclaresInclusionAttributes(IgnoreOnlyAttributesDto::class));
        $this->assertTrue(FieldSchema::classDeclaresInclusionAttributes(IgnoreDto::class));
        $this->assertSame(
            [],
            $this->options(['_basicInfo' => true], IgnoreOnlyAttributesDto::class)->getIncludedFields()
        );
    }

    public function testBindDoesNotWalkIgnoredProperties(): void
    {
        new FieldSchema(IgnoreDto::class);

        $this->assertArrayNotHasKey(InvalidGroupNameDto::class, $this->validatedGroupClasses());
        $this->expectException(\InvalidArgumentException::class);
        new FieldSchema(InvalidGroupNameDto::class);
    }

    public function testRedeclaredIgnoredPropertyIsNotAField(): void
    {
        $schema = new FieldSchema(IgnoreRedeclaringDto::class);
        $this->assertSame(['id'], $schema->getFieldNames());
        $this->assertFalse($schema->hasField('parentInternal'));
        $this->assertFalse($schema->hasField('bookkeeping'));
        $this->assertFalse($schema->isDefaultField('parentInternal'));

        $this->assertSame(['id'], $this->options([], IgnoreRedeclaringDto::class)->getIncludedFields());
        $all = $this->options(['_all' => true], IgnoreRedeclaringDto::class);
        $this->assertSame(['id'], $all->getIncludedFields());
        $this->assertFalse($all->isFieldIncluded('parentInternal'));
        $this->assertFalse($all->isFieldIncluded('bookkeeping'));
    }

    public function testIgnoreSurvivesMultiLevelRedeclaration(): void
    {
        $this->assertEqualsCanonicalizing(
            ['id', 'secret'],
            (new FieldSchema(IgnoreGrandchildDto::class))->getFieldNames()
        );

        $all = $this->options(['_all' => true], IgnoreGrandchildDto::class);
        $this->assertEqualsCanonicalizing(['id', 'secret'], $all->getIncludedFields());
        $this->assertFalse($all->isFieldIncluded('__state'));
        $this->assertFalse($all->isFieldIncluded('__flags'));
    }

    public function testPrivateParentIgnoreDoesNotHideChildProperty(): void
    {
        $schema = new FieldSchema(IgnorePrivateChildDto::class);
        $this->assertSame(['id', 'secret'], $schema->getFieldNames());
        $this->assertTrue($schema->hasField('secret'));
        $this->assertSame(
            ['id', 'secret'],
            $this->options(['_all' => true], IgnorePrivateChildDto::class)->getIncludedFields()
        );
    }

    public function testTraitIgnoreIsHonored(): void
    {
        $classes = [IgnoreTraitUserDto::class, IgnoreTraitRedeclaringDto::class, IgnoreTraitUserChildDto::class];
        foreach ($classes as $class) {
            $schema = new FieldSchema($class);
            $this->assertEqualsCanonicalizing(['id', 'traitField'], $schema->getFieldNames(), $class);
            $this->assertFalse($schema->hasField('__traitState'), $class);
            $this->assertEqualsCanonicalizing(
                ['id', 'traitField'],
                $this->options(['_all' => true], $class)->getIncludedFields(),
                $class
            );
        }
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('legacyExportedPropertiesRedeclarationProvider')]
    public function testLegacyExportedPropertiesRedeclarationIsNotAField(string $class): void
    {
        $schema = new FieldSchema($class);
        $this->assertNotContains('__exportedProperties', $schema->getFieldNames());
        $this->assertNotContains('__excludedProperties', $schema->getFieldNames());
        $this->assertFalse($schema->hasField('__exportedProperties'));
        $this->assertNotContains(
            '__exportedProperties',
            $this->options(['_all' => true], $class)->getIncludedFields()
        );
        $this->assertFalse($this->options(['_all' => true], $class)->isFieldIncluded('__exportedProperties'));
    }

    public static function legacyExportedPropertiesRedeclarationProvider(): array
    {
        return [
            [PlainProfileDto::class],
            [PlainWorkplaceDto::class],
            [ProfileDto::class],
            [WorkplaceDto::class],
        ];
    }

    public function testDoubleUnderscorePropertyWithoutIgnoreIsAField(): void
    {
        $options = $this->options(['__legacy' => true], IgnoreDto::class);

        $this->assertTrue($options->isFieldIncluded('__legacy'));
        $this->assertSame(['__legacy'], $options->getIncludedFields());
        $this->assertContains('__legacy', (new FieldSchema(IgnoreDto::class))->getFieldNames());
    }

    public function testSeparateFieldSchemaInstancesDoNotShareCaches(): void
    {
        $a = new FieldSchema(ProfileDto::class);
        $b = new FieldSchema(ProfileDto::class);

        $this->assertNotSame($this->fieldSchemaCacheOf($a), $this->fieldSchemaCacheOf($b));
        $this->assertNotSame($this->fieldSchemaResolverOf($a), $this->fieldSchemaResolverOf($b));
    }

    public function testEmptyRequestIncludesDefaultFieldsOnly(): void
    {
        $options = $this->options([], ProfileDto::class);

        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertTrue($options->isFieldIncluded('name'));
        $this->assertFalse($options->isFieldIncluded('description'));
        $this->assertFalse($options->isFieldIncluded('education'));
        $this->assertEquals(['id', 'name'], $options->getIncludedFields());
    }

    public function testDefaultsFalseExcludesDefaultFieldsUnlessExplicit(): void
    {
        $options = $this->options(['_defaults' => false], ProfileDto::class);
        $this->assertFalse($options->isFieldIncluded('id'));
        $this->assertFalse($options->isFieldIncluded('name'));

        $options = $this->options(
            ['_defaults' => false, 'name' => true],
            ProfileDto::class
        );
        $this->assertFalse($options->isFieldIncluded('id'));
        $this->assertTrue($options->isFieldIncluded('name'));
    }

    public function testFieldListTurnsDefaultsOff(): void
    {
        $options = $this->options(['id' => true], ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertFalse($options->isFieldIncluded('name'));
        $this->assertFalse($options->isFieldIncluded('description'));
    }

    public function testAllIncludesUnmarkedFieldsAndHonorsExclude(): void
    {
        $options = $this->options(
            ['_all' => true, 'description' => false],
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
        $options = $this->options(
            ['_all' => true, '_defaults' => false],
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('description'));
        $this->assertTrue($options->isFieldIncluded('id'));
    }

    public function testCustomGroupsAreAdditive(): void
    {
        $options = $this->options(['_basicInfo' => true], ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertTrue($options->isFieldIncluded('name'));
        $this->assertFalse($options->isFieldIncluded('description'));

        $options = $this->options(
            ['_extended' => true, '_defaults' => false],
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('description'));
        $this->assertFalse($options->isFieldIncluded('id'));

        $options = $this->options(
            ['_extended' => false, '_defaults' => true],
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertFalse($options->isFieldIncluded('description'));
    }

    public function testRepeatableGroupsEitherGroupIsEnough(): void
    {
        $options = $this->options(['_basicInfo' => true], ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('id'));
    }

    public function testParentFalseExcludesDescendants(): void
    {
        $options = $this->options(
            ['education' => false],
            ProfileDto::class
        );
        $this->assertFalse($options->isFieldIncluded('education'));
        $this->assertFalse($options->isFieldIncluded('education.institutionName'));
    }

    public function testUnderscorePropertyUsesSchemaLogic(): void
    {
        $options = $this->options(['_all' => true], ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('_text'));
        $this->assertTrue($options->isFieldExplicitlyIncluded('_all'));
        $this->assertFalse($options->isFieldIncluded('_basicInfo'));
    }

    public function testOptionOnlyFieldIsIncluded(): void
    {
        $options = $this->options(
            ['education' => ['_opt' => ['limit' => 1]]],
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
        $options = $this->options(['education' => true], ProfileDto::class);
        $this->assertTrue($options->isFieldIncluded('education'));
        $this->assertTrue($options->isFieldIncluded('education.institutionName'));
        $this->assertTrue($options->isFieldIncluded('education.institutionId'));
        $this->assertEquals(['institutionId', 'institutionName'], $options->getIncludedFields('education'));

        $child = $options->forField('education');
        $this->assertTrue($child->isFieldIncluded('institutionName'));
        $this->assertFalse($child->isFieldIncluded('missing'));
    }

    public function testValidatorRejectsUnknownFieldPaths(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid field path 'education.nosuchfield'");

        new FieldsOptions(
            ['education' => ['nosuchfield' => true]],
            new Validator(ProfileDto::class)
        );
    }

    public function testNestedObjectTrueOnRootProfile(): void
    {
        $options = $this->options(['profile' => true], RootDto::class);
        $this->assertTrue($options->isFieldIncluded('profile'));
        $this->assertTrue($options->isFieldIncluded('profile.id'));
        $this->assertTrue($options->isFieldIncluded('profile.name'));
        $this->assertFalse($options->isFieldIncluded('profile.education'));
        $this->assertFalse($options->isFieldIncluded('seo'));
        $this->assertEquals(['id', 'name'], $options->getIncludedFields('profile'));
    }

    public function testNestedFieldListDisablesChildDefaults(): void
    {
        $options = $this->options(
            ['education' => ['institutionName' => true]],
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('education.institutionName'));
        $this->assertFalse($options->isFieldIncluded('education.institutionId'));
    }

    public function testLocationTypedAndUntypedWithVar(): void
    {
        $options = $this->options(
            ['location' => true, 'location2' => true],
            ProfileDto::class
        );
        $this->assertTrue($options->isFieldIncluded('location.city'));
        $this->assertTrue($options->isFieldIncluded('location2.city'));
    }

    public function testUnresolvedNestedTypeFallsBackToExplicit(): void
    {
        $options = $this->options(
            ['mystery' => ['foo' => true]],
            MixedDto::class
        );
        $this->assertTrue($options->isFieldIncluded('mystery'));
        $this->assertTrue($options->isFieldIncluded('mystery.foo'));
        $this->assertFalse($options->isFieldIncluded('mystery.bar'));
        $this->assertFalse($options->isFieldIncluded('id'));

        $options = $this->options(
            ['_defaults' => true, 'mystery' => ['foo' => true]],
            MixedDto::class
        );
        $this->assertTrue($options->isFieldIncluded('id'));
    }

    public function testUntypedCollectionNestedPathsStayExplicit(): void
    {
        $options = $this->options(
            ['_all' => true, 'untypedArray' => ['foo' => true]],
            TypeResolutionDto::class
        );
        $this->assertTrue($options->isFieldIncluded('untypedArray'));
        $this->assertTrue($options->isFieldIncluded('untypedArray.foo'));
        $this->assertFalse($options->isFieldIncluded('untypedArray.bar'));
    }

    public function testDateCreatedChildrenStayExplicit(): void
    {
        $options = $this->options(['dateCreated' => true], ProfileDto::class);
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

    public function testCustomValidatorInterfaceSuppliesPrototype(): void
    {
        $validator = new CustomValidator(ProfileDto::class);

        $options = new FieldsOptions([], $validator);
        $this->assertEquals(['id', 'name'], $options->getIncludedFields());

        $built = (new FieldsOptionsBuilder($validator))->build();
        $this->assertEquals(['id', 'name'], $built->getIncludedFields());
    }

    public function testCustomValidatorNullPrototypeStaysExplicitOnly(): void
    {
        $options = new FieldsOptions(['id' => true], new CustomValidator(null));
        $this->assertEquals(['id'], $options->getIncludedFields());
        $this->assertFalse($options->isFieldIncluded('name'));
    }

    public function testGroupAttributeRejectsReservedNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Group name "_defaults" is reserved');
        new Group('_defaults');
    }

    public function testGroupAttributeRejectsNameWithoutUnderscore(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Group name "basicInfo" must start with an underscore');
        new Group('basicInfo');
    }

    public function testGroupAttributeRejectsOptionsKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Group name "_opt" is reserved');
        new Group('_opt');
    }

    public function testInvalidGroupAttributeThrowsWhenPrototypeIsBound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Group name "basicInfo" must start with an underscore');
        $this->options([], InvalidGroupNameDto::class);
    }

    public function testReservedGroupAttributeThrowsWhenPrototypeIsBound(): void
    {
        $class = (new class {
            #[Group('_all')]
            public string $name = 'x';
        })::class;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Group name "_all" is reserved');
        new FieldSchema($class);
    }

    public function testInvalidNestedGroupAttributeThrowsWhenPrototypeIsBound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Group name "basicInfo" must start with an underscore');
        $this->options([], NestedInvalidGroupDto::class);
    }

    public function testWithPrototypeRejectsInvalidGroupAttribute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Group name "basicInfo" must start with an underscore');
        (new FieldsOptions([]))->withPrototype(InvalidGroupNameDto::class);
    }

    public function testHasGroupFieldStillUsesExplicitCheck(): void
    {
        $options = $this->options(['id' => true], ProfileDto::class);
        $this->assertFalse($options->hasDefaultFields());
        $this->assertFalse($options->hasAllFields());
        $this->assertFalse($options->isFieldExplicitlyIncluded('name'));
        $this->assertFalse($options->isFieldSpecified('name'));
        $this->assertTrue($options->isFieldSpecified('id'));
    }

    public function testUnresolvableValidatorPrototypeDoesNotAcceptArbitraryPaths(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unresolvable prototype class 'App\\Dto\\Typo'");
        new FieldsOptions(['whatever' => true], new Validator('App\\Dto\\Typo'));
    }

    public function testWithPrototypeUnresolvableClassThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unresolvable prototype class 'App\\Dto\\Typo'");
        (new FieldsOptions([]))->withPrototype('App\\Dto\\Typo');
    }

    public function testFromPrototypeUnresolvableClassThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unresolvable prototype class 'App\\Dto\\Typo'");
        FieldSchema::fromPrototype('App\\Dto\\Typo');
    }

    public function testForFieldReusesTypeResolver(): void
    {
        $options = $this->options(['education' => true], ProfileDto::class);
        $child = $options->forField('education');

        $this->assertSame($this->typeResolverOf($options), $this->typeResolverOf($child));
        $this->assertSame($this->schemaCacheOf($options), $this->schemaCacheOf($child));
        $this->assertSame($this->schemaResolverOf($options), $this->schemaResolverOf($child));
        $this->assertSame($this->schemaFieldCacheOf($options), $this->schemaFieldCacheOf($child));
        $this->assertTrue($child->isFieldIncluded('institutionName'));

        $scalarChild = $options->forField('id');
        $this->assertSame($this->typeResolverOf($options), $this->typeResolverOf($scalarChild));
        $this->assertSame($this->schemaCacheOf($options), $this->schemaCacheOf($scalarChild));
        $this->assertNull(
            (new \ReflectionProperty(FieldsOptions::class, 'schema'))->getValue($scalarChild)
        );
    }

    public function testWithPrototypeReusesTypeResolverWhenAlreadyBound(): void
    {
        $options = $this->options([], ProfileDto::class);
        $bound = $options->withPrototype(new ProfileDto());

        $this->assertSame($this->typeResolverOf($options), $this->typeResolverOf($bound));
        $this->assertSame($this->schemaCacheOf($options), $this->schemaCacheOf($bound));
        $this->assertSame($this->schemaResolverOf($options), $this->schemaResolverOf($bound));
        $this->assertSame($this->schemaFieldCacheOf($options), $this->schemaFieldCacheOf($bound));
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertTrue($bound->isFieldIncluded('id'));
        $this->assertSame($options->toArray(), $bound->toArray());
    }

    public function testRepeatedWithPrototypeFromDataOnlyReusesTypeResolver(): void
    {
        $options = new FieldsOptions(['education' => true]);
        $this->assertNull(
            (new \ReflectionProperty(FieldsOptions::class, 'typeResolver'))->getValue($options)
        );

        $first = $options->withPrototype(ProfileDto::class);
        $second = $options->withPrototype(ProfileDto::class);
        $child = $first->forField('education');
        $item = $child->withPrototype(new EducationDto());

        $this->assertSame($this->typeResolverOf($options), $this->typeResolverOf($first));
        $this->assertSame($this->schemaCacheOf($options), $this->schemaCacheOf($first));
        $this->assertSame($this->typeResolverOf($first), $this->typeResolverOf($second));
        $this->assertSame($this->schemaCacheOf($first), $this->schemaCacheOf($second));
        $this->assertSame($this->typeResolverOf($first), $this->typeResolverOf($child));
        $this->assertSame($this->schemaCacheOf($first), $this->schemaCacheOf($child));
        $this->assertSame($this->typeResolverOf($first), $this->typeResolverOf($item));
        $this->assertSame($this->schemaCacheOf($first), $this->schemaCacheOf($item));
        $this->assertSame($this->schemaFieldCacheOf($first), $this->schemaFieldCacheOf($item));
        $this->assertFalse($options->isFieldIncluded('id'));
        $this->assertTrue($first->isFieldIncluded('education'));
        $this->assertTrue($item->isFieldIncluded('institutionName'));
    }

    public function testFromPrototypeReusesProvidedResolver(): void
    {
        $resolver = new NestedTypeResolver();
        $cache = new FieldSchemaCache();
        $schema = FieldSchema::fromPrototype(ProfileDto::class, $resolver, $cache);
        $this->assertInstanceOf(FieldSchema::class, $schema);
        $this->assertSame($resolver, $this->fieldSchemaResolverOf($schema));
        $this->assertSame($cache, $this->fieldSchemaCacheOf($schema));
        $this->assertSame(EducationDto::class, $schema->resolveClass('education'));
    }

    public function testSeparateFieldsOptionsInstancesDoNotShareTypeResolver(): void
    {
        $a = $this->options([], ProfileDto::class);
        $b = $this->options([], ProfileDto::class);

        $this->assertNotSame($this->typeResolverOf($a), $this->typeResolverOf($b));
        $this->assertNotSame($this->schemaCacheOf($a), $this->schemaCacheOf($b));
        $this->assertNotSame($this->schemaFieldCacheOf($a), $this->schemaFieldCacheOf($b));
    }

    private function options(array $data, string $prototypeClass): FieldsOptions
    {
        return new FieldsOptions($data, new Validator($prototypeClass));
    }

    private function typeResolverOf(FieldsOptions $options): NestedTypeResolver
    {
        $resolver = (new \ReflectionProperty(FieldsOptions::class, 'typeResolver'))->getValue($options);
        $this->assertInstanceOf(NestedTypeResolver::class, $resolver);

        return $resolver;
    }

    private function schemaResolverOf(FieldsOptions $options): NestedTypeResolver
    {
        $schema = (new \ReflectionProperty(FieldsOptions::class, 'schema'))->getValue($options);
        $this->assertInstanceOf(FieldSchema::class, $schema);

        return $this->fieldSchemaResolverOf($schema);
    }

    private function fieldSchemaResolverOf(FieldSchema $schema): NestedTypeResolver
    {
        $resolver = (new \ReflectionProperty(FieldSchema::class, 'resolver'))->getValue($schema);
        $this->assertInstanceOf(NestedTypeResolver::class, $resolver);

        return $resolver;
    }

    private function schemaCacheOf(FieldsOptions $options): FieldSchemaCache
    {
        $cache = (new \ReflectionProperty(FieldsOptions::class, 'schemaCache'))->getValue($options);
        $this->assertInstanceOf(FieldSchemaCache::class, $cache);

        return $cache;
    }

    private function schemaFieldCacheOf(FieldsOptions $options): FieldSchemaCache
    {
        $schema = (new \ReflectionProperty(FieldsOptions::class, 'schema'))->getValue($options);
        $this->assertInstanceOf(FieldSchema::class, $schema);

        return $this->fieldSchemaCacheOf($schema);
    }

    private function fieldSchemaCacheOf(FieldSchema $schema): FieldSchemaCache
    {
        $cache = (new \ReflectionProperty(FieldSchema::class, 'cache'))->getValue($schema);
        $this->assertInstanceOf(FieldSchemaCache::class, $cache);

        return $cache;
    }

    private function groupsByPropertyOf(FieldSchemaCache $cache): array
    {
        $groups = (new \ReflectionProperty(FieldSchemaCache::class, 'groupsByProperty'))->getValue($cache);
        $this->assertIsArray($groups);

        return $groups;
    }

    /**
     * @return array<class-string, true>
     */
    private function validatedGroupClasses(): array
    {
        /** @var array<class-string, true> */
        return (new \ReflectionProperty(FieldSchema::class, 'validatedGroupClasses'))->getValue();
    }

    private function resetValidatedGroupClasses(): void
    {
        (new \ReflectionProperty(FieldSchema::class, 'validatedGroupClasses'))->setValue(null, []);
    }

    /**
     * @param class-string $class
     * @return list<string>
     */
    private function staticPropertyNames(string $class): array
    {
        return array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_STATIC)
        );
    }
}
