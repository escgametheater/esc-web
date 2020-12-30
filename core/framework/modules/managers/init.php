<?php
/**
 * Query generation system
 *
 * @package managers
 */

class ObjectNotFound extends Exception
{
    private $object_type;

    public function __construct($object_type)
    {
        $this->object_type = $object_type;
    }

    public function __toString()
    {
        return sprintf("ObjectNotFound(table=\"%s\")", $this->object_type);
    }
}

// Manager DB Query Builder
require "query.php";

// DB Filters
require "backends/sql/fields.php";
require "backends/sql/filters.php";
require "q.php";

// Manager + DB Filter Services Locator
require "services.php";

// Base Manager Classes
require "manager.php";

