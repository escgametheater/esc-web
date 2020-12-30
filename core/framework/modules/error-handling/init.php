<?php
/**
 * Exception class system
 *
 * @package exceptions
 */

/**
 * Error classes
 */
class eConfiguration extends Exception {}

/**
 * Mails admins
 */
function mail_admins($url, $err)
{
    global $CONFIG;

    $lock = false;
    try {
        $r = Cache::get($lock_time, 'mail_lock');
        if ($r->shouldset == false)
            $lock = true;
    } catch(Exception $e) {
        // we catch everything to make sure we will receive
        // exceptions email in case something is broke with the cache
        echo $e->getMessage();
    }

    if ($lock == false) {
        $server_name = array_get($_ENV, "FCGI_WEB_SERVER_ADDRS", 'local');
        global $CONFIG;
        $title = "[".$CONFIG[ESCConfiguration::WEBSITE_NAME]."] Error: ".$url;
        $headers = 'From: '.$CONFIG[ESCConfiguration::WEBSITE_NAME]
            .'<website@'.$server_name.'.'.$CONFIG[ESCConfiguration::WEBSITE_DOMAIN].'>';
        if ($CONFIG['send_emails']) {
            mail($CONFIG['admins_email'], $title, $err, $headers);
        } else {
            echo "$title\n";
            echo $err;
        }

        Cache::set('mail_lock', '1', 60 + CACHE_REFRESH_TIME);
    }
}

/**
 * Error Handler
 * for uncatched exceptions
 * display a 500 error page and notify admins
 */
function exhandler($e)
{
    Modules::uses(Modules::DEBUG);
    Modules::uses(Modules::TWIG);
    Modules::uses(Modules::HTTP);

    global $CONFIG;

    $request = Http::get_request();

    $url = array_get($_SERVER, 'REQUEST_URI', 'URL Not Set');
    $referrer = array_get($_SERVER, 'HTTP_REFERER', 'URL Not Set');
    $error_msg = 'Exception: '.$e->getMessage()."\n"
        ."Referrer URL: $referrer\n"
        ."From: ".array_get($_SERVER, 'FCGI_WEB_SERVER_ADDRS', 'localhost')."\n";
    if ($request && isset($request->user) && $request->user->is_authenticated)
        $error_msg .= 'By '.$request->user->username.' ('.$request->user->id.')';
    $error_msg .= Debug::get_stack_string($e);

    // try {
    //     elog($error_msg);
    // } catch (DBException $e) {}

    // Send error to getsentry
    if (!$CONFIG['sentry_server'] && $CONFIG['send_emails']) {
        mail_admins($url, $error_msg);
    }

    if ($CONFIG['sentry_server']) {
        log_exception_in_sentry($e, $request);
    }

    if (PHP_SAPI == 'cli') {
        echo $e,"\n";
    } elseif ($CONFIG['debug'] || $request && $request->debug) {
        try {
            // output prevents the correct display of the error
            while (ob_get_level() - 1 > 0)
                ob_end_flush();
            @ob_end_flush();

            try {
                $message = $e->getMessage();
            } catch (Exception $e) {
                $message = $e;
            }

            if (!$request) {

                Modules::uses(Modules::HTTP);

                $request = Http::get_request();

                $content = 'Exception: '.htmlentities($e->getMessage());
                $content .= Debug::print_stack($e, false, false);

                $response = new HttpResponse($content, 500);

                if ($request)
                    $response = Http::process_response_error_middleware($request, $response);

                echo $response->getContent();
                return;
            }
            // Display stack trace
            $templ = new Template();
            $templ->assign([
                'images_url'        => $request->config[ESCConfiguration::IMAGES_URL],
                'static_url'        => $request->config[ESCConfiguration::STATIC_URL],
                'media_url'         => $request->config[ESCConfiguration::MEDIA_URL],
                'default_tree_time' => 'n/a',
                'memory_used'       => format_filesize(memory_get_peak_usage()),
                'sql_queries'       => Debug::sql_queries(),
                'cache'             => $request->cache,
                'memory_limit'      => format_filesize(ini_get("memory_limit")),
                'exception'         => get_class($e),
                'message'           => $message,
                'stack'             => Debug::get_stack($e),
                'server_name'       => $request->server['SERVER_NAME'],
                'getdata'           => $request->get,
                'postdata'          => $request->post,
                'cookies'           => $request->cookies,
                'ajax'              => $request->is_ajax(),
            ]);

            if (isset($request->user) && $request->user->is_authenticated) {
                $templ['user']     = $request->user;
                $templ['has_user'] = true;
            } else {
                $templ['has_user'] = false;
            }
            $content = $templ->render_response('errors/exception.twig', true)->content;
        } catch (Exception $e2) {
            $content = 'Exception: '.htmlentities($e->getMessage());
            $content .= Debug::print_stack($e, false, false);
            $content .= 'Exception: '.htmlentities($e2->getMessage());
            $content .= Debug::print_stack($e2, false, false);
        }

        $response = new HttpResponse($content, 500);

        $response = Http::process_response_error_middleware($request, $response);

        $response->setRequestId($request->requestId);

        $response->display();

    } else {
        $response = Http::render_error(500);
        $request = Http::get_request();

        if ($request && $response)
            $response->setRequestId($request->requestId);

        $response->display(/*ob_flush*/false);
    }

    exit();
}

/**
 * Error handler
 * - called on every error
 * - notify admins
 * - continue code execution
 */
function errhandler($errno, $errstr, $errfile, $errline, $errcontext, $errstack = [])
{
    global $FRAMEWORK_DIR, $PROJECT_DIR, $CONFIG;

    static $errortype = [
        E_ERROR             => 'Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parsing Error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Runtime Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'Deprecated',
    ];
    static $error_count = 0;

    if (!error_reporting())
        return;

    ++$error_count;
    if ($error_count > 6)
        return;

    $errfile = str_replace($FRAMEWORK_DIR, 'FRAMEWORK_DIR', $errfile);
    $errfile = str_replace($PROJECT_DIR, 'PROJECT_DIR', $errfile);

    $error_msg = 'PHP Error ['. ($errortype[$errno] ?? 'unknown')
        ."] ($errfile:$errline) $errstr";
    $url = array_get($_SERVER, 'REQUEST_URI', 'Unknown');
    $exception = new PHPError($errfile, $errline, $errstack);
    $stack = Debug::get_stack_string($exception);

    try {
        elog($url."\n".$error_msg."\n".$stack);
    } catch (DBException $e) {}

    // Send error to getsentry
    if (!$CONFIG['sentry_server'] && $CONFIG['send_emails'])
        mail_admins($url, $error_msg."\n".$stack);

    if (PHP_SAPI != 'cli') {
        if ($CONFIG['debug']) {
            $errorHtml = "";
            $errorHtml .= '<div style="background-color: white; text-align: left; font-size: 15px; padding: 8px; padding-bottom: 0px; border: 1px solid #44a; margin: 10px 120px; color: black;" class="phpexception">';
            $errorHtml .= '<div style="color: #000 !important;">'.htmlentities($error_msg).'</div>';
            $stackString = Debug::print_stack($exception, false, false);
            $errorHtml .= $stackString;
            $errorHtml .= '<div style="text-align: right; padding: 4px;"><a href="#" style="color: #44a; text-decoration: underline;" onclick="$(this).parent().parent().hide()">Close</a></div>';
            $errorHtml .= '</div>';

            if (!headers_sent()) {
                $response = new HtmlResponse($errorHtml, 500);
                echo $response->display();
            } else {
                echo $errorHtml;
            }

        }


    } else {
        echo $error_msg."\n".$stack;
    }
}

class PHPError
{
    private $file;
    private $line;
    private $stack;

    function __construct($file, $line, $stack)
    {
        $this->file = $file;
        $this->line = $line;
        $this->stack = $stack;
    }

    function getTrace()
    {
        return $this->stack;
    }

    function getLine()
    {
        return $this->line;
    }

    function getFile()
    {
        return $this->file;
    }
}

set_exception_handler('exhandler');
set_error_handler('errhandler');
