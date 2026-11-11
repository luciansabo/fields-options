<?php

namespace Lucian\FieldsOptions\Test\Fixture\Cycle;

use Lucian\FieldsOptions\Attribute\DefaultField;
use Lucian\FieldsOptions\Attribute\Group;
use Lucian\FieldsOptions\Test\Fixture\AbstractDto;

class AttributedNode extends AbstractDto
{
    #[DefaultField]
    public int $id;

    #[Group('_label')]
    public string $name;

    #[DefaultField]
    public ?AbstractDto $link = null;

    /** @var list<AbstractDto> */
    #[DefaultField]
    public array $items = [];

    public function __construct(int $id)
    {
        $this->id = $id;
        $this->name = 'node' . $id;
    }
}
