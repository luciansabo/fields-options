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
