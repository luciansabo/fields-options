<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\NestedTypeResolver;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreGrandchildDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnorePrivateChildDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnorePrivateParentDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreRedeclaringDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreTraitRedeclaringDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreTraitUserChildDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreRedeclaringTraitUserDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\IgnoreTraitUserDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\StaticPropertyDto;
use Lucian\FieldsOptions\Test\Fixture\EducationDto;
use Lucian\FieldsOptions\Test\Fixture\LocationDto;
use Lucian\FieldsOptions\Test\Fixture\ProfileDto;
use Lucian\FieldsOptions\Test\Fixture\TypeResolutionDto;
use Lucian\FieldsOptions\Test\Fixture\WorkplaceDto;
use PHPUnit\Framework\TestCase;

class NestedTypeResolverTest extends TestCase
{
    private NestedTypeResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new NestedTypeResolver();
    }

    public function testResolveTypedObject(): void
    {
        $this->assertSame(
            LocationDto::class,
            $this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'location')
        );
        $this->assertSame(
            LocationDto::class,
            $this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'nullableLocation')
        );
    }

    public function testResolvePhpDocArrayOfObjects(): void
    {
        $this->assertSame(
            EducationDto::class,
            $this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'educationList')
        );
    }

    public function testResolveIterableGeneric(): void
    {
        $this->assertSame(
            WorkplaceDto::class,
            $this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'workHistory')
        );
    }

    public function testResolveLegacyIterableUnionPhpDoc(): void
    {
        $this->assertSame(
            EducationDto::class,
            $this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'educationUnion')
        );
    }

    public function testResolveUntypedPropertyWithVarTag(): void
    {
        $this->assertSame(
            LocationDto::class,
            $this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'untypedLocation')
        );
    }

    public function testUnresolvedMixedAndUnions(): void
    {
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'mystery'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'untypedNoDoc'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'untypedObject'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'untypedArray'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'nullableUntypedArray'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'untypedIterable'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'intList'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'scalarUnion'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'objectUnion'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'objectIntersection'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'nativeIntersection'));
        $this->assertNull($this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'id'));
        $this->assertSame(
            LocationDto::class,
            $this->resolver->resolvePropertyClass(TypeResolutionDto::class, 'objectOrString')
        );
    }

    public function testResolveNestedPathOnProfileDtoWithoutInstance(): void
    {
        $this->assertSame(
            EducationDto::class,
            $this->resolver->resolveClass(ProfileDto::class, 'education')
        );
        $this->assertNull($this->resolver->resolveClass(ProfileDto::class, 'education.institutionName'));
        $this->assertTrue($this->resolver->pathExists(ProfileDto::class, 'education.institutionName'));
        $this->assertFalse($this->resolver->pathExists(ProfileDto::class, 'education.missing'));
        $this->assertTrue($this->resolver->pathExists(ProfileDto::class, 'location.city'));
        $this->assertTrue($this->resolver->pathExists(ProfileDto::class, 'location2.city'));
    }

    public function testUnresolvedNestedPathIsPermissive(): void
    {
        $this->assertTrue($this->resolver->pathExists(TypeResolutionDto::class, 'mystery.foo'));
        $this->assertTrue($this->resolver->pathExists(TypeResolutionDto::class, 'untypedNoDoc.foo'));
        $this->assertTrue($this->resolver->pathExists(TypeResolutionDto::class, 'untypedObject.foo'));
        $this->assertTrue($this->resolver->pathExists(TypeResolutionDto::class, 'untypedArray.foo'));
        $this->assertTrue($this->resolver->pathExists(TypeResolutionDto::class, 'nullableUntypedArray.foo'));
        $this->assertTrue($this->resolver->pathExists(TypeResolutionDto::class, 'untypedIterable.foo'));
        $this->assertTrue($this->resolver->pathExists(TypeResolutionDto::class, 'objectUnion.foo'));
        $this->assertTrue($this->resolver->pathExists(TypeResolutionDto::class, 'objectIntersection.foo'));
        $this->assertTrue($this->resolver->pathExists(TypeResolutionDto::class, 'nativeIntersection.foo'));
        $this->assertFalse($this->resolver->pathExists(TypeResolutionDto::class, 'missing.foo'));
        $this->assertFalse($this->resolver->pathExists(TypeResolutionDto::class, 'id.foo'));
        $this->assertFalse($this->resolver->pathExists(TypeResolutionDto::class, 'scalarUnion.foo'));
        $this->assertFalse($this->resolver->pathExists(TypeResolutionDto::class, 'intList.foo'));
        $this->assertFalse($this->resolver->pathExists(TypeResolutionDto::class, 'objectOrString.foo'));
        $this->assertFalse($this->resolver->pathExists(ProfileDto::class, 'location2.cityId.missing'));
    }

    public function testHasPropertyIgnoresStatic(): void
    {
        $this->assertTrue($this->resolver->hasProperty(StaticPropertyDto::class, 'id'));
        $this->assertFalse($this->resolver->hasProperty(StaticPropertyDto::class, 'secret'));
        $this->assertFalse($this->resolver->pathExists(StaticPropertyDto::class, 'secret'));
    }

    public function testHasPropertyIgnoresIgnoreAttributeIncludingInherited(): void
    {
        $this->assertTrue($this->resolver->hasProperty(IgnoreDto::class, 'id'));
        $this->assertFalse($this->resolver->hasProperty(IgnoreDto::class, 'internal'));
        $this->assertFalse($this->resolver->hasProperty(IgnoreDto::class, 'parentInternal'));
        $this->assertFalse($this->resolver->hasProperty(IgnoreDto::class, 'ignoredDefault'));
        $this->assertNull($this->resolver->resolvePropertyClass(IgnoreDto::class, 'ignoredInvalidChild'));
        $this->assertNull($this->resolver->resolveClass(IgnoreDto::class, 'ignoredInvalidChild'));
        $this->assertFalse($this->resolver->pathExists(IgnoreDto::class, 'ignoredInvalidChild.name'));
        $this->assertFalse($this->resolver->hasProperty(EducationDto::class, '__excludedProperties'));
    }

    public function testIgnoreIsInheritedThroughRedeclaration(): void
    {
        $this->assertFalse($this->resolver->hasProperty(IgnoreRedeclaringDto::class, 'parentInternal'));
        $this->assertFalse($this->resolver->hasProperty(IgnoreRedeclaringDto::class, 'bookkeeping'));
        $this->assertFalse($this->resolver->hasProperty(IgnoreGrandchildDto::class, '__state'));
        $this->assertFalse($this->resolver->hasProperty(IgnoreGrandchildDto::class, '__flags'));
        $this->assertFalse($this->resolver->hasProperty(ProfileDto::class, '__exportedProperties'));
        $this->assertFalse($this->resolver->hasProperty(WorkplaceDto::class, '__exportedProperties'));
        $this->assertFalse($this->resolver->pathExists(ProfileDto::class, 'education.__exportedProperties'));
        $this->assertFalse(NestedTypeResolver::isFieldProperty(
            new \ReflectionProperty(IgnoreRedeclaringDto::class, 'parentInternal')
        ));
    }

    public function testPrivateParentIgnoreIsADistinctProperty(): void
    {
        $this->assertTrue($this->resolver->hasProperty(IgnorePrivateChildDto::class, 'secret'));
        $this->assertTrue($this->resolver->hasProperty(IgnoreGrandchildDto::class, 'secret'));
        $this->assertFalse(NestedTypeResolver::isFieldProperty(
            new \ReflectionProperty(IgnorePrivateParentDto::class, 'secret')
        ));
    }

    public function testTraitIgnoreIsHonored(): void
    {
        $classes = [
            IgnoreTraitUserDto::class,
            IgnoreTraitRedeclaringDto::class,
            IgnoreTraitUserChildDto::class,
            IgnoreRedeclaringTraitUserDto::class,
        ];
        foreach ($classes as $class) {
            $this->assertFalse($this->resolver->hasProperty($class, '__traitState'), $class);
            $this->assertTrue($this->resolver->hasProperty($class, 'traitField'), $class);
        }
        $this->assertSame(
            IgnoreTraitRedeclaringDto::class,
            (new \ReflectionProperty(IgnoreTraitRedeclaringDto::class, '__traitState'))->class
        );
        $this->assertSame(
            [],
            (new \ReflectionProperty(IgnoreTraitRedeclaringDto::class, '__traitState'))->getAttributes()
        );
    }

    public function testDoubleUnderscorePropertyWithoutIgnoreIsAProperty(): void
    {
        $this->assertTrue($this->resolver->hasProperty(IgnoreDto::class, '__legacy'));
        $this->assertTrue(
            NestedTypeResolver::isFieldProperty(new \ReflectionProperty(IgnoreDto::class, '__legacy'))
        );
    }

    public function testResolveClassRoot(): void
    {
        $this->assertSame(ProfileDto::class, $this->resolver->resolveClass(ProfileDto::class));
        $this->assertSame(ProfileDto::class, $this->resolver->resolveClass(ProfileDto::class, ''));
    }
}
