<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\ArrayHelper;
use PHPUnit\Framework\TestCase;

class ArrayHelperTest extends TestCase
{
    private const SAMPLE = [
        'tests' => 'testValue',
        'test2' => [
            'child1' => [
                'child2' => 'testChild1Child2Value'
            ]
        ]
    ];

    /**
     * @dataProvider getTestCases
     * @param string $path
     * @param $default
     * @param $expected
     * @return void
     */
    public function testGetValue(string $path, $default, $expected)
    {
        $this->assertEquals($expected, ArrayHelper::getValue(self::SAMPLE, $path, $default));
    }

    public function getTestCases(): array
    {
        return [
            ['tests', null, 'testValue'],
            ['missing', null, null],
            ['missing', 'default', 'default'],
            ['test2.child1', null, ['child2' => 'testChild1Child2Value']],
            ['test2.child1.missing', null, null],
            ['test2.child1.missing', 1, 1],
            ['test2.child1.child2', null, 'testChild1Child2Value'],
        ];
    }
}
