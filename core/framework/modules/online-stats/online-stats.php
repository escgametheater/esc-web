<?php
/**
 * Online Stats functions
 *
 * @package online-stats
 */

class OnlineStats
{
    const default_cut = 900; // 15 minutes

    /**
     * @param Request $request
     * @param User|bool|false $user
     */
    public static function register_ip(Request $request, $user = false)
    {
        $ip = $request->getRealIp();
        $online_cut = array_get($request->config, 'stats_online_cut', OnlineStats::default_cut);
        try {
            $value = $request->cache["gc.stats.sessions.ip-${ip}"];
        } catch (CacheEntryNotFound $c) {
            self::online_visitors($request);
            self::online_visitors($request, 'registered');
            self::online_visitors($request, 'people');
            self::online_visitors($request, 'bots');
            self::online_visitors($request, 'staff');
            self::online_visitors($request, 'admin');
            $request->cache->increment("online_counter_all");

            if ($user !== false) {
                if ($user->is_bot)
                    $request->cache->increment("online_counter_bots");
                if (!$user->is_bot)
                    $request->cache->increment("online_counter_people");
                if ($user->is_authenticated)
                    $request->cache->increment("online_counter_registered");
                if ($user->is_staff)
                    $request->cache->increment("online_counter_staff");
                if ($user->has_group(6))
                    $request->cache->increment("online_counter_admin");
            }
            $c->set(1, $online_cut + CACHE_REFRESH_TIME);
        }
    }

    public static function online_visitors($request, $type = 'all')
    {
        $cut = array_get($request->config, 'stats_online_cut', OnlineStats::default_cut);
        try {
            $online_visitors = $request->cache["gc.stats.online_${type}_users"];
        } catch (CacheEntryNotFound $c) {
            $key = "online_counter_${type}";
            $online_visitors = $request->cache->read($key);
            if ($online_visitors === false) // counter not initialised: set counter to 1
                $online_visitors = 1;
            // reset counter
            $request->cache->write($key, '0', $cut + 2*CACHE_REFRESH_TIME);
            $c->set($online_visitors, $cut + CACHE_REFRESH_TIME);
        }
        return $online_visitors;
    }
}
