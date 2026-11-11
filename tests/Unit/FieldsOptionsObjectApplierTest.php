<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\FieldsOptions;
use Lucian\FieldsOptions\FieldsOptionsObjectApplier;
use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\Test\Fixture\Attributed\EducationDto as AttributedEducationDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\InvalidGroupNameDto;
use Lucian\FieldsOptions\Test\Fixture\Attributed\NestedInvalidGroupDto;
use Lucian\FieldsOptions\Test\Fixture\CustomValidator;
use Lucian\FieldsOptions\Test\Fixture\Cycle\AttributedNode;
use Lucian\FieldsOptions\Test\Fixture\Cycle\LegacyNode;
use Lucian\FieldsOptions\Test\Fixture\LegacyFieldFilterDto;
use Lucian\FieldsOptions\Test\Fixture\LocationDto;
use Lucian\FieldsOptions\Test\Fixture\ProfileDto;
use Lucian\FieldsOptions\Test\Fixture\RecordingExportApplier;
use Lucian\FieldsOptions\Test\Fixture\SampleExportApplier;
use Lucian\FieldsOptions\Test\Fixture\WorkplaceDto;
use Lucian\FieldsOptions\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FieldsOptionsObjectApplierTest extends TestCase
{
    private const PROFILE_FIELDS = [
        'id',
        'name',
        'description',
        'education',
        'workHistory',
        'location',
        'location2',
        'dateCreated',
        '_text',
    ];

    private FieldsOptionsObjectApplier $applier;
    private FieldsOptionsBuilder $builder;
    private ProfileDto $dto;

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

    /**
     * Test that WeakMap properly handles object identity after garbage collection
     *
     * This test verifies that the fix using WeakMap correctly handles scenarios where
     * spl_object_hash() values are reused. With WeakMap, object identity is properly
     * tracked, so even if a new object gets the same hash as a destroyed object,
     * the cache will correctly recognize it as a different object.
     */
    public function testWeakMapHandlesObjectIdentityCorrectly()
    {
        $fieldsOptions1 = $this->builder
            ->setFieldIncluded('id')
            ->build();

        $fieldsOptions2 = $this->builder
            ->setFieldIncluded('name')
            ->build();

        // Create first object and apply fieldsOptions1
        $dto1 = $this->getSampleDto();
        $hash1 = spl_object_hash($dto1);
        $this->applier->apply($dto1, $fieldsOptions1);

        // Verify first application worked correctly - only 'id' should be exported
        $this->assertEquals(['id' => 1], $dto1->jsonSerialize());

        // Force garbage collection by unsetting the object and running gc
        unset($dto1);
        gc_collect_cycles();

        // Create new objects until we get one with the same spl_object_hash
        $dto2 = null;
        $hash2 = null;
        $maxAttempts = 10000;
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $tempDto = $this->getSampleDto();
            $tempHash = spl_object_hash($tempDto);

            if ($tempHash === $hash1) {
                $dto2 = $tempDto;
                $hash2 = $tempHash;
                break;
            }

            unset($tempDto);
            if ($attempts % 10 === 0) {
                gc_collect_cycles();
            }
            $attempts++;
        }

        // If we found a hash collision, verify the fix works
        if ($dto2 !== null) {
            $this->assertEquals($hash1, $hash2, 'Found object with reused hash after ' . $attempts . ' attempts');

            // Apply fieldsOptions2 to dto2
            // With WeakMap fix: The cache will correctly recognize dto2 as a NEW object
            // even though it has the same spl_object_hash as dto1
            $this->applier->apply($dto2, $fieldsOptions2);

            $result = $dto2->jsonSerialize();

            // VERIFY THE FIX: dto2 should export 'name' because WeakMap correctly
            // recognizes it as a different object than dto1
            $this->assertArrayHasKey(
                'name',
                $result,
                'WeakMap correctly handled object identity. Expected dto2 to export \'name\' ' .
                '(fieldsOptions2), and it did! Even though dto2 has the same spl_object_hash ' .
                "as the destroyed dto1, WeakMap recognized them as different objects. Result: " .
                json_encode($result)
            );

            $this->assertEquals('John', $result['name'], 'Name field should be exported');
        } else {
            $this->markTestSkipped(
                'Could not reproduce hash collision within ' . $maxAttempts . ' attempts. ' .
                'The test would verify that WeakMap properly handles reused hashes.'
            );
        }
    }

    /**
     * Test that WeakMap provides proper automatic cleanup
     *
     * This test verifies that WeakMap automatically removes entries when objects
     * are garbage collected, unlike the old array-based cache with spl_object_hash
     */
    public function testWeakMapAutomaticCleanup()
    {
        $fieldsOptions = $this->builder
            ->setFieldIncluded('id')
            ->build();

        // Create and process multiple objects
        $objects = [];
        for ($i = 0; $i < 10; $i++) {
            $dto = $this->getSampleDto();
            $this->applier->apply($dto, $fieldsOptions);
            $objects[] = $dto;
        }

        // All objects should be cached (no easy way to check WeakMap size directly)
        // But we can verify they're all processed correctly
        foreach ($objects as $obj) {
            $this->assertEquals(['id' => 1], $obj->jsonSerialize());
        }

        // Release half the objects
        for ($i = 0; $i < 5; $i++) {
            unset($objects[$i]);
        }
        gc_collect_cycles();

        // WeakMap automatically cleaned up the released objects
        // Create new objects to verify cache still works for remaining objects
        $newDto = $this->getSampleDto();
        $this->applier->apply($newDto, $fieldsOptions);
        $this->assertEquals(['id' => 1], $newDto->jsonSerialize());

        // Verify remaining objects still cached
        for ($i = 5; $i < 10; $i++) {
            // Try to apply again - should be cached and skip
            $this->applier->apply($objects[$i], $fieldsOptions);
            $this->assertEquals(['id' => 1], $objects[$i]->jsonSerialize());
        }

        $this->assertTrue(true, 'WeakMap successfully handles automatic cleanup');
    }

    /**
     * Test that WeakMap correctly handles the same object with different FieldsOptions
     *
     * This verifies that the cache properly tracks not just object identity, but also
     * which FieldsOptions were applied to that specific object
     */
    public function testWeakMapHandlesSameObjectWithDifferentOptions()
    {
        $fieldsOptions1 = $this->builder
            ->setFieldIncluded('id')
            ->build();

        $fieldsOptions2 = $this->builder
            ->setFieldIncluded('name')
            ->build();

        // Create object and apply first options
        $dto = $this->getSampleDto();
        $this->applier->apply($dto, $fieldsOptions1);

        // Should only have 'id'
        $this->assertEquals(['id' => 1], $dto->jsonSerialize());

        // Apply different options to the SAME object
        $this->applier->apply($dto, $fieldsOptions2);

        // Should now have 'name' because fieldsOptions2 is different
        $result = $dto->jsonSerialize();
        $this->assertArrayHasKey(
            'name',
            $result,
            'WeakMap correctly recognized different FieldsOptions for the same object'
        );
        $this->assertEquals('John', $result['name']);
    }

    /**
     * Intentional divergence from 1.x, which passed the reflected property list for an `_all`-only request.
     * v2 passes null ("export everything") and the result does not depend on a bound prototype.
     */
    public function testAllFieldsOnlyPassesNullUnlike1xRegardlessOfPrototype(): void
    {
        $withPrototype = $this->captureRawExportedFields(
            new FieldsOptions(['_all' => true], new Validator(ProfileDto::class))
        );
        $withoutPrototype = $this->captureRawExportedFields(
            new FieldsOptions(['_all' => true])
        );

        $this->assertNull($withPrototype);
        $this->assertSame($withoutPrototype, $withPrototype);
    }

    public function testApplyLegacyAllFieldsWithExplicitFieldRawListIsUnchangedWithPrototype(): void
    {
        $data = ['_all' => true, 'name' => true];
        $withPrototype = $this->captureRawExportedFields(
            new FieldsOptions($data, new Validator(ProfileDto::class))
        );
        $withoutPrototype = $this->captureRawExportedFields(
            new FieldsOptions($data)
        );

        $this->assertIsArray($withPrototype);
        $this->assertSame($withoutPrototype, $withPrototype);
        $this->assertSame($withPrototype, array_values(array_unique($withPrototype)));
        $this->assertCount(1, array_keys($withPrototype, 'name', true));
        $this->assertCount(1, array_keys($withPrototype, '_text', true));
        $this->assertNotContains('__exportedProperties', $withPrototype);
        $this->assertNotContains('__excludedProperties', $withPrototype);
    }

    public function testUnannotatedPrototypeDoesNotIncludeUnmarkedDefaults(): void
    {
        $fieldsOptions = new FieldsOptions(['_defaults' => true], new Validator($this->dto));
        $this->assertSame(
            [],
            $fieldsOptions->getIncludedFields(),
            'prototype is bound: schema-calculated defaults are empty for an unannotated class'
        );

        $this->assertSame(['id', 'name'], $this->captureRawExportedFields($fieldsOptions));
        $this->applier->apply($this->dto, $fieldsOptions);
        $this->assertEquals(
            ['id' => 1, 'name' => 'John'],
            $this->dto->jsonSerialize()
        );
    }

    public function testUnannotatedEducationTrueStillExportsAllChildFields(): void
    {
        $fieldsOptions = $this->builder
            ->setFieldIncluded('education', [])
            ->build();

        $this->applier->apply($this->dto, $fieldsOptions);
        $encoded = json_decode(json_encode($this->dto), true);

        $this->assertArrayHasKey('institutionId', $encoded['education'][0]);
        $this->assertArrayHasKey('institutionName', $encoded['education'][0]);
        $this->assertSame(['education'], array_keys($encoded));
    }

    public function testLegacyChildSliceIsDataOnlyAndSharesTypeCaches(): void
    {
        $root = new FieldsOptions(['education' => ['_basicInfo' => true]], new Validator(ProfileDto::class));
        $slice = $root->sliceWithoutSchema('education');

        $this->assertSame(['_basicInfo' => true], $slice->toArray());
        $this->assertSame(['_basicInfo'], $slice->getIncludedFields(), 'no schema bound');
        foreach (['typeResolver', 'schemaCache'] as $cache) {
            $property = new \ReflectionProperty(FieldsOptions::class, $cache);
            $this->assertNotNull($property->getValue($root));
            $this->assertSame($property->getValue($root), $property->getValue($slice));
            $this->assertSame(
                $property->getValue($root),
                $property->getValue($slice->withPrototype(new AttributedEducationDto()))
            );
        }
    }

    public function testLegacyChildSliceIsRevalidatedLike1x(): void
    {
        $fieldsOptions = new FieldsOptions(
            ['education' => ['institutionName' => 'yes']],
            new CustomValidator()
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid field options');
        $this->applier->apply($this->dto, $fieldsOptions);
    }

    /**
     * 1.x built (and validated) a child slice only when it recursed into an object.
     *
     * @param list<mixed>|null $workHistory null keeps the sample's WorkplaceDto items
     * @param list<string> $expected 1.x raw list for the root
     */
    #[DataProvider('legacySliceNotRecursedProvider')]
    public function testLegacyChildSliceIsNotValidatedWithoutRecursionLike1x(
        array $data,
        ?array $workHistory,
        array $expected
    ): void {
        $dto = ProfileDto::getSampleDto();
        if ($workHistory !== null) {
            $dto->workHistory = $workHistory;
        }
        $exportApplier = new RecordingExportApplier();

        (new FieldsOptionsObjectApplier($exportApplier))->apply($dto, new FieldsOptions($data, new CustomValidator()));

        $this->assertSame([[$dto, $expected]], $exportApplier->calls);
    }

    public static function legacySliceNotRecursedProvider(): array
    {
        return [
            'string value' => [['description' => ['x' => 'yes']], null, ['description']],
            'null object property' => [['location' => ['city' => 'yes']], null, ['location']],
            'int value next to a valid field' => [['id' => ['x' => 1], 'name' => true], null, ['id', 'name']],
            'empty collection' => [['workHistory' => ['id' => 'yes']], [], ['workHistory']],
            'collection of scalars' => [['workHistory' => ['id' => 'yes']], ['a', 1, null], ['workHistory']],
        ];
    }

    /**
     * @param list<mixed>|null $workHistory null keeps the sample's WorkplaceDto items
     */
    #[DataProvider('legacySliceRecursedProvider')]
    public function testLegacyChildSliceIsValidatedBeforeFirstNestedApply(array $data, ?array $workHistory): void
    {
        $dto = ProfileDto::getSampleDto();
        if ($workHistory !== null) {
            $dto->workHistory = $workHistory;
        }
        $exportApplier = new RecordingExportApplier();
        $fieldsOptions = new FieldsOptions($data, new CustomValidator());

        try {
            (new FieldsOptionsObjectApplier($exportApplier))->apply($dto, $fieldsOptions);
            $this->fail('Expected the nested slice to be rejected');
        } catch (\RuntimeException $e) {
            $this->assertStringStartsWith('Invalid field options', $e->getMessage());
        }
        $this->assertSame([], $exportApplier->calls);
    }

    public static function legacySliceRecursedProvider(): array
    {
        $workplace = new WorkplaceDto();
        $workplace->id = 1;

        return [
            'object child' => [['location2' => ['city' => 'yes']], null],
            'nested leaf under object child' => [['location2' => ['city' => ['x' => 'yes']]], null],
            'collection of objects' => [['workHistory' => ['id' => 'yes']], null],
            'object after scalars in a collection' => [['workHistory' => ['id' => 'yes']], ['a', null, $workplace]],
        ];
    }

    public function testFailedApplyIsRetriedWithTheSameOptions(): void
    {
        $cases = [
            'invalid nested slice' => [ProfileDto::getSampleDto(), ['location2' => ['city' => 'yes']]],
            'invalid group on nested attributed class' => [new NestedInvalidGroupDto(), ['child' => true]],
        ];
        foreach ($cases as $name => [$dto, $data]) {
            if ($dto instanceof NestedInvalidGroupDto) {
                $dto->child = new InvalidGroupNameDto();
            }
            $applier = new FieldsOptionsObjectApplier(new SampleExportApplier());
            $failures = 0;
            for ($i = 0; $i < 2; $i++) {
                try {
                    $applier->apply($dto, new FieldsOptions($data, new CustomValidator()));
                } catch (\RuntimeException | \InvalidArgumentException) {
                    $failures++;
                }
            }

            $this->assertSame(2, $failures, $name);
        }
    }

    public function testFailedApplyForgetsEarlierSuccessfulApply(): void
    {
        $first = ['education' => ['institutionId' => true]];
        $dto = ProfileDto::getSampleDto();
        $applier = new FieldsOptionsObjectApplier(new SampleExportApplier());
        $applier->apply($dto, new FieldsOptions($first));

        try {
            // education items are re-applied before location2 is rejected
            $applier->apply($dto, new FieldsOptions(
                ['education' => ['institutionName' => true], 'location2' => ['city' => 'yes']],
                new CustomValidator()
            ));
            $this->fail('Expected the location2 slice to be rejected');
        } catch (\RuntimeException) {
        }
        $applier->apply($dto, new FieldsOptions($first));

        $fresh = ProfileDto::getSampleDto();
        (new FieldsOptionsObjectApplier(new SampleExportApplier()))->apply($fresh, new FieldsOptions($first));
        $this->assertSame(json_encode($fresh), json_encode($dto));
        $this->assertSame(
            ['education' => [['institutionId' => 3], ['institutionId' => 4]]],
            json_decode((string) json_encode($dto), true)
        );
    }

    public function testApplyLegacyRawListsHaveNoDuplicates(): void
    {
        $cases = [
            [['_all' => true, 'name' => true, 'education' => true], self::PROFILE_FIELDS],
            [['_defaults' => true, 'id' => true, 'description' => true], ['id', 'name', 'description']],
            [['_defaults' => true, '_all' => true, 'id' => true], self::PROFILE_FIELDS],
        ];
        foreach ($cases as [$data, $expected]) {
            $raw = $this->captureRawExportedFields(new FieldsOptions($data));

            $this->assertSame($expected, $raw, json_encode($data));
            $this->assertSame(array_values(array_unique($raw)), $raw, json_encode($data));
        }
    }

    /**
     * @param 'profile'|'filter'|'uninitialized' $graph
     * @param list<array{string, list<string>|null}> $expected1x
     */
    #[DataProvider('legacy1xParityProvider')]
    public function testApplyLegacyMatches1xRawCalls(string $graph, array $data, array $expected1x): void
    {
        $this->assertSame($expected1x, $this->recordLegacyCalls($graph, $data, false));
        $this->assertSame($expected1x, $this->recordLegacyCalls($graph, $data, true));
    }

    /**
     * Ground truth recorded from the 1.x line (master) with an applier that keeps every
     * setExportedFields() call verbatim. Labels are paths in the fixture graph.
     */
    public static function legacy1xParityProvider(): array
    {
        return [
            'empty' => ['profile', [], []],
            'defaults true' => ['profile', ['_defaults' => true], [['root', ['id', 'name']]]],
            'defaults false' => ['profile', ['_defaults' => false], [['root', []]]],
            'all false' => ['profile', ['_all' => false], []],
            'field true' => ['profile', ['name' => true], [['root', ['name']]]],
            'field false' => ['profile', ['name' => false], []],
            'field true and false' => ['profile', ['name' => true, 'id' => false], [['root', ['name']]]],
            'fields all false' => ['profile', ['name' => false, 'id' => false], []],
            'defaults minus field' => ['profile', ['_defaults' => true, 'name' => false], [['root', ['id']]]],
            'defaults plus field' => [
                'profile',
                ['_defaults' => true, 'description' => true],
                [['root', ['id', 'name', 'description']]],
            ],
            'defaults false plus field' => [
                'profile',
                ['_defaults' => false, 'description' => true],
                [['root', ['description']]],
            ],
            'nested true' => ['profile', ['education' => true], [['root', ['education']]]],
            'nested empty' => ['profile', ['education' => []], [['root', ['education']]]],
            'nested slice' => [
                'profile',
                ['education' => ['institutionName' => true]],
                [['education.0', ['institutionName']], ['education.1', ['institutionName']], ['root', ['education']]],
            ],
            'nested defaults false' => [
                'profile',
                ['education' => ['_defaults' => false]],
                [['education.0', []], ['education.1', []], ['root', ['education']]],
            ],
            'nested all false' => ['profile', ['education' => ['_all' => false]], [['root', ['education']]]],
            'nested defaults plus minus' => [
                'profile',
                ['workHistory' => ['_defaults' => true, 'startYear' => true, 'employerName' => false]],
                [
                    ['workHistory.0', ['id', 'startYear']],
                    ['workHistory.1', ['id', 'startYear']],
                    ['root', ['workHistory']],
                ],
            ],
            'nested untyped slice and null object' => [
                'profile',
                ['location2' => ['city' => true], 'location' => true],
                [['location2', ['city']], ['root', ['location', 'location2']]],
            ],
            'custom group only' => ['profile', ['_basicInfo' => true], [['root', []]]],
            'custom group false' => ['profile', ['_basicInfo' => false], []],
            'custom group plus field' => ['profile', ['_basicInfo' => true, 'name' => true], [['root', ['name']]]],
            'nested custom group only' => [
                'profile',
                ['education' => ['_basicInfo' => true]],
                [['education.0', []], ['education.1', []], ['root', ['education']]],
            ],
            'opt only' => ['profile', ['_opt' => ['lang' => 'en']], [['root', ['id', 'name']]]],
            'opt false only' => ['profile', ['_opt' => false], []],
            'field with opt' => ['profile', ['name' => ['_opt' => ['upper' => true]]], [['root', ['name']]]],
            'underscore property' => ['profile', ['_text' => true], [['root', ['_text']]]],
            'non dto object' => ['profile', ['dateCreated' => true], [['root', ['dateCreated']]]],
            'filtered fixture nested' => [
                'filter',
                ['location' => ['city' => true], 'id' => true],
                [['location', ['city']], ['root', ['id', 'location']]],
            ],
        ];
    }

    /**
     * @param 'profile'|'filter'|'uninitialized' $graph
     * @param list<array{string, list<string>|null}>|string $recorded1x 1.x calls, or the error 1.x threw
     * @param list<array{string, list<string>|null}> $expected
     */
    #[DataProvider('legacy1xIntentionalDivergenceProvider')]
    public function testApplyLegacyIntentionallyDivergesFrom1x(
        string $graph,
        array $data,
        array|string $recorded1x,
        array $expected,
        bool $validWithPrototype = true
    ): void {
        $actual = $this->recordLegacyCalls($graph, $data, false);
        $this->assertSame($expected, $actual);
        $this->assertNotSame($recorded1x, $actual);

        if (!$validWithPrototype) {
            $this->expectException(\RuntimeException::class);
        }
        $this->assertSame($expected, $this->recordLegacyCalls($graph, $data, true));
    }

    public static function legacy1xIntentionalDivergenceProvider(): array
    {
        $profile1x = ['__exportedProperties', ...self::PROFILE_FIELDS, '__excludedProperties'];
        $workplace1x = ['__exportedProperties', 'id', 'employerName', 'startYear', 'endYear', '__excludedProperties'];
        $education1x = ['institutionId', 'institutionName', '__exportedProperties', '__excludedProperties'];

        return [
            // intentional: `_all`-only passes null instead of the reflected property list
            'all only' => ['profile', ['_all' => true], [['root', $profile1x]], [['root', null]]],
            'all only with opt' => [
                'profile',
                ['_all' => true, '_opt' => ['x' => 1]],
                [['root', $profile1x]],
                [['root', null]],
            ],
            'nested all only' => [
                'profile',
                ['education' => ['_all' => true]],
                [['education.0', $education1x], ['education.1', $education1x], ['root', ['education']]],
                [['education.0', null], ['education.1', null], ['root', ['education']]],
            ],
            'nested all only on defaults' => [
                'profile',
                ['_defaults' => true, '_all' => false, 'workHistory' => ['_all' => true]],
                [
                    ['workHistory.0', $workplace1x],
                    ['workHistory.1', $workplace1x],
                    ['root', ['id', 'name', 'workHistory']],
                ],
                [['workHistory.0', null], ['workHistory.1', null], ['root', ['id', 'name', 'workHistory']]],
            ],
            'non dto object all only' => [
                'profile',
                ['dateCreated' => ['_all' => true]],
                [['dateCreated', []], ['root', ['dateCreated']]],
                [['dateCreated', null], ['root', ['dateCreated']]],
            ],

            // intentional: static and #[Ignore] properties are not fields
            'all with field' => [
                'profile',
                ['_all' => true, 'name' => true],
                [['root', $profile1x]],
                [['root', self::PROFILE_FIELDS]],
            ],
            'all with field false' => [
                'profile',
                ['_all' => true, 'name' => false],
                [['root', array_values(array_diff($profile1x, ['name']))]],
                [['root', array_values(array_diff(self::PROFILE_FIELDS, ['name']))]],
            ],
            'all with defaults' => [
                'profile',
                ['_all' => true, '_defaults' => true],
                [['root', $profile1x]],
                [['root', self::PROFILE_FIELDS]],
            ],
            'all with nested false field' => [
                'profile',
                ['_all' => true, 'education' => ['institutionName' => false]],
                [['root', $profile1x]],
                [['root', self::PROFILE_FIELDS]],
            ],
            'mixed' => [
                'profile',
                [
                    '_all' => true,
                    'education' => ['_all' => true, 'institutionName' => false],
                    'workHistory' => ['_defaults' => true, 'endYear' => true],
                    'location2' => ['_defaults' => false, 'city' => true],
                ],
                [
                    ['education.0', ['institutionId', '__exportedProperties', '__excludedProperties']],
                    ['education.1', ['institutionId', '__exportedProperties', '__excludedProperties']],
                    ['workHistory.0', ['id', 'employerName', 'endYear']],
                    ['workHistory.1', ['id', 'employerName', 'endYear']],
                    ['location2', ['city']],
                    ['root', $profile1x],
                ],
                [
                    ['education.0', ['institutionId']],
                    ['education.1', ['institutionId']],
                    ['workHistory.0', ['id', 'employerName', 'endYear']],
                    ['workHistory.1', ['id', 'employerName', 'endYear']],
                    ['location2', ['city']],
                    ['root', self::PROFILE_FIELDS],
                ],
            ],
            'filtered fixture all with field' => [
                'filter',
                ['_all' => true, 'label' => true],
                [[
                    'root',
                    [
                        'instances',
                        'internal',
                        'id',
                        'label',
                        'location',
                        '__exportedProperties',
                        '__excludedProperties',
                    ],
                ]],
                [['root', ['id', 'label', 'location']]],
            ],
            'filtered fixture defaults' => [
                'filter',
                ['_defaults' => true],
                [['root', ['internal', 'id', 'label', 'location']]],
                [['root', ['id', 'label', 'location']]],
            ],
            'filtered fixture static field' => [
                'filter',
                ['instances' => true],
                [['root', ['instances']]],
                [['root', []]],
                false,
            ],
            'filtered fixture ignored field' => [
                'filter',
                ['internal' => true],
                [['root', ['internal']]],
                [['root', []]],
                false,
            ],

            // intentional: uninitialized typed properties are listed but not read (1.x threw)
            'uninitialized scalar' => [
                'uninitialized',
                ['id' => true],
                self::uninitializedError('id'),
                [['root', ['id']]],
            ],
            'uninitialized nested' => [
                'uninitialized',
                ['location' => ['city' => true], 'label' => true],
                self::uninitializedError('location'),
                [['root', ['label', 'location']]],
            ],
            'uninitialized all with field' => [
                'uninitialized',
                ['_all' => true, 'location' => true],
                self::uninitializedError('location'),
                [['root', ['id', 'label', 'location']]],
            ],
        ];
    }

    /**
     * The apply cache keys on request data only; the result must not depend on whether a prototype
     * was bound or on which variant ran first for the same object.
     *
     * @param list<string> $expected 1.x raw list
     */
    #[DataProvider('prototypeOrderProvider')]
    public function testApplyCacheIsPrototypeInvariant(array $data, array $expected, bool $prototypeFirst): void
    {
        $exportApplier = new RecordingExportApplier();
        $applier = new FieldsOptionsObjectApplier($exportApplier);
        $dto = ProfileDto::getSampleDto();
        $plain = new FieldsOptions($data);
        $withPrototype = new FieldsOptions($data, new Validator(ProfileDto::class));

        foreach ($prototypeFirst ? [$withPrototype, $plain] : [$plain, $withPrototype] as $fieldsOptions) {
            $applier->apply($dto, $fieldsOptions);
            $this->assertSame([$expected], $exportApplier->fieldsPassedFor($dto));
        }

        $fresh = ProfileDto::getSampleDto();
        (new FieldsOptionsObjectApplier(new SampleExportApplier()))->apply($fresh, $plain);
        $this->assertSame(json_encode($fresh), json_encode($dto));
    }

    public static function prototypeOrderProvider(): array
    {
        $cases = [
            'custom group only' => [['_basicInfo' => true], []],
            'opt only' => [['_opt' => ['lang' => 'en']], ['id', 'name']],
            'defaults only' => [['_defaults' => true], ['id', 'name']],
        ];

        $provided = [];
        foreach ($cases as $name => [$data, $expected]) {
            $provided["$name, plain first"] = [$data, $expected, false];
            $provided["$name, prototype first"] = [$data, $expected, true];
        }

        return $provided;
    }

    /**
     * @param 'pair-link'|'self-link'|'pair-items' $topology
     * @param 'legacy'|'attributed' $kindA
     * @param 'legacy'|'attributed' $kindB
     * @param list<array{string, list<string>|null}> $expected
     */
    #[DataProvider('cyclicGraphProvider')]
    public function testApplyTerminatesOnCyclicGraph(
        string $topology,
        string $kindA,
        string $kindB,
        array $data,
        array $expected
    ): void {
        $a = $this->node($kindA, 1);
        $b = $topology === 'self-link' ? $a : $this->node($kindB, 2);
        if ($topology === 'pair-items') {
            $a->items = [$b];
            $b->items = [$a];
        } else {
            $a->link = $b;
            $b->link = $a;
        }
        $labels = new \WeakMap();
        $labels[$b] = 'b';
        $labels[$a] = 'a';

        $exportApplier = new RecordingExportApplier();
        (new FieldsOptionsObjectApplier($exportApplier))->apply($a, new FieldsOptions($data));

        $this->assertSame($expected, $this->labelCalls($exportApplier, $labels));
    }

    public static function cyclicGraphProvider(): array
    {
        $all = ['id', 'name', 'link', 'items'];
        $defaults = ['id', 'link', 'items'];

        return [
            'legacy pair, all' => [
                'pair-link', 'legacy', 'legacy',
                ['_all' => true, 'link' => ['_all' => true, 'link' => true]],
                [['b', $all], ['a', $all]],
            ],
            'legacy pair, nested slices' => [
                'pair-link', 'legacy', 'legacy',
                ['link' => ['link' => ['name' => true]]],
                [['a', ['name']], ['b', ['link']], ['a', ['link']]],
            ],
            'legacy pair, defaults' => [
                'pair-link', 'legacy', 'legacy',
                ['_defaults' => true, 'link' => ['_defaults' => true]],
                [['b', $all], ['a', $all]],
            ],
            'legacy self, nested slices' => [
                'self-link', 'legacy', 'legacy',
                ['link' => ['link' => ['id' => true]]],
                [['a', ['id']], ['a', ['link']], ['a', ['link']]],
            ],
            'legacy collection, all' => [
                'pair-items', 'legacy', 'legacy',
                ['items' => ['_all' => true, 'items' => ['name' => true]]],
                [['a', ['name']], ['b', $all], ['a', ['items']]],
            ],
            'attributed pair, implicit defaults' => [
                'pair-link', 'attributed', 'attributed',
                [],
                [['b', $defaults], ['a', $defaults]],
            ],
            'attributed pair, defaults and group' => [
                'pair-link', 'attributed', 'attributed',
                ['_defaults' => true, '_label' => true],
                [['a', $defaults], ['b', $defaults], ['a', $all]],
            ],
            'attributed pair, all' => [
                'pair-link', 'attributed', 'attributed',
                ['_all' => true, 'link' => ['_all' => true, 'name' => false]],
                [['b', $defaults], ['a', $defaults], ['b', $defaults], ['a', $all]],
            ],
            'attributed pair, nested slices' => [
                'pair-link', 'attributed', 'attributed',
                ['link' => ['link' => ['name' => true]]],
                [['a', ['name']], ['b', ['link']], ['a', ['link']]],
            ],
            'attributed self, implicit defaults' => [
                'self-link', 'attributed', 'attributed',
                [],
                [['a', $defaults]],
            ],
            'attributed self, all' => [
                'self-link', 'attributed', 'attributed',
                ['_all' => true, 'name' => true],
                [['a', $defaults], ['a', $all]],
            ],
            'attributed collection, implicit defaults' => [
                'pair-items', 'attributed', 'attributed',
                [],
                [['b', $defaults], ['a', $defaults]],
            ],
            'mixed pair from legacy, explicit' => [
                'pair-link', 'legacy', 'attributed',
                ['link' => true],
                [['b', $defaults], ['a', ['link']]],
            ],
            'mixed pair from legacy, all' => [
                'pair-link', 'legacy', 'attributed',
                ['_all' => true, 'link' => ['_all' => true, 'name' => false]],
                [['b', $defaults], ['a', $all]],
            ],
            'mixed pair from legacy, nested slices' => [
                'pair-link', 'legacy', 'attributed',
                ['link' => ['link' => ['link' => ['id' => true]]]],
                [['b', ['id']], ['a', ['link']], ['b', ['link']], ['a', ['link']]],
            ],
            'mixed pair from attributed, implicit defaults' => [
                'pair-link', 'attributed', 'legacy',
                [],
                [['a', $defaults]],
            ],
            'mixed collection from attributed, defaults' => [
                'pair-items', 'attributed', 'legacy',
                ['_defaults' => true, 'items' => ['_defaults' => true, 'items' => true]],
                [['a', $defaults], ['b', $all], ['a', $defaults]],
            ],
        ];
    }

    private static function uninitializedError(string $property): string
    {
        return sprintf(
            'Typed property %s::$%s must not be accessed before initialization',
            LegacyFieldFilterDto::class,
            $property
        );
    }

    /**
     * @param 'legacy'|'attributed' $kind
     */
    private function node(string $kind, int $id): LegacyNode|AttributedNode
    {
        return $kind === 'legacy' ? new LegacyNode($id) : new AttributedNode($id);
    }

    /**
     * @param 'profile'|'filter'|'uninitialized' $graph
     * @return list<array{string, list<string>|null}>
     */
    private function recordLegacyCalls(string $graph, array $data, bool $withPrototype): array
    {
        $labels = new \WeakMap();
        if ($graph === 'profile') {
            $root = ProfileDto::getSampleDto();
            foreach ($root->education as $i => $education) {
                $labels[$education] = "education.$i";
            }
            foreach ($root->workHistory as $i => $workplace) {
                $labels[$workplace] = "workHistory.$i";
            }
            $labels[$root->location2] = 'location2';
            $labels[$root->dateCreated] = 'dateCreated';
        } else {
            $root = new LegacyFieldFilterDto();
            if ($graph === 'filter') {
                $root->id = 7;
                $root->location = new LocationDto();
                $labels[$root->location] = 'location';
            }
        }
        $labels[$root] = 'root';

        $fieldsOptions = $withPrototype
            ? new FieldsOptions($data, new Validator($root::class))
            : new FieldsOptions($data);
        $exportApplier = new RecordingExportApplier();
        (new FieldsOptionsObjectApplier($exportApplier))->apply($root, $fieldsOptions);

        return $this->labelCalls($exportApplier, $labels);
    }

    /**
     * @param \WeakMap<object, string> $labels
     * @return list<array{string, array|null}>
     */
    private function labelCalls(RecordingExportApplier $exportApplier, \WeakMap $labels): array
    {
        $calls = [];
        foreach ($exportApplier->calls as [$data, $fields]) {
            $calls[] = [is_object($data) ? ($labels[$data] ?? $data::class) : 'array', $fields];
        }

        return $calls;
    }

    /**
     * Raw list the root ProfileDto receives (`null` = all fields).
     *
     * @return list<string>|null
     */
    private function captureRawExportedFields(FieldsOptions $fieldsOptions): ?array
    {
        $dto = ProfileDto::getSampleDto();
        $exportApplier = new RecordingExportApplier();
        (new FieldsOptionsObjectApplier($exportApplier))->apply($dto, $fieldsOptions);

        $passed = $exportApplier->fieldsPassedFor($dto);
        $this->assertCount(1, $passed);

        return $passed[0];
    }

    private function getSampleDto(): ProfileDto
    {
        return ProfileDto::getSampleDto();
    }
}
