<?php

declare(strict_types=1);

namespace Helpers\Array;

/**
 * Anchor Framework
 *
 * ArrayCollection provides static methods to perform common, advanced array operations.
 * It features dot-notation access, deep mapping, and object-aware filtering/sorting.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use InvalidArgumentException;
use stdClass;

class ArrayCollection
{
    /**
     * Set an array item using dot notation for nested keys.
     */
    public static function set(array $array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (! is_array($current)) {
                $current = [];
            }
            $current = &$current[$k];
        }

        $current = $value;

        return $array;
    }

    /**
     * Gets a specific item from the array, supporting dot notation for nested access.
     */
    public static function value(array $array, string $key, mixed $default = null): mixed
    {
        if (! str_contains($key, '.')) {
            return $array[$key] ?? $default;
        }

        $segments = explode('.', $key);
        $current = $array;

        foreach ($segments as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return $default;
            }
        }

        return $current;
    }

    /**
     * Gets a value from the array, or sets and returns a default if the key doesn't exist.
     * Supports dot notation for nested keys. The array is modified by reference.
     *
     * @param array  $array   The array to search (passed by reference)
     * @param string $key     The key to retrieve (supports dot notation)
     * @param mixed  $default The default value to set if key doesn't exist (can be a callable)
     *
     * @return mixed The existing value or the newly set default
     */
    public static function getOrSet(array &$array, string $key, mixed $default): mixed
    {
        // Check if the key exists
        if (static::has($array, $key)) {
            return static::value($array, $key);
        }

        // Resolve callable defaults
        $resolvedDefault = is_callable($default) ? $default() : $default;

        // Set the default value using dot notation
        $array = static::set($array, $key, $resolvedDefault);

        return $resolvedDefault;
    }

    /**
     * Calculates the mean (average) value of an array. Alias for avg().
     */
    public static function mean(array $array, ?string $field = null): float
    {
        return static::avg($array, $field);
    }

    /**
     * Calculates the median (middle value) of an array of numbers.
     * Supports arrays of arrays/objects via an optional field name.
     */
    public static function median(array $array, ?string $field = null): float
    {
        $values = $field ? static::pluck($array, $field) : $array;

        $numbers = array_filter($values, 'is_numeric');
        $count = count($numbers);

        if ($count === 0) {
            return 0.0;
        }

        sort($numbers);

        $middleIndex = (int) floor($count / 2);

        if ($count % 2 === 1) {
            return (float) $numbers[$middleIndex];
        }

        $lowMiddle = $numbers[$middleIndex - 1];
        $highMiddle = $numbers[$middleIndex];

        return (float) (($lowMiddle + $highMiddle) / 2);
    }

    /**
     * Calculates the mode (most frequently occurring value) of an array.
     * Returns an array since there can be multiple modes.
     */
    public static function mode(array $array, ?string $field = null): array
    {
        $values = $field ? static::pluck($array, $field) : $array;
        $countMap = array_count_values($values);

        if (empty($countMap)) {
            return [];
        }

        $maxValue = max($countMap);
        $modes = array_keys($countMap, $maxValue, true);

        return $modes;
    }

    /**
     * Calculates the statistical variance of the values.
     */
    public static function variance(array $array, ?string $field = null): float
    {
        $values = $field ? static::pluck($array, $field) : $array;
        $numbers = array_filter($values, 'is_numeric');
        $count = count($numbers);

        if ($count < 2) {
            return 0.0;
        }

        $mean = self::avg($numbers);
        $sumOfSquaredDifferences = 0;

        foreach ($numbers as $number) {
            $sumOfSquaredDifferences += ($number - $mean) ** 2;
        }

        return (float) ($sumOfSquaredDifferences / ($count - 1));
    }

    /**
     * Calculates the standard deviation of the values (square root of the variance).
     */
    public static function stdDev(array $array, ?string $field = null): float
    {
        $variance = self::variance($array, $field);

        return sqrt($variance);
    }

    /**
     * Checks if a key exists in an array (supports dot notation) and is NOT null.
     */
    public static function has(array $array, string $key): bool
    {
        $marker = new stdClass();

        return static::value($array, $key, $marker) !== $marker;
    }

    /**
     * Checks if all of the provided dot-notation keys exist in the array.
     */
    public static function hasAll(array $array, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! static::has($array, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a key exists in an array, even if its value is null.
     */
    public static function exists(array $array, string $key): bool
    {
        return array_key_exists($key, $array);
    }

    /**
     * Removes specified keys from an array, supporting dot notation for nested removal.
     */
    public static function forget(array $array, string|array $keys): array
    {
        $keys = (array) $keys;
        $currentArray = $array;

        foreach ($keys as $key) {
            $keySegments = explode('.', $key);
            $current = &$currentArray;
            $last_key = array_pop($keySegments);

            foreach ($keySegments as $k) {
                if (is_array($current) && array_key_exists($k, $current)) {
                    $current = &$current[$k];
                } else {
                    continue 2;
                }
            }

            if (is_array($current) && array_key_exists($last_key, $current)) {
                unset($current[$last_key]);
            }
        }

        return $currentArray;
    }

    /**
     * Filters an array of arrays/objects to return only items where a field strictly or loosely matches a value.
     */
    public static function where(array $array, string $field, mixed $value, bool $strict = true): array
    {
        return array_filter($array, function ($data) use ($field, $value, $strict) {
            $field_value = is_object($data) ? ($data->{$field} ?? null) : ($data[$field] ?? null);

            return $strict ? ($field_value === $value) : ($field_value == $value);
        });
    }

    /**
     * Filters an array of arrays/objects to only include items whose field value is present in a reference collection.
     */
    public static function whereIn(array $array, string $field, array $collection, bool $strict = true): array
    {
        return array_filter($array, function ($data) use ($field, $collection, $strict) {
            $field_value = is_object($data) ? ($data->{$field} ?? null) : ($data[$field] ?? null);

            return in_array($field_value, $collection, $strict);
        });
    }

    /**
     * Filters an array of arrays/objects to only include items whose field value is NOT present in a reference collection.
     */
    public static function whereNotIn(array $array, string $field, array $collection, bool $strict = true): array
    {
        return array_filter($array, function ($data) use ($field, $collection, $strict) {
            $field_value = is_object($data) ? ($data->{$field} ?? null) : ($data[$field] ?? null);

            return ! in_array($field_value, $collection, $strict);
        });
    }

    /**
     * Reduces an array to a single value using a callback function.
     */
    public static function reduce(array $array, callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($array, $callback, $initial);
    }

    /**
     * Calculates the average value of a given field across an array of arrays/objects.
     */
    public static function avg(array $array, ?string $field = null): float
    {
        $values = $field ? static::pluck($array, $field) : $array;
        $numericValues = array_filter($values, 'is_numeric');
        $count = count($numericValues);

        if ($count === 0) {
            return 0.0;
        }

        return (float) (array_sum($numericValues) / $count);
    }

    /**
     * Divides the array into two new arrays based on the result of a callback.
     */
    public static function partition(array $array, callable $callback): array
    {
        $passed = [];
        $failed = [];

        foreach ($array as $key => $item) {
            if ($callback($item, $key)) {
                $passed[$key] = $item;
            } else {
                $failed[$key] = $item;
            }
        }

        return [$passed, $failed];
    }

    /**
     * Checks if a value (or a list of values) exists in an array.
     * Returns true/false or an array of found keys/values depending on $return_key.
     */
    public static function contains(array $array, mixed $value, bool $return_key = false): bool|int|string|array|null
    {
        if (is_array($value)) {
            $results = [];

            foreach ($value as $item) {
                $result = static::contains($array, $item, $return_key);

                if ($result !== false && $result !== null && $result !== []) {
                    $results[] = $return_key ? $result : $item;
                }
            }

            return $return_key ? array_values(array_filter($results)) : (count($results) > 0);
        }

        $in_array = in_array($value, $array, true);

        if ($return_key) {
            return $in_array ? array_search($value, $array, true) : null;
        }

        return $in_array;
    }

    /**
     * Executes a callback on each item and allows stopping the iteration by returning false.
     */
    public static function each(array $array, callable $callback): array
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }

        return $array;
    }

    /**
     * Extracts values of a specific field from an array of arrays or objects (pluck operation).
     */
    public static function pluck(array $array_list, string $fieldName = 'id'): array
    {
        if (empty($array_list)) {
            return [];
        }

        return array_map(function ($option) use ($fieldName) {
            if (is_array($option)) {
                return $option[$fieldName] ?? null;
            } elseif (is_object($option)) {
                return $option->{$fieldName} ?? null;
            }

            return null;
        }, $array_list);
    }

    /**
     * Highly simplified method to build a new associative array structure.
     */
    public static function build(array $array, string $keyField, string|array|null $valueField = null): array
    {
        $result = [];
        foreach ($array as $item) {
            $itemArray = (array) $item;
            $key = static::value($itemArray, $keyField);

            if ($key !== null) {
                if ($valueField === null) {
                    $value = $item;
                } elseif (is_array($valueField)) {
                    $value = static::only($itemArray, $valueField);
                } else {
                    $value = static::value($itemArray, $valueField);
                }
                $result[(string) $key] = $value;
            }
        }

        return $result;
    }

    /**
     * Executes a callback on each item and uses its return value (which must be a single [key => value] array)
     * to build a new associative array.
     */
    public static function mapWithKeys(array $array, callable $callback): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $pair = $callback($value, $key);
            if (is_array($pair) && count($pair) === 1) {
                $result[key($pair)] = current($pair);
            }
        }

        return $result;
    }

    /**
     * Builds an associative array where the key is a concatenation of multiple field values.
     */
    public static function indexByCompoundKey(array $array, array $keyFields, ?string $valueField = null, string $separator = '-', bool $asList = false): array
    {
        $result = [];

        foreach ($array as $item) {
            $itemArray = (array) $item;
            $compoundKeyParts = [];
            foreach ($keyFields as $field) {
                $compoundKeyParts[] = $itemArray[$field] ?? '';
            }
            $compoundKey = implode($separator, $compoundKeyParts);

            $value = $valueField ? ($itemArray[$valueField] ?? null) : $item;

            if ($asList) {
                $result[$compoundKey][] = $value;
            } else {
                $result[$compoundKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Flattens a multi-dimensional array recursively.
     */
    public static function flatten(array $array, bool $preserve_keys = true): array
    {
        $flattened = [];

        $flattener = function ($value, $key) use (&$flattened, $preserve_keys, &$flattener) {
            if (is_array($value)) {
                array_walk($value, $flattener);
            } elseif ($preserve_keys && ! is_int($key)) {
                $flattened[$key] = $value;
            } else {
                $flattened[] = $value;
            }
        };

        array_walk($array, $flattener);

        return $flattened;
    }

    /**
     * Recursively applies a callback function to all values in a multi-dimensional array.
     */
    public static function map(array $array, callable $function): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = static::map($value, $function);
            } else {
                $result[$key] = $function($value);
            }
        }

        return $result;
    }

    /**
     * Recursively applies a callback function to every element in the array.
     */
    public static function mapDeep(array $array, callable $callback, bool $on_no_scalar = false): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = static::mapDeep($value, $callback, $on_no_scalar);
            } elseif (is_scalar($value) || $on_no_scalar) {
                $array[$key] = call_user_func($callback, $value);
            }
        }

        return $array;
    }

    /**
     * Groups an array of arrays/objects by a specified key's value.
     */
    public static function groupByKey(array $array_list, string $key = 'id'): array
    {
        $result = [];

        foreach ($array_list as $item) {
            $value = null;

            if (is_object($item)) {
                $value = $item->{$key} ?? null;
            } elseif (is_array($item)) {
                $value = $item[$key] ?? null;
            }

            if ($value !== null) {
                $result[(string) $value][] = $item;
            }
        }

        return $result;
    }

    /**
     * Removes duplicate arrays/objects from a list based on a value from a target key.
     */
    public static function uniqueBy(array $data, string $target_key): array
    {
        $uniqueKeys = [];
        $result = [];

        foreach ($data as $key => $value) {
            $field_value = is_object($value) ? ($value->{$target_key} ?? null) : ($value[$target_key] ?? null);

            if ($field_value !== null && ! isset($uniqueKeys[$field_value])) {
                $uniqueKeys[$field_value] = true;
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Removes duplicate values from an array, with an option to preserve keys.
     */
    public static function unique(array $array, bool $keep_keys = false): array
    {
        return $keep_keys ? array_unique($array) : array_values(array_unique($array));
    }

    /**
     * Sorts an array of arrays or objects by a specified field using an internal comparison function.
     */
    public static function sortByField(array $records, string $field, bool $reverse = false): array
    {
        usort($records, function ($a, $b) use ($field, $reverse) {
            $valA = is_object($a) ? ($a->{$field} ?? null) : ($a[$field] ?? null);
            $valB = is_object($b) ? ($b->{$field} ?? null) : ($b[$field] ?? null);

            if ($valA == $valB) {
                return 0;
            }

            if ($reverse) {
                return ($valA < $valB) ? 1 : -1;
            } else {
                return ($valA > $valB) ? 1 : -1;
            }
        });

        return $records;
    }

    /**
     * Sorts an associative array based on a list of keys provided in the order array.
     */
    public static function orderKeys(array $array, array $order): array
    {
        $result = [];

        foreach ($order as $value) {
            if (array_key_exists($value, $array)) {
                $result[$value] = $array[$value];
                unset($array[$value]);
            }
        }

        return $result + $array;
    }

    /**
     * Replaces array keys with specified new keys in a list of arrays/objects or a single array/object.
     */
    public static function replaceKeys(array $array, array $replace, bool $multiple = true): array|object
    {
        $generate_key = function (array $arr) use ($replace): array {
            $data = [];
            foreach ($arr as $key => $value) {
                $data[($replace[$key] ?? $key)] = $value;
            }

            return $data;
        };

        if ($multiple) {
            $data = [];
            foreach ($array as $value) {
                $is_object = is_object($value);
                $value_arr = $is_object ? (array) $value : $value;
                $processed_value = $generate_key($value_arr);

                $data[] = $is_object ? (object) $processed_value : $processed_value;
            }

            return $data;
        }

        $is_object = is_object($array);
        $result = $generate_key(($is_object ? (array) $array : $array));

        return $is_object ? (object) $result : $result;
    }

    /**
     * Replaces values in the array based on a map of rules, where the replacement
     */
    public static function replaceValues(array $array, array $replacements): array
    {
        foreach ($array as $key => $value) {
            if (array_key_exists($key, $replacements)) {
                $replacementRule = $replacements[$key];

                if (is_callable($replacementRule)) {
                    $array[$key] = $replacementRule($value, $key, $array);
                } else {
                    $array[$key] = $replacementRule;
                }
            }
        }

        return $array;
    }

    /**
     * Returns a new array/object containing only the specified keys from the source data.
     */
    public static function only(mixed $data, array $filter): array|object
    {
        $array = [];
        $is_object = is_object($data);
        $arr = (array) $data;

        foreach ($filter as $value) {
            $array[$value] = $arr[$value] ?? null;
        }

        return $is_object ? (object) $array : $array;
    }

    /**
     * Returns a new array/object excluding the specified keys from the source data.
     */
    public static function exclude(mixed $data, array $exclude): array|object
    {
        $is_object = is_object($data);
        $arr = (array) $data;

        foreach ($exclude as $value) {
            unset($arr[$value]);
        }

        return $is_object ? (object) $arr : $arr;
    }

    /**
     * Gets the key of the first element in an array.
     */
    public static function firstKey(array $array): string|int|null
    {
        return array_key_first($array);
    }

    /**
     * Gets the first value of an array without modifying the internal pointer.
     */
    public static function first(array $array): mixed
    {
        return reset($array);
    }

    /**
     * Gets the key of the last element in an array.
     */
    public static function lastKey(array $array): string|int|null
    {
        return array_key_last($array);
    }

    /**
     * Gets the last value of an array without modifying the internal pointer.
     */
    public static function last(array $array): mixed
    {
        return end($array);
    }

    /**
     * Gets the maximum value in an array.
     */
    public static function max(array $array): mixed
    {
        return max($array);
    }

    /**
     * Gets the minimum value in an array.
     */
    public static function min(array $array): mixed
    {
        return min($array);
    }

    /**
     * Adds an element to the beginning of an array while preserving keys.
     */
    public static function prependKeyed(array $array, string $key, mixed $value): array
    {
        return [$key => $value] + $array;
    }

    /**
     * Reverses the order of elements in an array.
     */
    public static function reverse(array $array): array
    {
        return array_reverse($array, true);
    }

    /**
     * Exchanges all keys with their corresponding values in an array.
     */
    public static function flip(array $array): array
    {
        return array_flip($array);
    }

    /**
     * Combines two arrays, using the first array's elements as keys and the second array's elements as values.
     */
    public static function combine(array $keys, array $values): array
    {
        if (count($keys) !== count($values)) {
            throw new InvalidArgumentException('Arrays must have the same number of elements for array_combine.');
        }

        return array_combine($keys, $values);
    }

    /**
     * Zips two arrays together, interleaving their values to form an array of pairs.
     */
    public static function zip(array $array, array $other): array
    {
        $result = [];
        $keys = array_keys($array);

        foreach ($keys as $key) {
            $result[$key] = [$array[$key], $other[$key] ?? null];
        }

        return $result;
    }

    /**
     * Returns all the keys of an array.
     */
    public static function getKeys(array $array): array
    {
        return array_keys($array);
    }

    /**
     * Returns a numerically indexed array (resets keys).
     */
    public static function values(array $array): array
    {
        return array_values($array);
    }

    /**
     * Removes an item from the array by key (non-destructive).
     */
    public static function remove(array $array, string $key): array
    {
        if (array_key_exists($key, $array)) {
            unset($array[$key]);
        }

        return $array;
    }

    /**
     * Pushes a new item (or array of items) onto the end of the array.
     */
    public static function push(array $array, mixed $item): array
    {
        return array_merge($array, (array) $item);
    }

    /**
     * Pushes an item (or array of items) onto the beginning of the array.
     */
    public static function prepend(array $array, mixed $item): array
    {
        return (array) $item + $array;
    }

    /**
     * Merges two arrays non-destructively, prioritizing keys from the original $array.
     */
    public static function attach(array $array, array $item): array
    {
        return $array + $item;
    }

    /**
     * Re-indexes the array numerically starting from 0, discarding original keys.
     */
    public static function rebase(array $array): array
    {
        return array_values($array);
    }

    /**
     * Calculates the sum of values in an array.
     */
    public static function sum(array $array): float|int
    {
        return array_sum($array);
    }

    /**
     * Splits an array into smaller chunks of a specified size, optionally preserving keys.
     */
    public static function chunk(array $array, int $size, bool $preserveKeys = false): array
    {
        return array_chunk($array, $size, $preserveKeys);
    }

    /**
     * Takes a specified number of elements from the beginning of the array (positive limit) or end (negative limit).
     */
    public static function take(array $array, int $limit): array
    {
        return array_slice($array, 0, $limit, true);
    }

    /**
     * Selects a slice of the array starting from an offset and taking a limit of elements.
     */
    public static function limit(array $array, int $limit, int $offset = 0): array
    {
        return array_slice($array, $offset, $limit, true);
    }

    /**
     * Retrieves a specified number of random elements from the array.
     */
    public static function random(array $array, int $count = 1): mixed
    {
        if ($count <= 0 || empty($array)) {
            return ($count === 1) ? null : [];
        }

        $keys = (array) array_rand($array, $count);

        if ($count === 1) {
            return $array[$keys[0]];
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $array[$key];
        }

        return $result;
    }

    /**
     * Shuffles (randomises) the items in the array (non-destructive).
     */
    public static function shuffle(array $array): array
    {
        shuffle($array);

        return $array;
    }

    /**
     * Retrieves and returns the first item from the array (non-destructive).
     */
    public static function shift(array $array): mixed
    {
        return array_shift($array);
    }

    /**
     * Removes and returns the last item from the array (non-destructive).
     */
    public static function pop(array $array): mixed
    {
        return array_pop($array);
    }

    /**
     * Counts the number of elements in an array.
     */
    public static function count(array $array, bool $recursive = false): int
    {
        return count($array, $recursive ? COUNT_RECURSIVE : COUNT_NORMAL);
    }

    /**
     * Checks if an array is empty (i.e., count($array) === 0).
     */
    public static function isEmpty(array $array): bool
    {
        return empty($array);
    }

    /**
     * Checks if an array is associative (non-sequential numeric keys).
     */
    public static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Checks if all elements in the array are themselves arrays.
     */
    public static function isArrayOfArrays(array $array): bool
    {
        foreach ($array as $item) {
            if (! is_array($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if an array or object is multi-dimensional (contains nested arrays or objects).
     */
    public static function isMultiDimensional(mixed $data): bool
    {
        if (is_array($data) || is_object($data)) {
            foreach ((array) $data as $value) {
                if (is_array($value) || is_object($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks if two arrays contain the exact same elements (regardless of order or key).
     */
    public static function isEqual(array $array1, array $array2): bool
    {
        if (count($array1) !== count($array2)) {
            return false;
        }

        sort($array1);
        sort($array2);

        return $array1 === $array2;
    }

    /**
     * Recursively cleans an array by removing empty strings, nulls, and empty nested arrays.
     */
    public static function cleanDeep(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::cleanDeep($value);
            }

            if ($array[$key] === '' || is_null($array[$key]) || (is_array($array[$key]) && empty($array[$key]))) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Cleans an array by a custom rule (e.g., removing nulls, empty strings).
     */
    public static function clean(array $haystack, ?callable $callback = null): array
    {
        return array_filter($haystack, $callback);
    }

    /**
     * Converts a numerically indexed list into an associative array where keys equal values.
     */
    public static function toAssoc(array $list, ?string $keyField = null, ?string $valueField = null): array
    {
        if ($keyField !== null && $valueField !== null) {
            return static::build($list, $keyField, $valueField);
        }

        $assoc = [];
        foreach ($list as $item) {
            if (is_scalar($item)) {
                $assoc[$item] = $item;
            }
        }

        return $assoc;
    }

    /**
     * Wraps a non-array value in an array; returns an empty array for null.
     */
    public static function wrap(mixed $object): array
    {
        if (is_null($object)) {
            return [];
        }

        return is_array($object) ? $object : [$object];
    }

    public static function toComment(array $data, int $level = 0): string
    {
        $lines = [];
        $indent = str_repeat('    ', $level);

        foreach ($data as $key => $value) {
            $formattedKey = is_int($key) ? '[' . $key . ']' : (string) $key;

            if (is_array($value)) {
                $lines[] = $indent . $formattedKey . ': [';
                $lines[] = self::toComment($value, $level + 1);
                $lines[] = $indent . ']';
            } else {
                if (is_bool($value)) {
                    $formattedValue = $value ? 'true' : 'false';
                } elseif (is_null($value)) {
                    $formattedValue = 'null';
                } elseif (is_string($value) && (str_contains($value, PHP_EOL) || strlen($value) > 80)) {
                    $formattedValue = '...' . PHP_EOL . str_repeat('    ', $level + 1) . "'" . str_replace(PHP_EOL, PHP_EOL . str_repeat('    ', $level + 1), $value) . "'";
                } else {
                    $formattedValue = (string) $value;
                }

                $lines[] = $indent . $formattedKey . ': ' . $formattedValue;
            }
        }

        return implode(PHP_EOL, $lines);
    }

    public static function safeImplode(mixed $body, string $glue = ' '): string
    {
        if (! is_array($body)) {
            return self::toSafeString($body);
        }

        $stringified_elements = array_map([self::class, 'toSafeString'], $body);

        return implode($glue, $stringified_elements);
    }

    private static function toSafeString(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            $json = @json_encode($value);

            return $json === false ? '[Data Error]' : $json;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return '';
    }
}
