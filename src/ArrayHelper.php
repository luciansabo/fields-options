<?php

namespace Lucian\FieldsOptions;

/**
 * Simplistic Dot notation array extractor
 */
class ArrayHelper
{
    public static function getValue(array $array, ?string $key, mixed $default = null): mixed
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

    public static function setValue(array &$array, ?string $key, mixed $value): void
    {
        if (is_null($key)) {
            $array = $value;
            return;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $lastKey = array_shift($keys);
        if ($lastKey !== null) {
            $array[$lastKey] = $value;
        }
    }
}
