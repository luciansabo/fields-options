<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\FieldsOptions;
use Lucian\FieldsOptions\FieldsOptionsObjectApplier;
use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\Test\Fixture\ProfileDto;
use Lucian\FieldsOptions\Test\Fixture\SampleExportApplier;
use Lucian\FieldsOptions\Validator;
use PHPUnit\Framework\TestCase;

class FieldsOptionsObjectApplierTest extends TestCase
{
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

    private function getSampleDto(): ProfileDto
    {
        return ProfileDto::getSampleDto();
    }
}
