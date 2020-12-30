<?php
/**
 * Profiling
 *
 * @package profiling
 */

global $CONFIG, $FRAMEWORK_DIR;

if ($CONFIG['enable_profiling']) {
    Modules::uses(Modules::HTTP);
    Modules::uses(Modules::DEBUG);
    require "middleware.php";
    Http::register_middleware(new ProfilingMiddleware());
}
