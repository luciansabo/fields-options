<?php

namespace Lucian\FieldsOptions\Attribute;

/**
 * Marks a property as not being a field: it is never requestable, exported, validated or walked.
 * Intended for bookkeeping state on DTO base classes; subclasses inherit it with the property.
 *
 * @psalm-api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Ignore
{
}
