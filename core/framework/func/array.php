<?php
/**
 * Array Functions
 *
 */

function array_extract($key, array $array, $includeNull = true)
{
    $els = [];
    foreach ($array as $row) {
        if ($row[$key] != null || $includeNull)
            $els[] = $row[$key];
    }
    return $els;
}

/**
 * @param DBManagerEntity[] $array
 * @return array
 */
function extract_owner_ids(array $array, $unique = true)
{
    $els = [];
    foreach ($array as $entity)
        $els[] = $entity->getOwnerId();

    return $unique ? array_unique($els) : $els;
}
/**
 * @param DBManagerEntity[] $array
 * @return array
 */
function extract_pks(array $array, $unique = true)
{
    $els = [];
    foreach ($array as $entity)
        $els[] = $entity->getPk();

    return $unique ? array_unique($els) : $els;
}

function unique_array_extract($key, array $array, $includeNull = true)
{
    return array_unique(array_extract($key, $array, $includeNull));
}
function unique_array_merge(array $array1, array $array2)
{
    return array_unique(array_merge($array1, $array2));
}

function array_get($array, $key, $default)
{
    return array_key_exists($key, $array) ? $array[$key] : $default;
}

function safe_get(array $array, $key, $default = '', $continue = false)
{
    if (array_key_exists($key, $array) == false)
        throw new Exception('Missing key '.$key);

    return $array[$key];
}

function array_find(array $array, $key, $value, $key_to_fetch = 1)
{
    foreach ($array as $el) {
        if ($el[$key] == $value)
            return $el[$key_to_fetch];
    }
    return false;
}

function array_index(array $array, $index_field)
{
    $indexed_array = [];
    foreach ($array as &$row)
        $indexed_array[$row[$index_field]] = $row;

    return $indexed_array;
}