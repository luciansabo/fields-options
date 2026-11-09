<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\FieldsOptions;
use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\FieldsOptionsObjectApplier;
use Lucian\FieldsOptions\Test\Fixture\AbstractDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\EducationDto as AttributedEducationDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\ProfileDto as AttributedProfileDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\StaticPropertyDto;
use Lucian\FieldsOptions\Test\Fixture\AttributedFieldFilterDto;
use Lucian\FieldsOptions\Test\Fixture\ProfileDto;
use Lucian\FieldsOptions\Test\Fixture\RecordingExportApplier;
use Lucian\FieldsOptions\Test\Fixture\SampleExportApplier;
use Lucian\FieldsOptions\Validator;
use PHPUnit\Framework\TestCase;

class FieldsOptionsObjectApplierAttributedTest extends TestCase
{
    private FieldsOptionsObjectApplier $applier;

    public function setUp(): void
    {
        $this->applier = new FieldsOptionsObjectApplier(new SampleExportApplier());
    }

    public function testClassAttributeCacheIsPerApplierAndCapped(): void
    {
        $other = new FieldsOptionsObjectApplier(new SampleExportApplier());
        $max = (new \ReflectionClass(FieldsOptionsObjectApplier::class))
            ->getConstant('MAX_REFLECTION_CACHE_SIZE');
        $this->assertIsInt($max);

        $this->assertSame([], $this->classAttributeCacheOf($other));

        $prefix = 'FoAttrCache' . str_replace('.', '', uniqid('', true));
        for ($i = 0; $i < $max + 5; $i++) {
            $class = $prefix . $i;
            // Distinct classes: `new class` in a loop reuses one anonymous class.
            eval("class {$class} { public int \$id = 1; }");
            $this->applier->apply(new $class(), new FieldsOptions(['id' => true]));
        }

        $cache = $this->classAttributeCacheOf($this->applier);
        $this->assertCount($max, $cache);
        $this->assertSame([], $this->classAttributeCacheOf($other));

        $dto = new StaticPropertyDto();
        $this->applier->apply($dto, new FieldsOptions([]));
        $this->assertSame(['id' => 1], $dto->jsonSerialize());
        $this->assertCount($max, $this->classAttributeCacheOf($this->applier));
        $this->assertTrue(
            $this->classAttributeCacheOf($this->applier)[StaticPropertyDto::class] ?? false
        );
    }

    public function testApplySkipsStaticPropertiesEvenWhenDefaultOrExplicit(): void
    {
        $dto = new StaticPropertyDto();
        $this->applier->apply($dto, new FieldsOptions([]));
        $this->assertSame(['id' => 1], $dto->jsonSerialize());
        $this->assertSame(['id' => true], $this->exportedPropertyMap($dto));

        $dto = new StaticPropertyDto();
        $this->applier->apply($dto, new FieldsOptions(['secret' => true]));
        $this->assertNull($dto->jsonSerialize());
        $this->assertSame([], $this->exportedPropertyMap($dto));
    }

    public function testApplyOneProperty(): void
    {
        $dto = AttributedProfileDto::getSampleDto();
        $fieldsOptions = $this->builder($dto)
            ->setFieldIncluded('id')
            ->build();

        $this->applier->apply($dto, $fieldsOptions);

        $this->assertEquals(['id' => 1], $dto->jsonSerialize());
    }

    public function testParityWithUnannotatedOneProperty(): void
    {
        $this->assertSame(
            $this->applyUnannotated(fn (FieldsOptionsBuilder $b) => $b->setFieldIncluded('id')),
            $this->applyAttributed(fn (FieldsOptionsBuilder $b) => $b->setFieldIncluded('id'))
        );
    }

    public function testParityWithUnannotatedNested(): void
    {
        $configure = static function (FieldsOptionsBuilder $builder): FieldsOptionsBuilder {
            return $builder
                ->setFieldIncluded('id')
                ->setFieldIncluded('education', ['institutionName']);
        };

        $this->assertSame($this->applyUnannotated($configure), $this->applyAttributed($configure));
    }

    public function testParityWithUnannotatedAllExceptOne(): void
    {
        $configure = static function (FieldsOptionsBuilder $builder): FieldsOptionsBuilder {
            return $builder
                ->setAllFieldsIncluded()
                ->setAllFieldsIncluded('education')
                ->setFieldExcluded('education', ['institutionName'])
                ->setDefaultFieldsIncluded('workHistory')
                ->setFieldIncluded('workHistory', ['startYear'])
                ->setFieldExcluded('workHistory', ['employerName']);
        };

        $this->assertSame($this->applyUnannotated($configure), $this->applyAttributed($configure));
    }

    public function testParityWithUnannotatedDefaultsPlusExtras(): void
    {
        $configure = static function (FieldsOptionsBuilder $builder): FieldsOptionsBuilder {
            return $builder
                ->setDefaultFieldsIncluded()
                ->setFieldExcluded(null, ['name', 'education'])
                ->setDefaultFieldsIncluded('workHistory')
                ->setFieldIncluded('workHistory', ['startYear'])
                ->setFieldExcluded('workHistory', ['employerName'])
                ->setFieldIncluded(null, ['dateCreated', 'location2.cityId']);
        };

        $this->assertSame($this->applyUnannotated($configure), $this->applyAttributed($configure));
    }

    public function testApplyDoesNotReadUninitializedTypedProperties(): void
    {
        $dto = new AttributedProfileDto();
        $dto->name = 'Jane';

        $this->applier->apply($dto, new FieldsOptions([]));

        $this->assertSame(['name' => 'Jane'], $dto->jsonSerialize());

        $dto->id = 5;
        $this->assertSame(['id' => 5, 'name' => 'Jane'], $dto->jsonSerialize());
    }

    public function testApplyDefaultsRequestDoesNotReadUninitializedTypedProperties(): void
    {
        $dto = new AttributedProfileDto();
        $dto->name = 'Jane';
        $fieldsOptions = $this->builder($dto)
            ->setDefaultFieldsIncluded()
            ->build();

        $this->applier->apply($dto, $fieldsOptions);

        $this->assertSame(['name' => 'Jane'], $dto->jsonSerialize());

        $dto->id = 5;
        $this->assertSame(['id' => 5, 'name' => 'Jane'], $dto->jsonSerialize());
    }

    public function testApplyDoesNotReadUninitializedNestedTypedProperties(): void
    {
        $dto = new AttributedProfileDto();
        $dto->id = 1;
        $education = new AttributedEducationDto();
        $education->institutionName = 'MIT';
        $dto->education = [$education];

        $this->applier->apply($dto, new FieldsOptions(['education' => true]));

        $encoded = json_decode(json_encode($dto), true);
        $this->assertSame([['institutionName' => 'MIT']], $encoded['education']);

        $education->institutionId = 4;
        $encoded = json_decode(json_encode($dto), true);
        $this->assertSame(
            [['institutionId' => 4, 'institutionName' => 'MIT']],
            $encoded['education']
        );
    }

    public function testApplyWithoutConstructorPrototypeUsesRuntimeObject(): void
    {
        $dto = AttributedProfileDto::getSampleDto();
        $fieldsOptions = new FieldsOptions([]);

        $this->applier->apply($dto, $fieldsOptions);

        $this->assertSame(['id' => 1, 'name' => 'John'], $dto->jsonSerialize());
    }

    public function testApplyWithoutPrototypeExplicitFieldOnly(): void
    {
        $dto = AttributedProfileDto::getSampleDto();
        $fieldsOptions = new FieldsOptions(['id' => true]);

        $this->applier->apply($dto, $fieldsOptions);

        $this->assertSame(['id' => 1], $dto->jsonSerialize());
    }

    public function testApplyWithoutPrototypeNestedChildDefaults(): void
    {
        $dto = AttributedProfileDto::getSampleDto();
        $fieldsOptions = new FieldsOptions(['education' => true]);

        $this->applier->apply($dto, $fieldsOptions);
        $encoded = json_decode(json_encode($dto), true);

        $this->assertSame(
            [
                ['institutionId' => 3, 'institutionName' => 'Columbia'],
                ['institutionId' => 4, 'institutionName' => 'MIT'],
            ],
            $encoded['education']
        );
        $this->assertArrayNotHasKey('id', $encoded);
        $this->assertArrayNotHasKey('name', $encoded);
    }

    public function testApplyDoesNotValidateUnknownNestedFields(): void
    {
        $dto = AttributedProfileDto::getSampleDto();
        $fieldsOptions = new FieldsOptions(['education' => ['nosuchfield' => true]]);

        $this->applier->apply($dto, $fieldsOptions);
        $encoded = json_decode(json_encode($dto), true);

        $this->assertSame([null, null], $encoded['education']);
        $this->assertArrayNotHasKey('id', $encoded);
        $this->assertArrayNotHasKey('name', $encoded);
    }

    public function testAttributedEmptyEducationUsesChildDefaultFields(): void
    {
        $dto = AttributedProfileDto::getSampleDto();
        $fieldsOptions = $this->builder($dto)
            ->setFieldIncluded('education', [])
            ->build();

        $this->applier->apply($dto, $fieldsOptions);
        $encoded = json_decode(json_encode($dto), true);

        $this->assertSame(
            [
                ['institutionId' => 3, 'institutionName' => 'Columbia'],
                ['institutionId' => 4, 'institutionName' => 'MIT'],
            ],
            $encoded['education']
        );
    }

    public function testApplyManyObjectsWithSharedOptionsKeepsChildDefaults(): void
    {
        $fieldsOptions = new FieldsOptions(
            ['education' => true],
            new Validator(AttributedProfileDto::class)
        );
        $expectedEducation = [
            ['institutionId' => 3, 'institutionName' => 'Columbia'],
            ['institutionId' => 4, 'institutionName' => 'MIT'],
        ];

        foreach ([AttributedProfileDto::getSampleDto(), AttributedProfileDto::getSampleDto()] as $dto) {
            $this->applier->apply($dto, $fieldsOptions);
            $encoded = json_decode(json_encode($dto), true);

            $this->assertSame($expectedEducation, $encoded['education']);
            $this->assertArrayNotHasKey('id', $encoded);
            $this->assertArrayNotHasKey('name', $encoded);
        }
    }

    public function testApplyManyDataOnlyOptionsUsesRuntimePrototypeEachTime(): void
    {
        $fieldsOptions = new FieldsOptions(['education' => true]);
        $expectedEducation = [
            ['institutionId' => 3, 'institutionName' => 'Columbia'],
            ['institutionId' => 4, 'institutionName' => 'MIT'],
        ];

        foreach ([AttributedProfileDto::getSampleDto(), AttributedProfileDto::getSampleDto()] as $dto) {
            $this->applier->apply($dto, $fieldsOptions);
            $encoded = json_decode(json_encode($dto), true);

            $this->assertSame($expectedEducation, $encoded['education']);
            $this->assertArrayNotHasKey('id', $encoded);
        }
    }

    public function testApplyRawListsHaveNoDuplicates(): void
    {
        $cases = [
            [
                ['_defaults' => true, 'id' => true, '_basicInfo' => true, '_extended' => true],
                ['id', 'name', 'description'],
            ],
            [['_all' => true, 'name' => true, '_basicInfo' => true, 'education' => true], [
                'id',
                'name',
                'description',
                'education',
                'workHistory',
                'location',
                'location2',
                'dateCreated',
                '_text',
            ]],
            [['education' => true, '_basicInfo' => true, 'id' => true], ['id', 'name', 'education']],
        ];
        foreach ($cases as [$data, $expected]) {
            $dto = AttributedProfileDto::getSampleDto();
            $exportApplier = new RecordingExportApplier();
            (new FieldsOptionsObjectApplier($exportApplier))->apply($dto, new FieldsOptions($data));

            $passed = $exportApplier->fieldsPassedFor($dto);
            $this->assertSame([$expected], $passed, json_encode($data));
            foreach ($exportApplier->calls as [, $fields]) {
                $this->assertSame(array_values(array_unique($fields ?? [])), $fields ?? [], json_encode($data));
            }
        }
    }

    public function testApplyRawListsSkipStaticAndIgnoredButNoNamePrefix(): void
    {
        $cases = [
            [[], ['id', 'name', '__marker']],
            [['_all' => true, 'id' => true], ['id', 'name', '__marker']],
            [['_extra' => true, 'secret' => true], ['name']],
            [['secret' => true, 'counter' => true], []],
        ];
        foreach ($cases as [$data, $expected]) {
            $dto = new AttributedFieldFilterDto();
            $exportApplier = new RecordingExportApplier();
            (new FieldsOptionsObjectApplier($exportApplier))->apply($dto, new FieldsOptions($data));

            $this->assertSame([$expected], $exportApplier->fieldsPassedFor($dto), json_encode($data));
        }
    }

    /**
     * @param callable(FieldsOptionsBuilder): FieldsOptionsBuilder $configure
     */
    private function applyUnannotated(callable $configure): array
    {
        $dto = ProfileDto::getSampleDto();
        $fieldsOptions = $configure($this->builder($dto))->build();
        $this->applier->apply($dto, $fieldsOptions);

        return json_decode(json_encode($dto), true);
    }

    /**
     * @param callable(FieldsOptionsBuilder): FieldsOptionsBuilder $configure
     */
    private function applyAttributed(callable $configure): array
    {
        $dto = AttributedProfileDto::getSampleDto();
        $fieldsOptions = $configure($this->builder($dto))->build();
        $this->applier->apply($dto, $fieldsOptions);

        return json_decode(json_encode($dto), true);
    }

    private function builder(object $dto): FieldsOptionsBuilder
    {
        return new FieldsOptionsBuilder(new Validator($dto));
    }

    /**
     * @return array<class-string, bool>
     */
    private function classAttributeCacheOf(FieldsOptionsObjectApplier $applier): array
    {
        $cache = (new \ReflectionProperty(FieldsOptionsObjectApplier::class, 'classAttributeCache'))
            ->getValue($applier);
        $this->assertIsArray($cache);

        return $cache;
    }

    /**
     * @return array<string, true>
     */
    private function exportedPropertyMap(object $dto): array
    {
        $map = (new \ReflectionProperty(AbstractDto::class, '__exportedProperties'))->getValue($dto);

        return is_array($map) ? $map : [];
    }
}
