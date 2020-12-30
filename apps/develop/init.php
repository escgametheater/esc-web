<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/8/18
 * Time: 11:28 AM
 */

define('APP_DIRECTORY', getcwd());

chdir('../../core/domain');

ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '200M');

require "../init.php";
