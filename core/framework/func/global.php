<?php
/**
 * Global functions
 *
 */

/**
 * Create getallheaders
 *
 */
if (!function_exists('getallheaders')) {
    function getallheaders() {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
    return $headers;
    }
}

/**
 * Safe count()
 *
 * @param mixed $arr
 * @return integer
 */
function safe_count($arr)
{
    if (!is_array($arr))
        return 0;

    return count($arr);
}

function generate_guid(){
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Safe strlen()
 *
 * @param mixed $arr
 * @return integer
 */
function safe_len($str)
{
    if (!is_string($str))
        return 0;

    return strlen($str);
}


/**
 * Set variable to x, if it is null
 *
 * @param mixed $var
 * @param mixed $default_value
 */
function default_to(&$var, $default_value)
{
    if ($var === null)
        $var = $default_value;
}

function strip_bb($text, $replace_with_space = true)
{
    return preg_replace('!\[[^\]]*\]!', $replace_with_space ? ' ' : '', $text);
}

function floor_decimal($val, $decimals)
{
    $mult = pow(10, $decimals);
    return floor($val * $mult) / $mult;
}

function ceil_decimal($val, $decimals)
{
    $mult = pow(10, $decimals);
    return ceil($val * $mult) / $mult;
}

/**
 * @param $string
 * @param int $maxLength
 * @return string
 */
function truncate_string($string, $maxLength = 255)
{
    if (strlen($string) > $maxLength)
        $string = substr($string, 0, ($maxLength-1));

    return $string;
}

/**
 * @return string
 */
function uuidV4()
{
    $factory = new \Ramsey\Uuid\UuidFactory();

    $generator = new \Ramsey\Uuid\Generator\CombGenerator(
        $factory->getRandomGenerator(),
        $factory->getNumberConverter()
    );

    $factory->setRandomGenerator($generator);

    \Ramsey\Uuid\Uuid::setFactory($factory);
    return \Ramsey\Uuid\Uuid::uuid4()->toString();
}

/**
 * @return string
 */
function uuidV4HostName()
{
    return str_replace('-', '', uuidV4());
}


/**
 * Used to Convert CamelCase Strings to Snake Case Strings
 *
 * @param $input
 * @return string
 */
function camel_to_snake_case($input)
{
    preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
    $ret = $matches[0];
    foreach ($ret as &$match) {
        $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
    }
    return implode('_', $ret);
}

/**
 * @param int $minRand
 * @param int $maxRand
 * @param int $divMinRand
 * @param int $divMaxRand
 * @param int $decimals
 * @return float
 */
function generate_random_float($minRand = 1, $maxRand = TEXT, $divMinRand = 1, $divMaxRand = 999, $decimals = 2)
{
    return round(mt_rand($minRand, $maxRand) / mt_rand($divMinRand, $divMaxRand), $decimals);
}

/**
 * @param int $length
 * @return string
 */
function generate_random_string($length = 10, $allowedCharacters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {

    $charactersLength = strlen($allowedCharacters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $allowedCharacters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
