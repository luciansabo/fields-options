<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\NestedTypeResolver;
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

    public function testResolveClassRoot(): void
    {
        $this->assertSame(ProfileDto::class, $this->resolver->resolveClass(ProfileDto::class));
        $this->assertSame(ProfileDto::class, $this->resolver->resolveClass(ProfileDto::class, ''));
    }
}
