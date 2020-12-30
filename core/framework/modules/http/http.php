<?php
/**
 * Http Handling
 *
 * @package http
 */

class Http
{
    /** @var Request  */
    protected static $request;
    /**
     * @var array|Middleware[]
     */
    public static $registeredMiddlewareClasses = [];

    /** @var Middleware[] $processResponseMiddlewareOnError */
    public static $processResponseMiddlewareOnError = [];

    public static $httpCodeSent;

    /**
     * Renders an error page
     * - source is from templates/errors
     * - does not use template system to be able
     * to render errors when templating is broken
     *
     * @param integer http error code
     * @param string exception message
     */
    public static function render_error($err_no, $reason = '', Request $request = null)
    {
        global $CONFIG;

        if (!$request)
            $request = Http::get_request();

        ob_start();
        if (is_int($err_no))
            http_status($err_no);
        $err_template = "{$CONFIG['templ']['dir']}/errors/${err_no}.html";
        echo file_get_contents($err_template);
        $show_error = ($CONFIG['debug'] || $request !== null && $request->debug);
        if ($show_error && $reason != '')
            echo '<h3><pre>'.$reason.'</pre></h3>';

        $response = new HttpResponse(ob_get_clean());

        return self::process_response_error_middleware($request, $response);
    }

    /**
     * @param Request $request
     * @param HttpResponse $response
     * @return HttpResponse|string
     */
    public static function process_response_error_middleware(Request $request, HttpResponse $response)
    {
        /** @var Middleware[] $errorMiddleware */
        $errorMiddleware = array_reverse(self::$processResponseMiddlewareOnError);

        foreach ($errorMiddleware as $middlewareClass => $middleware) {
            $r = $middleware->process_response($request, $response);

            if (array_key_exists($middlewareClass, self::$processResponseMiddlewareOnError)) {
                unset(self::$processResponseMiddlewareOnError[$middlewareClass]);
            }

            if ($r)
                return $r;
        }

        return $response;
    }

    /**
     * @param Request $request
     */
    public static function set_request(Request $request)
    {
        self::$request = $request;
    }

    /**
     * @return Request
     */
    public static function get_request()
    {
        return self::$request;
    }

    /**
     * Register a middleware
     * middleware classes call this function on load
     *
     * @param Middleware
     */
    public static function register_middleware(Middleware $middleware)
    {
        $className = get_class($middleware);
        self::$registeredMiddlewareClasses[$className] = $middleware;

        if ($middleware->processResponseOnServerError) {
            self::$processResponseMiddlewareOnError[$className] = $middleware;
        }
    }

    /**
     * Entry point for handling an http request
     * execute middleware and render content
     *
     * @param Request
     */
    public static function handle_request(Request $request)
    {
        $response = null;

        /**
         * Process request with middleware
         *
         * @var $middleware_stack Middleware[]
         */
        $middleware_stack = [];
        foreach(self::$registeredMiddlewareClasses as $middleware) {
            $response = $middleware->process_request($request);
            if ($response)
                break;
            else
                $middleware_stack[] = $middleware;
        }

        if ($response === null)
            $response = self::render_content($request);

        /**
         * Response middleware
         *
         * @var $middleware Middleware
         */
        foreach(array_reverse($middleware_stack) as $middleware) {
            $r = $middleware->process_response($request, $response);

            $middlewareClass = get_class($middleware);

            if (array_key_exists($middlewareClass, self::$processResponseMiddlewareOnError)) {
                unset(self::$processResponseMiddlewareOnError[$middlewareClass]);
            }

            if ($r)
                return $r;
        }

        return $response;
    }

    /**
     * Entry point for handling an http request
     *
     * @param Request $request
     * @return HtmlResponse|HttpResponse
     */
    private static function render_content(Request $request)
    {
        global $APP_DIR;

        // Get content to load from url
        $content_name = self::dispatch($request);

        // Load content
        require_once($APP_DIR.'/'.CONTENT_DIR.'/'.strtolower($content_name).'.php');
        $class_name = str_replace('-', '', $content_name);

        /** @var BaseContent $content */
        $content = new $class_name(new TemplateFactory($request));

        // Render response
        try {
            $response = $content->render($request);
        } catch (Http404 $e) {
            $response = $content->render_404($request, $e->getMessage(), $e->is_ajax());
        }

        if ($response === null)
            return Http::render_error(500, "No response returned by $content_name");

        return $response;
    }

    /**
     * Translates a url to a content name
     *
     * @param Request
     */
    private static function dispatch(Request $request)
    {
        $section = get_from_url($request, 1);
        if (!array_key_exists(ESCConfiguration::SECTIONS, $request->config))
            throw new eConfiguration('Sections are not defined');

        if (!$section)
            $section = 'default';

        return array_get($request->config[ESCConfiguration::SECTIONS], $section, 'Main');
    }
}
