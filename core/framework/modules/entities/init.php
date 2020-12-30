<?php
/**
 * Entities Module
 *
 * @author <<<REDACTED>>>
 * @package entities
 */

// Base Abstract Classes and Traits
require "traits.php";
require "base.php";
require "core.php";

class Entities {

    protected static $entities = [];

    private static function load($section = '') {

        $coreFile = "${section}/core.php";
        $r = is_file($coreFile) && include($coreFile);

        return $r;
    }

    /**
     * @param string $section
     * @throws BaseEntityException
     */
    public static function uses($section = '') {
        global $PROJECT_DIR;
        if (!in_array($section, self::$entities)) {
            // Todo: Fix trailing slash in PROJECT DIR
            $r = self::load("${PROJECT_DIR}core/domain/entities/".$section);
            if ($r)
                self::$entities[] = $section;
            else
                throw new BaseEntityException("Failed to load entities section: $section");
        }
    }
}
