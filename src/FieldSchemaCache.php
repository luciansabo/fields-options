<?php

namespace Lucian\FieldsOptions;

/**
 * Property group lookups shared across FieldSchema copies.
 * FieldsOptions threads one instance through forField() / withPrototype(), matching NestedTypeResolver.
 *
 * @internal
 * @psalm-api
 */
final class FieldSchemaCache
{
    /**
     * @var array<string, list<string>>
     */
    private array $groupsByProperty = [];

    /**
     * @return list<string>|null cached groups, or null on miss (including “no groups”)
     */
    public function getPropertyGroups(string $propertyKey): ?array
    {
        if (!array_key_exists($propertyKey, $this->groupsByProperty)) {
            return null;
        }

        return $this->groupsByProperty[$propertyKey];
    }

    /**
     * @param list<string> $groups
     * @return list<string>
     */
    public function setPropertyGroups(string $propertyKey, array $groups): array
    {
        return $this->groupsByProperty[$propertyKey] = $groups;
    }
}
