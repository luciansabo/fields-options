<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\FieldsOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class FieldsOptionsTest extends TestCase
{
    public function testFromArray()
    {
        $data = $this->getSampleData();

        $options = FieldsOptions::fromArray($data);
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

    public function testFieldGroups()
    {
        $data = $this->getSampleData();
        $options = FieldsOptions::fromArray($data);
        $this->assertTrue($options->hasDefaultFields());
        $this->assertFalse($options->hasDefaultFields('profile'));
        $this->assertFalse($options->hasAllFields('profile'));
        $this->assertTrue($options->hasAllFields('profile.education'));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectErrorMessage('not available');
        $options->hasAllFields('profiles.missing');
    }

    public function testMissingFieldGetOptionsThrowsException()
    {
        $options = FieldsOptions::fromArray(['field' => true]);
        $this->expectException(\InvalidArgumentException::class);
        $options->getFieldOptions('missing');
    }

    public function testToArray()
    {
        $data = $this->getSampleData();
        $options = FieldsOptions::fromArray($data);
        $this->assertEquals($data, $options->toArray());
    }

    private function getSampleData()
    {
        return [
            '_defaults' => true,
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
