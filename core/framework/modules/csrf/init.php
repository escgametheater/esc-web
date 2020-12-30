<?php
/**
 * Cross-site scripting security check
 *
 * @package csrf
 */

Modules::uses(Modules::HTTP);
require "middleware.php";

Http::register_middleware(new CSRFMiddleware());
