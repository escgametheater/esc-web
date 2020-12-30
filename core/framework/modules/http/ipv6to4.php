<?php

// vB3 ipv6 to ipv4 converter
// Coded up by Wootalyzer

if (!array_key_exists('REMOTE_ADDR', $_SERVER))
    return;

if (substr_count($_SERVER['REMOTE_ADDR'], ":") <= 1) {
    // This isn't an ipv6 address... abort!
    return;
}

function ipv6_to_ipv4($input)
{
    // Create a fake ipv4 address
    // that will "represent" the given
    // ipv6 address
    $hash = abs(crc32($input));
    if ($hash < 100000000)
        $hash += 100000000;
    $num1 = intval(substr($hash, 0, 3));
    $num2 = intval(substr($hash, 3, 3));
    $num3 = intval(substr($hash, 6, 3));
    return "224." .$num1. "." .$num2. "." .$num3;
}

function v4overv6_to_ipv4($input)
{
    if (strpos($input, ":") === false)
        return $input;
    $exploded = explode("::ffff:", $input);
    if (count($exploded) != 2 || $exploded[0] != "")
        return false;
    return $exploded[1];
}

function all_to_ipv4($input)
{
    $v4overv6_test = v4overv6_to_ipv4($input);
    if($v4overv6_test !== FALSE)
        return $v4overv6_test;
    return ipv6_to_ipv4($input);
}

$_SERVER['REMOTE_ADDR_V6'] = $_SERVER['REMOTE_ADDR'];
$_SERVER['REMOTE_ADDR'] = all_to_ipv4($_SERVER['REMOTE_ADDR']);
