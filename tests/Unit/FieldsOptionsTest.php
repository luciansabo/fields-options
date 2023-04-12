<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\FieldsOptions;
use PHPUnit\Framework\TestCase;

class FieldsOptionsTest extends TestCase
{
    private array $data;
    private FieldsOptions $options;

    public function setUp(): void
    {
        $this->data = $this->getSampleData();
        $this->options = new FieldsOptions($this->data);
    }

    public function testConstruct()
    {
        $this->assertTrue($this->options->isFieldIncluded('id'));
        $this->assertFalse($this->options->isFieldIncluded('missing'));
        // field is present but value is false
        $this->assertFalse($this->options->isFieldIncluded('seo'));
        $this->assertTrue($this->options->isFieldIncluded('profile'));
        $this->assertTrue($this->options->isFieldIncluded('profile.education'));
        $this->assertEquals(
            $this->data['profile']['education']['_opt']['limit'],
            $this->options->getFieldOption('profile.education', 'limit')
        );
        $this->assertEquals(
            1,
            $this->options->getFieldOption('profile.education', 'missing', 1)
        );
        $this->assertNull(
            $this->options->getFieldOption('profile.education', 'missing')
        );

        $this->assertEquals(
            $this->options->getFieldOptions('profile.education'),
            $this->data['profile']['education']['_opt']
        );
    }

    public function testConstructWithInvalidData()
    {
        $this->expectException(\RuntimeException::class);
        new FieldsOptions(['id' => true, 'profile' => 'invalid']);
    }

    public function testFieldGroups()
    {
        $this->assertTrue($this->options->hasDefaultFields());
        $this->assertFalse($this->options->hasDefaultFields('profile'));
        $this->assertFalse($this->options->hasDefaultFields('profile.workHistory'));
        $this->assertTrue($this->options->hasGroupField('_basicInfo'));
        $this->assertFalse($this->options->hasAllFields('profile'));
        $this->assertTrue($this->options->hasAllFields('profile.education'));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not available');
        $this->options->hasAllFields('profile.missing');
    }

    public function testMissingFieldGetOptionsThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->options->getFieldOptions('missing');
    }

    public function testGetIncludedFields()
    {
        $this->assertEquals(['_defaults', '_basicInfo', 'id', 'profile'], $this->options->getIncludedFields());
        $this->assertEquals(['education', 'workHistory'], $this->options->getIncludedFields('profile'));
    }

    public function testToArray()
    {
        $this->assertEquals($this->data, $this->options->toArray());
        $this->assertEquals($this->data['profile']['education'], $this->options->toArray('profile.education'));
        $this->assertEquals([], $this->options->toArray('missing'));
    }

    public function testIsFieldSpecified()
    {
        $this->assertTrue($this->options->isFieldSpecified('id'));
        $this->assertTrue($this->options->isFieldSpecified('profile.education'));
        $this->assertFalse($this->options->isFieldSpecified('profile.education.missing'));
        $this->assertFalse($this->options->isFieldSpecified('missing'));
    }

    private function getSampleData()
    {
        return [
            '_defaults' => true,
            '_basicInfo' => true,
            'id'         => true,
            'seo'        => false,
            'profile'    =>
                [
                    'education'   =>
                        [
                            '_all' => true,
                            '_opt' =>
                                [
                                    'limit'   => 1,
                                    'sort'    => 'startYear',
                                    'sortDir' => 'asc',
                                ],
                        ],
                    'workHistory' =>
                        [
                            '_defaults' => false,
                            'id'        => true
                        ]
                ],
        ];
    }
}
