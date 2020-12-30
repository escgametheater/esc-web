<?php
/**
 * Caching Middleware
 * The purpose is to cache the html of a page for anonymous users. The page
 * will always be rendered the same an anonymous user, thus the idea to store
 * the result in the cache and pull instead of reprocessing the page, reducing
 * the load on the web servers.
 *
 * @package online-stats
 */
class OnlineStatsMiddleware extends Middleware
{
    public function process_request(Request $request)
    {
        if ($request->config['stats_enable'] && $request->getRealIp()) {
            $user = Modules::is_loaded('auth') ? $request->user : false;

            OnlineStats::register_ip($request, $user);
        }
    }
}
