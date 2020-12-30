<?php
/**
 * CSRF middleware
 *
 * @package csrf
 */
class CSRFMiddleware extends Middleware
{
    /**
     * Cross site request forgery check
     * checks of the domain is authorized for POSTs query
     * if it is a POST query
     *
     * @param Request
     */
    public function process_request(Request $request)
    {
        if (strtoupper($request->method) == 'POST' && array_key_exists('HTTP_REFERER', $request->server)) {
            $http_host = preg_replace('#:80$#', '', trim($request->config[ESCConfiguration::WEBSITE_DOMAIN]));

            // Referrer string
            $referrer_parts = @parse_url($request->server['HTTP_REFERER']);
            $ref_port = array_get($referrer_parts, 'port', 80);
            $ref_host = array_get($referrer_parts, 'host', '') .($ref_port != 80 ? ":$ref_port" : '');

            // Allowed referrers
            if (array_key_exists(ESCConfiguration::ALLOWED_REFERRERS, $request->config))
                $allowed = preg_split('#\s+#', $request->config[ESCConfiguration::ALLOWED_REFERRERS], -1, PREG_SPLIT_NO_EMPTY);
            else
                $allowed = [];
            $allowed[] = preg_replace('#^www\.#i', '', $http_host);

            // Check
            $pass_ref_check = false;

            foreach ($allowed as $host) {
                if (preg_match('#' . preg_quote($host, '#') . '$#siU', $ref_host)) {
                    $pass_ref_check = true;
                    break;
                }
            }

            if(!$pass_ref_check && !$request->settings()->is_dev())
                throw new HttpDenied('Domain not allowed: '.$ref_host);
        }
    }
}
