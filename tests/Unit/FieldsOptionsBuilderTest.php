<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\FieldsOptionsBuilder;
use PHPUnit\Framework\TestCase;

class FieldsOptionsBuilderTest extends TestCase
{
    private FieldsOptionsBuilder $builder;

    public function setUp(): void
    {
        $this->builder = new FieldsOptionsBuilder();
        parent::setUp();
    }

    public function testSetFieldIncluded()
    {
        $fieldsOptions = $this->builder
            ->setFieldIncluded('test')
            ->setFieldIncluded('profile.education')
            ->build();

        $this->assertTrue($fieldsOptions->isFieldIncluded('test'));
        $this->assertTrue($fieldsOptions->isFieldIncluded('profile.education'));
        $this->assertEquals(['education'], $fieldsOptions->getIncludedFields('profile'));

        $this->expectException(\LogicException::class);
        $this->builder
            ->setFieldIncluded(null);
    }

    public function testSetFieldsIncluded()
    {
        // root fields
        $fieldsOptions = $this->builder
            ->setFieldIncluded(null, ['test', 'profile.education'])
            ->build();

        $this->assertTrue($fieldsOptions->isFieldIncluded('test'));
        $this->assertTrue($fieldsOptions->isFieldIncluded('profile.education'));

        // child fields
        $fieldsOptions = $this->builder
            ->setFieldIncluded('profile', ['workHistory', 'education'])
            ->build();

        $this->assertTrue($fieldsOptions->isFieldIncluded('profile.workHistory'));
        $this->assertTrue($fieldsOptions->isFieldIncluded('profile.education'));

        // complex + nested child fields
        $fieldsOptions = $this->builder
            ->setFieldIncluded(null, ['name'])
            ->setFieldIncluded('profile', ['workHistory'])
            ->setFieldIncluded('profile.education', ['id', 'name'])
            ->build();

        $this->assertTrue($fieldsOptions->isFieldIncluded('name'));
        $this->assertTrue($fieldsOptions->isFieldIncluded('profile.workHistory'));
        $this->assertTrue($fieldsOptions->isFieldIncluded('profile.education.id'));
        $this->assertTrue($fieldsOptions->isFieldIncluded('profile.education.name'));
    }

    public function testSetFieldExcluded()
    {
        $fieldsOptions = $this->builder
            ->setFieldExcluded('test')
            ->setFieldExcluded('profile.education')
            ->setFieldIncluded('profile.name')
            ->build();

        $this->assertFalse($fieldsOptions->isFieldIncluded('test'));
        $this->assertFalse($fieldsOptions->isFieldIncluded('profile.education'));
        $this->assertEquals(['name'], $fieldsOptions->getIncludedFields('profile'));

        // child fields
        $fieldsOptions = $this->builder
            ->setFieldExcluded('profile', ['workHistory', 'education'])
            ->build();

        $this->assertFalse($fieldsOptions->isFieldIncluded('profile.workHistory'));
        $this->assertFalse($fieldsOptions->isFieldIncluded('profile.education'));

        // complex + nested child fields
        $fieldsOptions = $this->builder
            ->setFieldExcluded(null, ['name'])
            ->setFieldExcluded('profile', ['workHistory'])
            ->setFieldExcluded('profile.education', ['id', 'name'])
            ->build();

        $this->assertFalse($fieldsOptions->isFieldIncluded('name'));
        $this->assertFalse($fieldsOptions->isFieldIncluded('profile.workHistory'));
        $this->assertFalse($fieldsOptions->isFieldIncluded('profile.education.id'));
        $this->assertFalse($fieldsOptions->isFieldIncluded('profile.education.name'));
    }

    public function testSetFieldOption()
    {
        $fieldsOptions = $this->builder
            ->setFieldOption('test', 'limit', 1)
            ->setFieldOption('profile.education', 'limit', 2)
            ->setFieldOption('profile.education', 'offset', 5)
            ->build();

        $this->assertEquals(1, $fieldsOptions->getFieldOption('test', 'limit'));
        $this->assertEquals(2, $fieldsOptions->getFieldOption('profile.education', 'limit'));
        $this->assertEquals(5, $fieldsOptions->getFieldOption('profile.education', 'offset'));
    }

    public function testSetFieldOptions()
    {
        $educationOptions = ['limit' => 2, 'offset' => 5];
        $fieldsOptions = $this->builder
            ->setFieldOptions('test', ['limit' => 1])
            ->setFieldOptions('profile.education', $educationOptions)
            ->build();

        $this->assertEquals(1, $fieldsOptions->getFieldOption('test', 'limit'));
        $this->assertEquals($educationOptions, $fieldsOptions->getFieldOptions('profile.education'));
    }


    public function testSetGroupFields()
    {
        $fieldsOptions = $this->builder
            ->setFieldIncluded('id')
            ->setDefaultFieldsIncluded()
            ->setDefaultFieldsIncluded('profile.education')
            ->setAllFieldsIncluded('profile.workHistory')
            ->setGroupFieldIncluded('_basicInfo', 'profile')
            ->build();

        $this->assertTrue($fieldsOptions->hasDefaultFields());
        $this->assertTrue($fieldsOptions->hasDefaultFields('profile.education'));
        $this->assertFalse($fieldsOptions->hasDefaultFields('id'));
        $this->assertFalse($fieldsOptions->hasAllFields());
        $this->assertTrue($fieldsOptions->hasAllFields('profile.workHistory'));
        $this->assertTrue($fieldsOptions->hasGroupField('_basicInfo', 'profile'));

    }
}
