<?php

require "../helpers/file.php";

$sec = 'File Wrapper';

$cache_directory = $CONFIG['cache_directory'];

$test_key = 'file-test';

/*
 * Lock Test
 * exclusive lock
 *
 */
$file = new File("${cache_directory}/${test_key}");
$file->open('w');
$r = $file->lockwrite();
simple_test($r == true, "$sec Lock, exclusive");
$file->close();

/*
 * Lock Test
 * lock unlock lock (same file object)
 *
 */
$file = new File("${cache_directory}/${test_key}");
$file->open('w');
$file->lockwrite();
$file->unlock();
$r = $file->lockwrite();
simple_test($r == true, "$sec Lock, lock unlock lock (same file object)");
$file->close();

/*
 * Lock Test
 * lock unlock lock (2 file objects)
 *
 */
$file = new File("${cache_directory}/${test_key}");
$file->open('w');
$file->lockwrite();
$file->unlock();

$file2 = new File("${cache_directory}/${test_key}");
$file2->open('w');
$r = $file2->lockwrite();

simple_test($r == true, "$sec Lock, lock unlock lock (same file object)");
$file->close();
$file2->close();

/*
 * Lock Test
 * exclusive locking twice the same file
 *
 */
$file = new File("${cache_directory}/${test_key}");
$file->open('w');
$file->lockwrite();

$file2 = new File("${cache_directory}/${test_key}");
$file2->open('w');
$r = $file2->lockwrite();

simple_test($r == false, "$sec Lock, exclusive locking twice the same file");

$file->close();
$file2->close();
