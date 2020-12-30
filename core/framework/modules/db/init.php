<?php
/**
 * DB abstraction init
 *
 * @package db
 */

class DBException extends Exception {}
class DBConnectionFailed extends Exception {}
class DBConnectionLost extends Exception {}
class DBDuplicateKeyException extends Exception {}

require "connection.php";
require "result.php";
require "base.php";
require "db.php";

if (Modules::is_loaded('http')) {
    require "middleware.php";
    Http::register_middleware(new DBMiddleware());
}
