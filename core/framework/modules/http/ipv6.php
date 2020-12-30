<?php
/**
 * ip2bin
 *
 * Converts an IP from printable to a binary string
 */
function _ip2bin($ip, $format)
{
    return current(unpack($format, inet_pton($ip)));
}


function ip2bin($ip, $shorten_ip = false)
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        $bin = _ip2bin($ip, 'A4');
    elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
        $bin = _ip2bin($ip, $shorten_ip ? 'A8' : 'A16');
    else
        throw new Exception("Invalid IP address");

    return $bin;
}

/**
 * bin2ip
 *
 * Converts an IP from a binary string to printable
*/
function bin2ip($bin)
{
    if (strlen($bin) == 4)
        $ip = inet_ntop(pack("A4", $bin));
    elseif (strlen($bin) == 16)
        $ip = inet_ntop(pack("A16", $bin));
    elseif (strlen($bin) == 8)
        $ip = inet_ntop(pack("A16", $bin));
    else
        throw new Exception("Invalid IP address");

    return $ip;
}
