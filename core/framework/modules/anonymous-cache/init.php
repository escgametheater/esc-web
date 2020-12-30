<?php
/**
 * Cross-site scripting security check
 *
 * @package auth
 */

Modules::uses(Modules::HTTP);
Modules::uses(Modules::CACHE);

require "middleware.php";

Http::register_middleware(new AnonymousCacheMiddleware());
