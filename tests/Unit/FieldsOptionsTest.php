<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\FieldsOptions;
use PHPUnit\Framework\TestCase;

class FieldsOptionsTest extends TestCase
{
    public function testConstruct()
    {
        $data = $this->getSampleData();

        $options = new FieldsOptions($data);
        $this->assertTrue($options->isFieldIncluded('id'));
        $this->assertFalse($options->isFieldIncluded('missing'));
        // field is present but value is false
        $this->assertFalse($options->isFieldIncluded('seo'));
        $this->assertTrue($options->isFieldIncluded('profile'));
        $this->assertTrue($options->isFieldIncluded('profile.education'));
        $this->assertEquals(
            $data['profile']['education']['_opt']['limit'],
            $options->getFieldOption('profile.education', 'limit')
        );
        $this->assertEquals(
            1,
            $options->getFieldOption('profile.education', 'missing', 1)
        );
        $this->assertNull(
            $options->getFieldOption('profile.education', 'missing')
        );

        $this->assertEquals($options->getFieldOptions('profile.education'), $data['profile']['education']['_opt']);
    }

    public function testConstructWithInvalidData()
    {
        $this->expectException(\RuntimeException::class);
        new FieldsOptions(['id' => true, 'profile' => 'invalid']);
    }

    public function testFieldGroups()
    {
        $data = $this->getSampleData();
        $options = new FieldsOptions($data);
        $this->assertTrue($options->hasDefaultFields());
        $this->assertFalse($options->hasDefaultFields('profile'));
        $this->assertTrue($options->hasGroupField('_basicInfo'));
        $this->assertFalse($options->hasAllFields('profile'));
        $this->assertTrue($options->hasAllFields('profile.education'));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not available');
        $options->hasAllFields('profiles.missing');
    }

    public function testMissingFieldGetOptionsThrowsException()
    {
        $options = new FieldsOptions(['field' => true]);
        $this->expectException(\InvalidArgumentException::class);
        $options->getFieldOptions('missing');
    }

    public function testGetIncludedFields()
    {
        $data = $this->getSampleData();
        $options = new FieldsOptions($data);
        $this->assertEquals(['_defaults', '_basicInfo', 'id', 'profile'], $options->getIncludedFields());
        $this->assertEquals(['education'], $options->getIncludedFields('profile'));
    }

    public function testToArray()
    {
        $data = $this->getSampleData();
        $options = new FieldsOptions($data);
        $this->assertEquals($data, $options->toArray());
    }

    private function getSampleData()
    {
        return [
            '_defaults' => true,
            '_basicInfo' => true,
            'id'       => true,
            'seo'      => false,
            'profile'  =>
                [
                    'education' =>
                        [
                            '_all' => true,
                            '_opt' =>
                                [
                                    'limit'   => 1,
                                    'sort'    => 'startYear',
                                    'sortDir' => 'asc',
                                ],
                        ],
                ],
        ];
    }
}
