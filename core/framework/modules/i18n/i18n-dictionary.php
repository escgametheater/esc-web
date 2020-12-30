<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 5/20/18
 * Time: 5:16 PM
 */

class I18nDictionary {

    protected static $dictionary = [];

    /**
     * @param $dictionary
     */
    public static function register_dictionary($dictionary)
    {
        self::$dictionary = array_merge(self::$dictionary, $dictionary);
    }

    /**
     * @param $key
     * @return array|null
     */
    public static function lookup($key)
    {
        if (array_key_exists($key, self::$dictionary)) {
            return self::$dictionary[$key];
        } else {
            return null;
        }
    }
}

