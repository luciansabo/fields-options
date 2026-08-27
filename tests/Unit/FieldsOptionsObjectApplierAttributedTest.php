<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\FieldsOptionsBuilder;
use Lucian\FieldsOptions\FieldsOptionsObjectApplier;
use Lucian\FieldsOptions\Test\Fixture\Attributed\ProfileDto as AttributedProfileDto;
use Lucian\FieldsOptions\Test\Fixture\ProfileDto;
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
}
