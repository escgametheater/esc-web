<?php
/**
 * class Rights
 * various funcs
 *
 * @package auth
 */

class Rights
{
    const sessionmap_prefix = 'sessionmap';

    const SUBMIT = 's';
    const USE = 'u';

    const READ = 'r';
    const MODERATE = 'm';

    const WRITE = 'w';
    const ADMINISTER = 'a';

    /**
     * Converts an access string to a bitfield
     *
     * @static
     * @param string $acces_level s-r-w (submit,read,write)
     * @return boolean
     */
    public static function getAccessLevel($access)
    {
        $access_level = BitField::create();

        $access = str_split($access);
        foreach ($access as $c) {
            switch($c) {
                // 2 models here: read,write,submit or admin,moderator,user
                case self::SUBMIT: // submit
                case self::USE: // use
                    $access_level[2] = 1;
                    break;
                case self::READ: // read
                case self::MODERATE: // moderate
                    $access_level[1] = 1;
                    break;
                case self::WRITE: // write
                case self::ADMINISTER: // administer
                    $access_level[0] = 1;
                    break;
            }
        }

        return $access_level->get_value();
    }

    /**
     * Generate a random password
     */
    public static function generate_password()
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz1234567890';
        $max = strlen($chars) - 1;
        $size = rand(7, 12);

        $password = '';
        for ($i = 0; $i < $size; $i++) {
            $pos = rand(0, $max);
            $password .= $chars[$pos];
        }

        return $password;
    }

    /**
     * check $userid against superadmins list
     */
    public static function is_superadmin($userid)
    {
        return in_array($userid, self::getSuperAdminIds());
    }

    /**
     * @return array
     */
    public static function getSuperAdminIds()
    {
        global $CONFIG;
        $superAdminIds = explode(',', $CONFIG['superadmins']);

        foreach ($superAdminIds as $key => $value) {
            $superAdminIds[$key] = trim($value);
        }

        return $superAdminIds;
    }

    /**
     * @return array
     */
    public static function getStaffGroupIds()
    {
        global $CONFIG;

        $staffGroupIds = explode(',', $CONFIG['staff_groups']);

        foreach ($staffGroupIds as $key => $value) {
            $staffGroupIds[$key] = trim($value);
        }

        return $staffGroupIds;
    }

    /**
     * check $group against staff groups list
     */
    public static function is_staff($groups)
    {
        //$staff_groups = explode(',', get_db_setting('staff_groups'));
        $staff_groups = self::getStaffGroupIds();

        return count(array_intersect($staff_groups, $groups)) > 0;
    }

    public static function set_session_map(CacheBackend $cache, $userid, $sessionid)
    {
        global $CONFIG;
        return $cache->set(
            self::sessionmap_prefix.'-'.$userid,
            $sessionid,
            $CONFIG['session_timeout']
        );
    }

    public static function clear_session(CacheBackend $cache, $userid)
    {
        try {
            $sessionid = $cache[self::sessionmap_prefix.'-'.$userid];
            $cache->delete('session-'.$sessionid);
        } catch (CacheEntryNotFound $c) {
            $c->delete();
        }
    }

}
