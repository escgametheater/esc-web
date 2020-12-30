<?php
/**
 * Twig templating module
 *
 * @package twig
 */

global $CONFIG;

Twig_Autoloader::register(true);
require_once 'translation_tag.php';
require_once 'translation_filter.php';
require_once 'html_substr.php';


class TemplateFactory
{
    protected $request;

    public function __construct(Request $request = null)
    {
        $this->request = $request;
    }

    public function get()
    {
        if ($this->request)
            $context = ['request' => $this->request];
        else
            $context = null;

        return new Template($context);
    }


}

function xmldate($date)
{
    return date('Y-m-d', strtotime($date));
}

/**
 * @param $string
 * @return string
 */
function transliterate_csharp_types($string)
{
    $transliteratedString = '';

    switch ($string) {
        case "Int32":
            $transliteratedString = 'Int';
            break;
        case "Single":
            $transliteratedString = 'Float';
            break;
    }

    if ($transliteratedString)
        return $transliteratedString;
    else
        return $string;
}

/**
 * @param $string
 * @param string $delimiter
 * @param int $index
 * @return string
 */
function string_explode($string, $delimiter = ',', $index = 0)
{
    $parts = explode($delimiter, $string);

    if (isset($parts[$index]))
        return $parts[$index];
    else
        return '';
}


/**
 * @param $parameterType
 * @return array|float|int|null|string
 */
function generateExampleVttValue($parameterType)
{
    $value = null;

    switch ($parameterType) {

        case "Int32":
            $value = mt_rand(1, TEXT);
            break;
        case "String":
            $value = generate_random_string(6, 'abcdefghijklmnopqrstuvwxyz');
            break;
        case "Double":
            $value = generate_random_float();
            break;
        case "Single":
            $value = generate_random_float();
            break;
        case "Vector3":
            $value = [
                'x' => generate_random_float(),
                'y' => generate_random_float(),
                'z' => generate_random_float()
            ];
            break;
    }

    return $value;
}

/**
 * @param $vttMethod
 * @return string
 */
function render_vtt_example($vttMethod)
{
    $example = [
        'type' => 'call',
        'method' => $vttMethod['MethodInfo']['Name'],
        'parameters' => []
    ];

    foreach ($vttMethod['ParametersInfo'] as $vttParam) {
        $parameterName = $vttParam['Name'];
        $rawParameterType = $vttParam['ParameterType'];

        $parameterType = string_explode(string_explode($rawParameterType), '.', 1);


        $example['parameters'][$parameterName] = generateExampleVttValue($parameterType);
    }

    return json_encode($example);
}

/**
 * @param $start
 * @param $addTime
 * @return string
 */
function add_time($start, $addTime)
{
    $dt = new DateTime($start);
    $dt->modify($addTime);
    return $dt->format(SQL_DATETIME);
}

class BaseTemplateException extends Exception {}

class Template implements ArrayAccess
{

    /**
     * Template path
     *
     * @var string template path
     */
    protected $template;

    /**
     * Enable profiling
     *
     * @var boolean switch to enable/disable profiling
     */
    protected $profiling = false;

    /**
     * Show template time in debug bar
     * switch to enable/disable the append to the content of a javascript that
     * will update the debug bar template time display
     *
     * @var boolean enable/disable
     */
    protected $append_time = false;

    /**
     * Time used to generate the default tree
     *
     * @var int
     */
    public $default_tree_time = 0;

    /**
     * Time used to generate the default tree
     *
     * @var array
     */
    public $context = [];

    /** @var Twig_Environment  */
    public $twig;

    /**
     * Initialize using parameters from the settings file
     */
    public function __construct($context = null)
    {
        global $CONFIG;

        if (!array_key_exists('templ', $CONFIG))
            throw new eConfiguration('Missing Template Engine Configuration');

        $config = $CONFIG['templ'];

        if (!array_key_exists('dir', $config)
            || !is_dir($config['dir'])) {
            throw new eConfiguration('Invalid Templates Directory: '.array_get($config, 'dir', ''));
        }
        if (!array_key_exists('compiled_dir', $config))
            throw new eConfiguration('Invalid Compiled Templates Directory: '.array_get($config, 'compiled_dir', ''));

        if (!is_dir($config['compiled_dir'])) {
            if (!mkdir($config['compiled_dir'], 0700, true))
                throw new eConfiguration('Impossible to create compiled templates directory: '.$config['compiled_dir']);
        }

        $this->template_dir      = $config['dir'];
        $this->compiled_dir      = $config['compiled_dir'];
        $this->config_dir        = array_get($config, 'configs_dir', '');
        $this->cache_dir         = array_get($config, 'cache_dir', '');
        // debug console
        $this->debugging         = array_get($config, 'debug', false);
        $this->caching           = array_get($config, 'caching', false);
        $this->compile_check     = array_get($config, 'compile_check', true);
        $this->error_unassigned  = true;

        $loader = new Twig_Loader_Filesystem($this->template_dir);
        $twig = new Twig_Environment($loader, [
            'cache' => $this->caching ? $this->compiled_dir : false,
            'auto_reload' => true,
            'debug' => $this->debugging,
        ]);
        $this->twig = $twig;

        if (array_get($config, 'autoescape', true)) {
            $escaper = new Twig_Extension_Escaper('html');
            $twig->addExtension($escaper);
        }

        $this->add_function('bool_field', 'bool_field_tag');
        $this->add_function('process_time', ['Debug', 'processing_time_tag']);
        $this->add_function('init_time', ['Debug', 'init_time_tag']);
        $this->add_function('dump', ['Debug', 'dump_tag']);
        $this->add_function('add_time', "add_time");
        // Functions to pass on as is in the templates
        $functions = ['defined', 'count', 'is_array', 'in_array', 'json_decode', 'array_key_exists'];
        foreach ($functions as $func_name)
            $this->add_function($func_name, $func_name);

        $this->add_filter('time', 'format_time');
        $this->add_filter('getclass', 'get_class');
        $this->add_filter('date', 'format_date');
        $this->add_filter('xmldate', 'xmldate');
        $this->add_filter('date_format', 'format_date');
        $this->add_filter('long_date_format', 'long_format_date');
        $this->add_filter('explode', 'string_explode');
        $this->add_filter('csharp_types', 'transliterate_csharp_types');
        $this->add_filter('bool', 'bool_field');
        $this->add_filter('vtt_example', 'render_vtt_example');
        $this->add_filter('filesize', 'format_filesize');
        $this->add_filter('duration', 'format_duration');
        $this->add_filter('number_format', 'number_format');
        $this->add_filter('short_duration', 'format_short_duration');
        $this->add_filter('build_query', 'build_query');
        $this->add_filter('merge', 'array_merge');
        $this->add_filter('clean_time', "format_time_string");
        $this->add_filter('large_number', 'format_large_number');

        $this->add_filter('t', 'translation_filter', ['needs_context' => true]);
        $this->add_filter('duration_time', 'translated_duration_filter', ['needs_context' => true]);

        $filters = ['count', 'dump', 'base64_encode', 'array_keys',
                    'urlencode', 'html_substr', 'ucfirst', 'json_decode', 'json_encode'];
        foreach ($filters as $filter_name)
            $this->add_filter($filter_name, $filter_name);

        $this->add_filter('regex_replace', function($string, $pattern, $replace) {
            return preg_replace($pattern, $replace, $string);
        });

        $this->add_test('AnonymousUser', function($value) {
            return $value instanceof AnonymousUser;
        });

        $this->add_test('array', function($value) {
           return is_array($value);
        });

        $twig->addTokenParser(new TranslationTag_TokenParser());
        $twig->addExtension(new Twig_Extensions_Extension_Text());

        if ($context)
            $this->assign($context);
    }

    public function add_function($name, $func)
    {
        $filter = new Twig_SimpleFunction($name, $func);
        $this->twig->addFunction($filter);
    }

    public function add_filter($name, $func, $options = [])
    {
        $filter = new Twig_SimpleFilter($name, $func, $options);
        $this->twig->addFilter($filter);
    }

    public function add_test($name, $func)
    {
        $test = new Twig_SimpleTest($name, $func);
        $this->twig->addTest($test);
    }

    public function render_sitemap($sitemap_template)
    {
        $this[TemplateVars::TEMPLATE_NAME] = $sitemap_template;
        return new XmlResponse($this->render_template('sitemap-bone.twig'));
    }

    /**
     * @return GlobalJsDataEntity
     */
    public function getGlobalJsDataEntityFromContext()
    {
        return isset($this->context[TemplateVars::GLOBAL_JS_DATA]) ? new GlobalJsDataEntity(json_decode($this->context[TemplateVars::GLOBAL_JS_DATA], true)) : new GlobalJsDataEntity([]);
    }

    public function getCleanUserObjectFromContext()
    {
        /** @var User $user */
        if (!isset($this->context[TemplateVars::REQUEST_USER]))
            return null;

        $user = clone $this->context[TemplateVars::REQUEST_USER];

        $user->entity = $user->is_authenticated() ? $user->entity : [];
        $user->session_entity = $user->session->getEntity()->getJSONData();
        $user->guest_entity = $user->guest->getEntity()->getJSONData();

        unset($user->guest);
        unset($user->user_salt);
        unset($user->deviceDetector);
        unset($user->info);

        return $user;
    }


    public function render_response($template, $custom_bone = false, $code = 200)
    {

        $cache = Cache::get_cache();
        $local_cache = Cache::get_local_cache();
        $js_content = $this->getGlobalJsDataEntityFromContext();
        $js_content[TemplateVars::REQUEST_USER] = $this->getCleanUserObjectFromContext();

        if ($this->profiling && !$custom_bone) {
            $start_time = microtime(true);
            $debug_start_time = microtime(true);

            $debug_content = [
                'processing_time' => Debug::get_time_elapsed().' ms',
                'total_sql_query_time' => $query_time = Debug::total_sql_query_time().' ms',
                'sql_queries' => $sql_queries = $this['sql_queries'] = Debug::sql_queries(),
                'cache_get_queries' => $cache->get_get_queries(),
                'cache_set_queries' => $cache->get_set_queries(),
                'cache_connect_time' => $cache->getConnectTime(),
                'local_cache_get_queries' => $local_cache->get_get_queries(),
                'local_cache_set_queries' => $local_cache->get_set_queries(),
                'local_cache_connect_time' => $local_cache->getConnectTime()
            ];

        }

        // Add some last minute template variables
        if (isset($this->context[TemplateVars::PAGE_IDENTIFIER]))
            $js_content->setPageIdentifier($this->context[TemplateVars::PAGE_IDENTIFIER]);

        // Append query times and debug to global js data
        $this->context[TemplateVars::GLOBAL_JS_DATA] = $js_content->getJsonObject();

        if ($custom_bone) {
            $content = $this->render_template($template);
        } else {
            $this[TemplateVars::TEMPLATE_NAME] = $template;
            $content = $this->render_template('bone.twig');
        }

        if ($this->profiling && !$custom_bone) {

            $end_time = microtime(true);
            $time = round(($end_time - $start_time) * 1000, 2);

            if ($this->append_time) {
                $debugContent = "Object.assign(debug_info, { template_time: {$time}});";
                $content = str_replace('<!-- DEBUGINFO_TEMPLATE -->', $debugContent, $content);
            }
        }

        $response = new HtmlResponse($content, $code);
        $response->custom_bone = $custom_bone;

        if ($this->profiling && !$custom_bone) {
            $response->template_time     = $end_time - $start_time;
            $response->debug_time        = $start_time - $debug_start_time;
            $response->default_tree_time = $this->default_tree_time;
        }

        return $response;
    }

    /**
     * @param $template
     * @return string
     */
    public function render_template($template)
    {
        $this->template = $template;
        return $this->twig->render($template, $this->context);
    }

    /**
     * @param $name
     * @param null $value
     * @return $this
     * @throws BaseEntityException
     */
    public function assign($name, $value = null)
    {
        if (is_array($name)) {
            if ($value !== null)
                throw new BaseEntityException("value should null if name is an array");
            $this->context = array_merge($this->context, $name);
        } else {
            $this->context[$name] = $value;
        }
        return $this;
    }

    /**
     * @param $name
     * @param $value
     */
    public function assign_by_ref($name, &$value)
    {
        $this->context[$name] = &$value;
    }

    public function enable_profiling()
    {
        $this->profiling = true;
    }

    public function enable_append_time()
    {
        $this->append_time = true;
    }

    /*
     * ArrayAccess
     */

   public function offsetSet($offset, $value)
    {
        $this->context[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->context);
    }

    public function offsetUnset($offset)
    {
        unset($this->context[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->context[$offset];
    }
}
