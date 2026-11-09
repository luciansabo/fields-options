<?php

namespace Lucian\FieldsOptions\Test\Fixture\Cycle;

use Lucian\FieldsOptions\Test\Fixture\AbstractDto;

class LegacyNode extends AbstractDto
{
    public int $id;

    public string $name;

    public ?AbstractDto $link = null;

    /** @var list<AbstractDto> */
    public array $items = [];

    public function __construct(int $id)
    {
        $this->id = $id;
        $this->name = 'node' . $id;
    }
}
