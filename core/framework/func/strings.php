<?php

/**
 * String functions
 *
 */

class Strings
{
    public static function endswith($str, $sub)
    {
        return (substr($str, strlen($str) - strlen($sub)) === $sub);
    }

    /**
     * Returns subject replaced with regular expression matchs
     *
     * @param mixed subject to search
     * @param array array of search => replace pairs
    */
    public static function pregtr($search, $replacePairs)
    {
        return preg_replace(array_keys($replacePairs), array_values($replacePairs), $search);
    }

    /**
     * str_replace() with count
     *
     * @param string $value
     * @param string $replace
     * @param integer $count
     * @return string
     */
    public static function str_replacec($value, $replace, $count)
    {
        return preg_replace('~'.preg_quote($value).'~', $replace, $count);
    }

        /**
         * Returns char at pos or the default character if
         * the string is shorter than pos
         *
         * @param string subject to search
         * @param string position
         * @param string default character
         * @return string
    */
    public static function get($string, $pos, $default)
    {
        return isset($string[$pos]) ? $string[$pos] : $default;
    }

    /**
     * Substr a string
     *
     * @param string $value
     * @param integer $num
     * @return string
     */
    public static function trim($value, $num)
    {
        if (strlen($value) > $num) {
            $strip = substr($value, 0, $num);
            return substr($strip, 0, strlen($strip) - strlen(strrchr($strip, ' '))).' ...';
        }
        return $value;
    }

    /**
     * @param null $string
     * @param int $indexToIncrement
     * @param string $delimeter
     * @return string
     */
    public static function incrementBuildVersion($string = null, $indexToIncrement = 2, $delimeter = '.')
    {
        if ($string && strpos($string, $delimeter) !== false) {
            $previousVersion = explode($delimeter, $string);

            $major = (int) $previousVersion[0];
            $minor = (int) $previousVersion[1];
            $build = (int) $previousVersion[2];

            // If we are incrementing the build version only
            if ($indexToIncrement == 2) {
                $build = $build+1;
            } elseif ($indexToIncrement == 1) {
                $minor = $minor+1;
                $build = 0;
            } elseif ($indexToIncrement == 0) {
                $major = $major+1;
                $minor = 0;
                $build = 0;
            } else {
                // Nothing to do
            }

            $versionNumber = "{$major}{$delimeter}{$minor}{$delimeter}{$build}";
        } else {
            $versionNumber = '0.0.1';
        }

        return $versionNumber;

    }
}