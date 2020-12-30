<?php

class PermissionsException extends Exception {}

class Permissions
{
    protected $is_superadmin = false;
    protected $permissions = [];

    public static $guest_user_permissions_cache_key = SettingsManager::GNS_KEY_PREFIX.'._guest-permissions';

    /**
     * Fetch all rights informations for an user
     *
     * @access public
     */
    public function __construct(User $user, Request $request)
    {
        if (// Superadmins don't need rights
            $user->is_superadmin()
            // Banned users don't have rights
            || $user->is_banned()) {
            $this->is_superadmin = $user->is_superadmin;
            return;
        }

        if (!$user->is_authenticated()) {
            try {
                $guest_permissions = $request->cache[self::$guest_user_permissions_cache_key];
            } catch (CacheEntryNotFound $c) {
                $guest_permissions = self::fetch_from_db($request->db, $user);
                $c->set($guest_permissions, ONE_DAY);
            }
            $this->permissions = $guest_permissions;
        } else {
            $this->permissions = self::fetch_from_db($request->db, $user);

//            if ($user->session instanceof Session && !$user->session->sessionKeyExists(Session::KEY_PERMISSIONS)) {
//
//            } elseif ($user->session && $user->session->sessionKeyExists(Session::KEY_PERMISSIONS)) {
//                $user->session[Session::KEY_PERMISSIONS] = self::fetch_from_db($request->db, $user);
//            } else {
//                $user->session[Session::KEY_PERMISSIONS] = self::fetch_from_db($request->db, $user);
//            }
//
//            $this->permissions = $user->session[Session::KEY_PERMISSIONS];
        }

    }

    /**
     * @param Request $request
     */
    public static function bustGuestPermissionsCache(Request $request)
    {
        $request->cache->delete(self::$guest_user_permissions_cache_key, true);
    }



    /**
     * Fetch permissions from the database
     */
    protected static function fetch_from_db(DB $db, User $user)
    {
        $sqli = $db->get_connection(SQLN_SITE);
        $r = $sqli->query_read('
            SELECT r.name, gr.right_id, gr.access_level
            FROM `'.Table::GroupRights.'` AS gr
            JOIN  `'.Table::Rights.'` AS r
            ON gr.right_id = r.right_id
            WHERE usergroup_id IN ('.join(',', $user->groups).')
        ');


        $permissions = [];

        while($right = $r->fetch_assoc()) {
            if (array_key_exists($right['name'], $permissions))
                $permissions[$right['name']] |= intval($right['access_level']);
            else
                $permissions[$right['name']] = intval($right['access_level']);
        }

        return $permissions;
    }


    /**
     * Check right
     *
     * @param string right name
     * @param string access requested s-r-w (submit, read,write)
     * @return boolean
     */
    public function has($name, $access_requested = Rights::USE)
    {
        if (strlen($access_requested) == 0)
            throw new PermissionsException("Access requested is an empty string for {$name}");

        if ($this->is_superadmin)
            return true;

        if (!array_key_exists($name, $this->permissions))
            return false;

        $access_level = Rights::getAccessLevel($access_requested);

        return ($this->permissions[$name] & $access_level) == $access_level;
    }

    /**
     * @return array
     */
    public function getRawList()
    {
        return $this->permissions;
    }

    /**
     * Check rights (array version)
     *
     * @param array rights names
     * @param string acces level s-r-w (submit, read, write)
     * @return boolean
     */
    public function has_many(array $names, $acces_requested, $or = false)
    {
        foreach ($names as $name) {
            $access = $this->has($name, $acces_requested);
            if (!$or && !$access)
                return false;
            elseif ($or && $access)
                return true;
        }
        return !$or;
    }
}