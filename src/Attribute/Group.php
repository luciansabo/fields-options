<?php

namespace Lucian\FieldsOptions\Attribute;

use Lucian\FieldsOptions\FieldsOptions;

/** @psalm-api */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class Group
{
    /**
     * @param non-empty-string $name
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(public string $name)
    {
        if (!str_starts_with($name, '_')) {
            throw new \InvalidArgumentException(sprintf('Group name "%s" must start with an underscore', $name));
        }

        $reserved = [
            FieldsOptions::FIELD_DEFAULTS,
            FieldsOptions::FIELD_ALL,
            FieldsOptions::OPTIONS_KEY,
        ];
        if (in_array($name, $reserved, true)) {
            throw new \InvalidArgumentException(sprintf('Group name "%s" is reserved', $name));
        }
    }
}
