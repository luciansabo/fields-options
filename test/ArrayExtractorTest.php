<?php

namespace Lucian\FieldsOptions\Test\Unit;

use Lucian\FieldsOptions\ArrayExtractor;
use PHPUnit\Framework\TestCase;

class ArrayExtractorTest extends TestCase
{
    private const SAMPLE = [
        'test' => 'testValue',
        'test2' => [
            'child1' => [
                'child2' => 'testChild1Child2Value'
            ]
        ]
    ];

    /**
     * @dataProvider getTestCases
     * @param string $path
     * @param $expected
     * @return void
     */
    public function testGetValue(string $path, $default, $expected)
    {
        $this->assertEquals($expected, ArrayExtractor::getValue(self::SAMPLE, $path, $default));
    }

    protected function getTestCases()
    {
        return [
            ['test', null, 'testValue'],
            ['missing', null, null],
            ['missing', 'default', 'default'],
            ['test2.child1', null, ['child2' => 'testChild1Child2Value']],
            ['test2.child1.missing', null, null],
            ['test2.child1.missing', 1, 1],
            ['test2.child1.child2', null, 'testChild1Child2Value'],
        ];
    }
}
