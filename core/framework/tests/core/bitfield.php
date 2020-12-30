<?php

require "../../helpers/bitfield.php";

$sec = 'Bitfield';


/*
 * Basic 1
 */
$b = BitField::create();
$b[0] = 1;
$r = $b[0];
simple_test($r == true, "$sec basic 1");

/*
 * Basic 2
 */
$b = BitField::create();
$r = $b[0];
simple_test($r == false, "$sec basic 2");

/*
 * Constructor 1
 */
$var = 0;
$b = new BitField($var);
$b[0] = 1;
$r = $b[0];
simple_test($r == true, "$sec constructor 1");

/*
 * Constructor 2
 */
$var = 1;
$b = new BitField($var);
$r = $b[0];
simple_test($r == true, "$sec constructor 2");

/*
 * Remove 1
 */
$b = BitField::create();
$b[0] = 0;
$r = $b[0];
simple_test($r == false, "$sec remove 1");

/*
 * Remove 2
 */
$b = BitField::create();
$b[0] = 1;
$b[0] = 0;
$r = $b[0];
simple_test($r == false, "$sec remove 2");

/*
 * Toggle 1
 */
$b = BitField::create();
$b->toggle(0);
$r = $b[0];
simple_test($r == true, "$sec toggle 1");
$b->toggle(0);
$r = $b[0];
simple_test($r == false, "$sec toggle 2");
$b->toggle(0);
$r = $b[0];
simple_test($r == true, "$sec toggle 3");

/*
 * Toggle 4
 */
$b = BitField::create();
$b[0] = 1;
$b->toggle(0);
$r = $b[0];
simple_test($r == false, "$sec toggle 4");

/*
 * Get value
 */
$b = BitField::create();
$b[0] = 1;
$r = $b->get_value();
simple_test($r == 1, "$sec get value");
