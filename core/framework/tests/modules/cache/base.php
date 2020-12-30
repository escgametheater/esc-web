<?php

$test_key = 'test-cache';

/*
 * Get/Set string Test
 *
 */
$value = 'hello';

$c->set($test_key, $value);
$r = $c->get($var, $test_key);
if (!$r->isset || $var != $value) {
   test_failed("$sec Get/Set string");
   echo "got '$var', expected '$value'\n";
} else {
   test_passed("$sec Get/Set string");
}

/*
 * Get/Set array Test
 *
 */
$value = ['a' => 1, 'b' => 1];

$c->set($test_key, $value);
$r = $c->get($var, $test_key);
if (!$r->isset || $var != $value) {
   test_failed("$sec Get/Set array");
   echo "got '$var', expected '$value'\n";
} else {
   test_passed("$sec Get/Set array");
}
unset($var);

/*
 * Get/Set object Test
 *
 */
require_once "objs.php";
$value = new CacheTestObj('5');

$c->set($test_key, $value);
$r = $c->get($var, $test_key, null, false);
if (!$r->isset || $var != $value) {
   test_failed("$sec Get/Set object");
   echo "got '$var', expected '$value'\n";
   var_dump($var);
   die();
} else {
   test_passed("$sec Get/Set object");
}

/*
 * Delete Test
 *
 */
$c->set($test_key, 'hello');
$c->delete($test_key);
$r = $c->get($var, $test_key, null, false);
simple_test($r->isset == false, "$sec Delete");

/*
 * Flush Test
 *
 */
$c->set($test_key, 'hello');
$c->flush();
$r = $c->get($var, $test_key, null, false);
simple_test($r->isset == false, "$sec Flush");

/*
 * Needsset Test
 * close to expiration
 *
 */
$c->set($test_key, 'hello', 50);
$c->flush_local();
$r = $c->get($var, $test_key, null, false);
//var_dump($r);
simple_test($r->needsset == true, "$sec Needsset, close to expiration");

/*
 * Needsset Test
 * plain get, not close to expiration
 *
 */
$c->set($test_key, 'hello', 3600);
$r = $c->get($var, $test_key, null, false);
simple_test($r->needsset == false, "$sec Needsset, not close to expiration");

/*
 * Needsset Test
 * not set
 *
 */
$c->delete($test_key);
$r = $c->get($var, $test_key, null, false);
simple_test($r->needsset == true, "$sec Needsset, not set");

/*
 * Lock Test
 * lock twice in a row should fail
 *
 */
$c->lock($test_key);
$r = $c->lock($test_key);
simple_test($r == false, "$sec Lock, twice in a row");

/*
 * Lock Test
 * lock, unlock, lock
 *
 */
$c->lock($test_key);
$c->unlock($test_key);
$r = $c->lock($test_key);
$c->unlock($test_key);
simple_test($r == true, "$sec Lock, lock unlock lock");
