<?php
/**
 * Cache module middleware
 *
 * @package cache
 */
class CacheMiddleware extends Middleware
{
    public function process_request(Request $request)
    {
        $request->cache = Cache::get_cache($request->config['cache']);
        if (array_get($request->config, 'local_cache', ''))
            $request->local_cache = Cache::get_local_cache($request->debug);
            //$request->local_cache = Cache::create_cache($request->config['local_cache'], array_get($request->config, 'local_cache_options', null));
    }
}
