<?php
/**
 * Tests runner
 *
 * @version 1
 * @package tests
 */

require "settings.php";
require "../init.php";
Modules::uses(Modules::ERROR_HANDLING);
Modules::uses(Modules::LOGS);

$tests_passed  = 0;
$tests_total   = 0;

function println($text, $nl = "\n")
{
   echo $text,$nl;
}

function test_passed($name)
{
   global $tests_passed, $tests_total;
   $tests_passed  += 1;
   $tests_total   += 1;

   println("\033[01;32m[OK]\033[01;00m     ${name}");
}

function test_failed($name)
{
   global $tests_total;
   $tests_total += 1;

   println("\033[01;31m[FAILED]\033[01;00m ${name}");
}

function simple_test($test, $name)
{
   if ($test)
      test_passed("$name");
   else
      test_failed("$name");
}

global $WEBSITE_ROOT, $PROJECT_DIR;
// Run all tests

// Core
require_once "${FRAMEWORK_DIR}/tests/esc/file.php";
require_once "${FRAMEWORK_DIR}/tests/esc/bitfield.php";
require_once "${FRAMEWORK_DIR}/tests/esc/timer.php";

// Cache
require_once "${FRAMEWORK_DIR}/tests/modules/cache/run.php";

echo "Tests passed: ${tests_passed} / ${tests_total}\n";
