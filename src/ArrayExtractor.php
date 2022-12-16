<?php

namespace Lucian\FieldsOptions;

/**
 * Simplistic Dot notation array extractor
 */
class ArrayExtractor
{
    public static function getValue(array $array, ?string $key, /*mixed*/ $default = null): mixed
    {
        if (is_string($key)) {
            $keys = explode('.', $key);
            foreach ($keys as $key) {
                if (!isset($array[$key])) {
                    return $default;
                }
                $array = &$array[$key];
            }

            return $array;
        } elseif (is_null($key)) {
            return $array;
        }

        return null;
    }
}
