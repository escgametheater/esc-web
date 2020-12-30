<?php
/**
 * Validators
 * Data checking (helper functions)
 *
 */

use \libphonenumber\PhoneNumberUtil;


class Validators {

    public static function string($text, $raise = false)
    {
        $is_str = is_string($text);
        if (!$is_str && $raise)
            throw new Http404("string check failed for: ".$text." @ ".get_stack_string());
        return $is_str;
    }

    public static function float($text, $raise = false)
    {
        $is_float = is_float($text) || is_string($text) && $text != '' && preg_match("/^-?[0-9]+(\.[0-9]+)?$/", $text);
        if (!$is_float && $raise)
            throw new Http404("int check failed for: ".$text." @ ".get_stack_string());
        return $is_float;
    }

    public static function int($text, $raise = false)
    {
        $is_int = is_int($text) || is_string($text) && $text != '' && ctype_digit($text);
        if (!$is_int && $raise)
            throw new Http404("int check failed for: ".$text." @ ".get_stack_string());
        return $is_int;
    }

    public static function uint($text, $raise = false)
    {
        if (is_int($text)) {
            $is_valid = ($text >= 0);
        } elseif (ctype_digit($text)) {
            $value = intval($text);
            $is_valid = ($value >= 0);
        } else {
            $is_valid = false;
        }
        if (!$is_valid && $raise)
            throw new Http404("uint check failed for: ".$text." @ ".Debug::get_stack_string());

        return $is_valid;
    }

    public static function id($id, $raise = false)
    {
        $is_valid = (is_int($id) || ctype_digit($id)) && (intval($id) > 0);
        if ($is_valid == false && $raise)
            throw new Http404("Id check failed for: ".$id." @ ".Debug::get_stack_string(null, 1));

        return $is_valid;
    }

    public static function string_not_empty($text)
    {
        return trim($text) !== '';
    }

    public static function email($s)
    {
        $lastDot = strrpos($s, '.');
        $ampersat = strrpos($s, '@');
        $length = strlen($s);
        return !(
            $lastDot === false ||
            $ampersat === false ||
            $length === false ||
            $lastDot - $ampersat < 3 ||
            $length - $lastDot < 3
        );
    }

    /**
     * @param $phoneNumber
     * @param null $countryId
     * @return bool
     */
    public static function phone($phoneNumber, $countryId = null)
    {
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $numberProto = $phoneUtil->parse($phoneNumber, $countryId);
            $isValid = $phoneUtil->isValidNumber($numberProto);

        } catch (\libphonenumber\NumberParseException $e) {
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * Check picture filename
     *
     * @param string $name
     * @return boolean
     */
    public static function picture_filename($name)
    {
        return preg_match('~^(.+)\.(jpg|jpeg|gif|png)$~i', $name);
    }

    /**
     * Check picture mime from getimagesize()
     *
     * @param array $mime
     * @return boolean
     */
    function picture_mime($mime)
    {
        return preg_match('~^image/(.*)~', $mime['mime']) && in_array($mime[2], [1, 2, 3]);
    }
}
