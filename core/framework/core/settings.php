<?php
/**
 * Base Settings
 *
 */

/**
 * Fetch a setting from config file
 * @param string $name Setting name
 * @return string Setting value
 *
 */
function get_setting($name, $default = "")
{
    global $CONFIG;
    return array_get($CONFIG, $name, $default);
}

/**
 * Fetch a setting from the db
 * @param string $name Setting name
 * @return string Setting value
 *
 */
function get_db_setting($name, $default = "")
{
    $sql = DB::inst(SQLN_SITE);

    $r = $sql->query_first(
        'SELECT value'
        .' FROM '.Table::Settings
        .' WHERE name = "'.$sql->escape_string($name).'"'
        .' LIMIT 1'
    );

    if ($r === null)
        return $default;

    return array_get($r, 'value', $default);
}
