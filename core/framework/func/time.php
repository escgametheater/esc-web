<?php
/**
 * Time related functions
 *
 */

/**
 * Returns time_now is $time is invalid
 */
function safe_strtotime($time)
{
    $time = strtotime($time);
    if ($time == -1 || $time === false) {
        // strtotime() was not able to parse $time, use "now"
        $time = time();
    }
    return $time;
}

/**
 * @param $timeStamp1
 * @param $timeStamp2
 * @return bool
 */
function time_gte($timeStamp1, $timeStamp2)
{
    return strtotime($timeStamp1) >= strtotime($timeStamp2);
}

/**
 * @param $timeStamp1
 * @param $timeStamp2
 * @return bool
 */
function time_gt($timeStamp1, $timeStamp2)
{
    return strtotime($timeStamp1) > strtotime($timeStamp2);
}

/**
 * @param $timeStamp1
 * @param $timeStamp2
 * @return bool
 */
function time_lte($timeStamp1, $timeStamp2)
{
    return strtotime($timeStamp1) <= strtotime($timeStamp2);
}

/**
 * @param $timeStamp1
 * @param $timeStamp2
 * @return bool
 */
function time_lt($timeStamp1, $timeStamp2)
{
    return strtotime($timeStamp1) < strtotime($timeStamp2);
}

/**
 * @param $date
 * @return string
 */
function long_format_date($date)
{
    return format_date($date, 0, '%b %e, %Y @ %H:%M%p');
}


/**
 * String to date
 */
function format_date($date, $timezone_offset = 0, $date_format = null)
{
    if ($date_format === null) {
        global $CONFIG;
        $date_format = safe_get($CONFIG, 'date_format', "F j, Y");
    }

    $timezone_offset = intval($timezone_offset);
    $date = safe_strtotime($date) - date('Z') + 3600 * $timezone_offset;
    return strftime($date_format, $date);
}

/**
 * String to time
 */
function format_time($date, $timezone_offset = 0, $time_format = null)
{
    if ($time_format === null) {
        global $CONFIG;
        $time_format = safe_get($CONFIG, 'time_format', "g:i a");
    }

    $timezone_offset = (int)$timezone_offset;
    $date = safe_strtotime($date) - date('Z') + 3600 * $timezone_offset;
    return strftime($time_format, $date);
}

/**
 * @param string $date
 * @return bool
 */
function is_valid_date($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * @param $dateTime
 * @return bool
 */
function is_valid_date_time($dateTime, $format = "Y-m-d H:i:s")
{
    $dt = DateTime::createFromFormat($format, $dateTime);
    return $dt && $dt->format("Y-m-d H:i:s") === $dateTime;
}

/**
 * @param $time
 * @return bool
 */
function is_valid_time($time, $format = "H:i:s")
{
    $d = DateTime::createFromFormat($format, $time);
    return $d && $d->format($format) === $time;
}


/**
 * @return float
 */
function format_duration_as_minutes($durationTime)
{
    $time = explode(':', $durationTime);
    return ($time[0] * 60.0 + $time[1] * 1.0);
}


/**
 * @param $time
 * @param i18n|null $translations
 * @return string
 */
function format_nice_time_duration($time, i18n $translations = null)
{
    return format_duration(time_to_seconds($time), $translations);
}

function time_to_seconds($time)
{
    $timeExploded = explode(':', $time);
    if (isset($timeExploded[2])) {
        return $timeExploded[0] * 3600 + $timeExploded[1] * 60 + $timeExploded[2];
    }
    return $timeExploded[0] * 3600 + $timeExploded[1] * 60;
}

function format_time_string($string)
{
    return str_replace("00:", "", $string);
}

/**
 * Format duration
 * eg.
 * - 86400 to 1 day
 * - 3600 to 1 h
 */
function format_short_duration($dur, $translations = null, $limit = null)
{
    $output = [];

    // Years
    if ($dur > 86400*365) {
        $years = floor($dur / (86400*365));
        $year_str = $translations ? $translations->get('locale.duration-short-year') : '{val}y';
        $output[] = str_replace('{val}', $years, $year_str);
        $dur = $dur % (86400*365);
    }

    // Months
    if ($dur > 86400*30) {
        $months = floor($dur / (86400*30));
        $month_str = $translations ? $translations->get('locale.duration-short-month') : '{val}m';
        $output[] = str_replace('{val}', $months, $month_str);
        $dur = $dur % (86400*30);
    }

    // Days
    if ($dur > 86400) {
        $days = floor($dur / 86400);
        $day_str = $translations ? $translations->get('locale.duration-short-day') : '{val}d';
        $output[] = str_replace('{val}', $days, $day_str);
        $dur = $dur % 86400;
    }

    // Hours
    if ($dur > 3600) {
        $hours = floor($dur / 3600);
        $hour_str = $translations ? $translations->get('locale.duration-short-hour') : '{val}h';
        $output[] = str_replace('{val}', $hours, $hour_str);
        $dur = $dur % 3600;
    }

    // Minutes
    if ($dur > 60) {
        $minutes = floor($dur / 60);
        $minute_str = $translations ? $translations->get('locale.duration-short-minute') : '{val}min';
        $output[] = str_replace('{val}', $minutes, $minute_str);
        $dur = $dur % 60;
    }

    // Seconds
    if ($dur > 0) {
        $seconds = floor($dur);
        $second_str = $translations ? $translations->get('locale.duration-short-second') : '{val}s';
        $output[] = str_replace('{val}', $seconds, $second_str);
    }

    if ($limit)
        $output = array_slice($output, 0, $limit);

    return join(' ', $output);
}

/**
 * @param float $start_time
 * @param bool|true $includeDenominator
 * @return float|string
 */
function get_milliseconds_elapsed($start_time, $includeDenominator = true, $decimals = 2)
{
    $ms_time = round(((microtime(true) - $start_time) * 1000), $decimals);
    return $includeDenominator ? $ms_time.'ms' : $ms_time;
}

/**
 * Find the starting Monday for the given week (or for the current week if no date is passed)
 *
 * This is required as by default in PHP, strtotime considers Sunday the first day of a week,
 * making strtotime('Monday this week') on a Sunday return the adjacent Monday instead of the
 * previous one.
 *
 * @param null $date
 * @param int $timezoneOffset
 * @return DateTime|null
 */
function get_start_of_week_date($date = null, $timezoneOffset = 0)
{
    if ($date instanceof \DateTime) {
        $date = clone $date;
    } else if (!$date) {
        $date = new \DateTime();
    } else {
        $date = new \DateTime($date);
    }

    $date->setTime(0, 0, 0);

    if ($timezoneOffset !== 0)
        $date->modify("{$timezoneOffset} hour");

    if ($date->format('N') == 1) {
        // If the date is already a Monday, return it as-is
        return $date;
    } else {
        // Otherwise, return the date of the nearest Monday in the past
        // This includes Sunday in the previous week instead of it being the start of a new week
        return $date->modify('last monday');
    }
}

function time_elapsed_string(i18n $translations, $datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $values = [
        'year' => $diff->y,
        'month' => $diff->m,
        'week' => $weeks,
        'day' => $diff->d - $weeks * 7,
        'hour' => $diff->h,
        'minute' => $diff->i,
        'second' => $diff->s,
    ];

    $string = [];

    foreach ($values as $name => $val) {
        if ($val) {
            $pluralString = ($val > 1 ? 's' : '');

            if ($full) {
                $sourceString = "{$name}{$pluralString}";
                $translated = $translations->get($sourceString, $sourceString);
                $string[] = $val.' '.$translated;
            } else {
                $sourceString = "{duration} {$name}{$pluralString} ago";
                $string[] = $translations->get("locale.time.elapsed.{$name}{$pluralString}", $sourceString, ['duration' => $val]);
            }
        }
    }

    if (!$full)
        $string = array_slice($string, 0, 1);

    if ($string) {
        if (!$full)
            return implode(', ', $string);
        else
            return implode(', ', $string).' '.$translations->get('locale.time.ago', 'ago');
    } else {
        return $translations->get('locale.time.just-now', 'just now');
    }
}

function duration_string(i18n $translations, $datetime, $full = false) {
    $now = new DateTime;
    $future = new DateTime($datetime);
    $diff = $future->diff($now);

    $weeks = floor($diff->d / 7);
    $values = [
        'year' => $diff->y,
        'month' => $diff->m,
        'week' => $weeks,
        'day' => $diff->d - $weeks * 7,
        'hour' => $diff->h,
        'minute' => $diff->i,
        'second' => $diff->s,
    ];

    $string = [];

    foreach ($values as $name => $val) {
        if ($val) {
            $pluralString = ($val > 1 ? 's' : '');

            if ($full) {
                $sourceString = "{$name}{$pluralString}";
                $translated = $translations->get($sourceString, $sourceString);
                $string[] = $val.' '.$translated;
            } else {
                $sourceString = "{duration} {$name}{$pluralString}";
                $string[] = $translations->get("locale.time.duration.{$name}{$pluralString}", $sourceString, ['duration' => $val]);
            }
        }
    }

    if (!$full)
        $string = array_slice($string, 0, 1);

    if ($string) {
        if (!$full)
            return implode(', ', $string);
        else
            return implode(', ', $string);
    } else {
        return $translations->get('locale.time.just-now', 'just now');
    }
}

/**
 * Format duration
 * eg.
 * - 86400 to 1 day
 * - 3600 to 1 hour
 */
function format_duration($dur, $limit = null)
{
    $output = [];

    // Years
    if ($dur > 86400*365) {
        $years = floor($dur / (86400*365));
        $output[] = $years.' year'.($years != 1 ? 's' : '');
        $dur = $dur % (86400*365);
    }

    // Months
    if ($dur > 86400*30) {
        $months = floor($dur / (86400*30));
        $output[] = $months.' month'.($months != 1 ? 's' : '');
        $dur = $dur % (86400*30);
    }

    // Days
    if ($dur > 86400) {
        $days = floor($dur / 86400);
        $output[] = $days.' day'.($days != 1 ? 's' : '');
        $dur = $dur % 86400;
    }

    // Hours
    if ($dur > 3600) {
        $hours = floor($dur / 3600);
        $output[] = $hours.' hour'.($hours != 1 ? 's' : '');
        $dur = $dur % 3600;
    }

    // Minutes
    if ($dur > 60) {
        $minutes = floor($dur / 60);
        $output[] = $minutes.' minute'.($minutes != 1 ? 's' : '');
        $dur = $dur % 60;
    }

    // Seconds
    if ($dur > 0) {
        $seconds = floor($dur);
        $output[] = $seconds.' second'.($seconds != 1 ? 's' : '');
    }

    if ($limit) {
        $output = array_slice($output, 0, $limit);
    }

    return join(' ', $output);
}

/**
 * Format duration (only show 2 elements)
 * eg.
 * - 86400 to 1 day
 * - 3600 to 1 hour
 */
function format_simple_duration($time, $translations = null)
{
    return format_short_duration($time, $translations, 1);
}
