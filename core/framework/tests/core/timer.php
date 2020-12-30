<?php

Modules::load_helper('timer');

$sec = 'Timer';

/*
 * Basic 1
 */
$t = new Timer('test');
ob_start();
$t->end();
$r = ob_get_clean();
$ref = "<!-- test took < 0.1 -->
";
simple_test($r == $ref, "$sec basic 1");

/*
 * Basic 2
 */
$t = new Timer('test');
sleep(1);
$r = $t->end();
simple_test($r > 1, "$sec basic 1");
