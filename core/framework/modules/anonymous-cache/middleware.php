<?php
/**
 * Caching Middleware
 * The purpose is to cache the html of a page for anonymous users. The page
 * will always be rendered the same an anonymous user, thus the idea to store
 * the result in the cache and pull instead of reprocessing the page, reducing
 * the load on the web servers.
 *
 * @package middleware
 * @subpackage cache
 */

class AnonymousCacheMiddleware extends Middleware
{
    private $url_finger;

    /**
     * Create a unique identifier from a url
     * We can't use the url directly since it can
     * contain special characters
     *
     * @param string $url
     * @return string
     */
    private static function request_finger($path, $lang = '', $country = '')
    {
        return "html-$lang-".md5($path);
    }

    /**
     * @see Middleware::process_request
     */
    public function process_request(Request $request)
    {
        if (count($request->post) == 0 && $request->config['enable_anonymous_cache']
            && (!Modules::is_loaded('auth') || !$request->user->is_authenticated))
        {
            $path = $request->path.($request->query ? '?'.$request->query : '');
            if (array_key_exists($path, $request->config['html_cache_pages'])) {
                $lang = Modules::is_loaded('i18n') ? $request->translations->get_lang() : '';
                $country = Modules::is_loaded('auth') ? $request->user->get_country() : '';
                $url_finger = self::request_finger($path, $lang, $country);
                try {
                    $response = $request->cache[$url_finger];
                    $response->set_header('X-Cached', $url_finger);
                    return $response;
                } catch (CacheEntryNotFound $c) {
                    $this->url_finger = $url_finger;
                }
            }
        }
    }

    /**
     * @see Middleware::process_response
     */
    public function process_response(Request $request, HttpResponse $response)
    {
        if (isset($this->url_finger))
            $request->cache->set($this->url_finger, $response);
    }
}
