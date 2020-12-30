<?php
/**
 * Request class
 * stores all info related to the user request
 *
 * @package http
 */


class Request
{

    const SCHEME = 'scheme';
    const USER = 'user';
    const HOST = 'host';
    const PATH = 'path';
    const QUERY = 'query';
    const FRAGMENT = 'fragment';

	// Cookie Settings
	const COOKIE_UI_LANG = 'lang';
	const COOKIE_T = 't';

	// Request Methods
	const METHOD_POST = 'POST';
	const METHOD_GET = 'GET';

	// Server Keys
	const SERVER_REMOTE_ADDR = 'REMOTE_ADDR';

	/**
	 * @var DefaultAuth
	 */
	public $auth;

	/** @var string */
	public $app;

	/** @var string $os */
	public $os;

    /**
     * @var array
     */
    public $etls = [];

	/**
	 * @var S3
	 */
	public $s3;

	/**
	 * @var User $user
	 */
	public $user;

	/**
	 * URL parameters
	 *
	 * @var array
	 */
	public $url;

	/**
	 * GET parameters
	 *
	 * @var array|GetRequest
	 */
	public $get;

	/**
	 * POST parameters
	 *
	 * @var array
	 */
	public $post;

	public $json_post;

	/**
	 * User cookies
	 *
	 * @var array
	 */
	public $cookies;

	/**
	 * Server info
	 *
	 * @var array
	 */
	public $server;

	/**
	 * Request Method (head/get/post/put etc)
	 *
	 * @var array
	 */
	public $method;

	/**
	 * Cache instance
	 *
	 * @var CacheBackend
	 */
	public $cache;

	/**
	 * @var APCBackend
	 */
	public $local_cache;

	/**
	 * @var DB
	 */
	public $db;

	/**
	 * @var i18n
	 */
	public $translations;

	/**
	 * Local memory cache
	 * linked to the request object
	 * data is lost at the end of the request
	 *
	 * @var array
	 */
	public $_cache = [];

	/**
	 * Request URL (path = before the ?)
	 *
	 * @var string
	 */
	public $path;

	/**
	 * Request URL (query = after the ?)
	 *
	 * @var string
	 */
	public $query;

	/**
	 * Request URL (anchor = after the #)
	 *
	 * @var string
	 */
	public $anchor;

	/**
	 * Request URL (scheme = before the ://)
	 *
	 * @var string
	 */
	public $scheme;

	/**
	 * Base URL to access the content
	 * (updated by each content rendering function)
	 *
	 * @var string
	 */
	public $root = '/';

	/**
	 * Configuration
	 *
	 * @var array
	 */
	public $config;

	/** @var ESCConfiguration */
	protected $config_entity;

	/**
	 * Debug mode
	 *
	 * @var array
	 */
	public $debug;

	/**
	 * User ip
	 *
	 * @var array
	 */
	public $ip;

	/** @var $es ElasticSearchClient */
	public $es;

	/** @var array */
	public $files;

    /** @var GeoIpMapperEntity */
    public $geoIpMapping;

	/** @var  GeoRegionEntity */
	public $geoRegion;

	/** @var string $requestId */
	public $requestId;

	/**
	 * @var ManagerLocator
	 */
	public $managers;

	public function __construct($server = [], $post = [], $cookies = [],
		$files = [], $config = [], $headers = []) {
		$this->start_time = microtime(true);

        $factory = new \Ramsey\Uuid\UuidFactory();

        $generator = new \Ramsey\Uuid\Generator\CombGenerator(
            $factory->getRandomGenerator(),
            $factory->getNumberConverter()
        );

        $codec = new Ramsey\Uuid\Codec\TimestampFirstCombCodec($factory->getUuidBuilder());

        $factory->setRandomGenerator($generator);
        $factory->setCodec($codec);

        Ramsey\Uuid\Uuid::setFactory($factory);


		$this->requestId = Ramsey\Uuid\Uuid::uuid4()->toString();

		// ArrayAccess Configuration Class - Enables IDE Auto-Complete for defined methods
		// Note: This is a hacky workaround for LOTS of issues with changing $this->config
        $this->config_entity = ESCConfiguration::getInstance($config);

		// Legacy Configuration Array (no auto-complete in IDE)
		$this->config = $config;
		$this->headers = $headers;
		$this->app = WEB_APP;
		$this->server = $server;
		$this->debug = $config['debug'];
		$this->host = array_get($server, 'HTTP_HOST', '');
		$this->method = array_get($server, 'REQUEST_METHOD', 'GET');

		$this->scheme = array_key_exists('HTTPS', $server) && $server['HTTPS'] ? 'https' : 'http';

		if (isset($server['HTTP_X_FORWARDED_PROTO']))
		    $this->scheme = $server['HTTP_X_FORWARDED_PROTO'];

		$this->server['HOSTNAME'] = array_get($_ENV, "FCGI_WEB_SERVER_ADDRS", 'local');

        if ($this->is_content_type_json() && $this->is_post()) {
            $json_content = json_decode(file_get_contents("php://input"), true);
            $post = $json_content ? $json_content : [];
        }

		//$this->post    = new PostRequestMethod($post);
		$this->files = $files;
		$this->cookies = $cookies;
		$this->ip = $this->getRealIp();

		$url_properties = @parse_url($this->scheme . '://' . $this->host . array_get($server, 'REQUEST_URI', '/'));
		if ($url_properties) {
			$this->path = array_get($url_properties, 'path', '');
			$this->query = array_get($url_properties, 'query', '');
			$this->anchor = array_get($url_properties, 'fragment', '');
		} else {
			$this->path = '/';
			$this->query = '';
			$this->anchor = '';
		}

		parse_str($this->query, $this->get);
		$this->get = new GetRequest($this->get);
		$this->post = $post;

		$this->url = explode('/', $this->path);
	}

	/**
	 * @param $key
	 * @param $value
	 * @param null $timeout
	 * @param string $path
	 * @param null $secure
	 */
	public function setCookie($key, $value, $timeout = null, $path = COOKIE_PATH, $domain = null, $secure = null, $httpOnly = null)
    {
		if ($timeout === null) {
			$timeout = time() + 3600 * 24 * 60;
		}

		if ($domain === null)
		    $domain = $this->settings()->getCookieDomain();

		setcookie($this->settings()->getFullCookieKey($key), $value, $timeout, $path, $domain, $secure, $httpOnly);
		$this->cookies[$key] = $value;
	}

	/**
	 * @param $key
	 * @return mixed
	 */
	public function readCookie($key, $default = null)
    {
		$cookie = array_get($this->cookies, $this->settings()->getFullCookieKey($key), $default);
		if (!$cookie && !is_null($default)) {
			$cookie = $default;
		}

		return $cookie;
	}

	/**
	 * @param $key
	 * @return bool
	 */
	public function hasCookie($key)
    {
		return array_key_exists($this->settings()->getFullCookieKey($key), $this->cookies);
	}

	/**
	 * @return bool
	 */
	private function is_content_type_json()
    {
		return isset($this->headers['Content-Type']) && stripos($this->headers['Content-Type'], 'application/json') === 0;
	}

    /**
     * @param User $user
     */
	public function setUser(User $user)
    {
        $this->user = $user;
    }

	/**
	 * @return ESCConfiguration
	 */
	public function settings()
    {
		return $this->config_entity;
	}

	/**
	 * @return mixed|string
	 */
	public function getUiLang()
    {
        return $this->translations->get_lang();
	}

	public function useSimpleMarkup() {
		return in_array($this->host, $this->config[ESCConfiguration::SIMPLE_MARKUP_DOMAINS]);
	}

	/**
	 * @return boolean whether this is an AJAX (XMLHttpRequest) request.
	 */
	public function is_ajax()
    {
		if (array_key_exists('X-Fancybox', $this->headers))
		    return true;

		return array_key_exists('ajax', $this->get)
		|| array_key_exists('HTTP_X_REQUESTED_WITH', $this->server)
		&& $this->server['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
	}

    /**
     * @param string $default
     * @return string
     */
	public function getRealIp($default = 'x.x.x.x') {
		if (isset($this->server["HTTP_X_FORWARDED_FOR"])) {
			$s_ip = $this->server["HTTP_X_FORWARDED_FOR"];
		} elseif (isset($this->server["REMOTE_ADDR"])) {
			$s_ip = $this->server["REMOTE_ADDR"];
		} else {
			$s_ip = $default;
		}

		return $s_ip;
	}

    /**
     * @return bool
     */
	public function is_post() {
		return $this->method == Request::METHOD_POST;
	}

	/**
	 * @return mixed
	 */
	public function getUserAgent() {
		return array_get($this->server, 'HTTP_USER_AGENT', '');
	}

	/**
	 * @return string
	 */
	public function getCurrentUrlPath() {
		return $this->getRequestRootUrl() . $this->path;
	}

	/**
	 * @param string $format
	 * @return bool|string
	 */
	public function getCurrentSqlTime($format = '') {
		if (!$format) {
			$format = $this->settings()->getSqlPostDateFormat();
		}

		return date($format);
	}

	/**
	 * @param $date_time_string
	 * @param bool|false $full
	 * @return string
	 */
	public function getTimeAgo($date_time_string, $full = false) {
		return time_elapsed_string($this->translations, $date_time_string, $full);
	}

	/**
	 * @param bool|true $absolutePath
	 * @return string
	 */
	public function getFullUrl($absolutePath = true) {
		$prefix = $absolutePath ? $this->getCurrentUrlPath() : $this->path;
		return $prefix . $this->get->buildQuery();
	}

	/**
	 * @return string
	 */
	public function getRequestRootUrl() {
		return $this->scheme . '://' . $this->host;
	}

    /**
     * @return string
     */
	public function getOs()
    {
        if (!$this->os) {
            $agent = $this->getUserAgent();

            if(preg_match('/Linux/i',$agent)) $os = 'Linux';
            elseif(preg_match('/Mac/i',$agent)) $os = 'Mac';
            elseif(preg_match('/iPhone/i',$agent)) $os = 'iPhone';
            elseif(preg_match('/iPad/i',$agent)) $os = 'iPad';
            elseif(preg_match('/Droid/i',$agent)) $os = 'Droid';
            elseif(preg_match('/Unix/i',$agent)) $os = 'Unix';
            elseif(preg_match('/Windows/i',$agent)) $os = 'Windows';
            else $os = 'Unknown';

            $this->os = $os;
        }

        return $this->os;
    }

    /**
     * @return bool
     */
    public function isWindows()
    {
        return $this->getOs() === 'Windows';
    }

    /**
     * @return bool
     */
    public function isMac()
    {
        return $this->getOs() === 'Mac';
    }


    /**
     * @return array|mixed
     */
	public function getReferrerUrlParams()
    {
        $referring_page_params = parse_url($this->getReferer(), PHP_URL_QUERY);

        if (!$referring_page_params)
            $referring_page_params = [];

        return $referring_page_params;
    }

    /**
     * @param $header
     * @return bool
     */
    public function hasHeader($header)
    {
        return array_key_exists($header, $this->headers);
    }

    /**
     * @param $header
     * @param null $default
     * @return mixed|null
     */
    public function readHeader($header, $default = null)
    {
        return $this->hasHeader($header) ? $this->headers[$header] : $default;
    }

    /**
     * @return string
     */
	public function getWwwUrl($suffix = null)
    {
        $hostName = $this->config['hosts']['www'];

        return "{$this->scheme}://{$hostName}{$suffix}";
    }

    /**
     * @param null $suffix
     * @return string
     */
    public function getGoUrl($suffix = null)
    {
        $hostName = $this->config['hosts']['go'];

        return "{$this->scheme}://{$hostName}{$suffix}";
    }

    /**
     * @param null $suffix
     * @return string
     */
    public function getImagesUrl($suffix = null)
    {
        $hostName = $this->config['hosts']['images'];

        return "{$this->scheme}://{$hostName}{$suffix}";
    }



    /**
     * @return string
     */
    public function getPlayUrl($suffix = null)
    {
        $hostName = $this->config['hosts']['play'];

        if ($this->settings()->is_prod()) {
            return "{$this->scheme}://{$hostName}{$suffix}";
        } else {
            return "{$this->scheme}://{$hostName}{$suffix}";
        }
    }

    /**
     * @return string
     */
    public function getDevelopUrl($suffix = null)
    {
        $hostName = $this->config['hosts']['develop'];

        return "{$this->scheme}://{$hostName}{$suffix}";
    }

    /**
     * @return string
     */
    public function getApiUrl($suffix = null)
    {
        $hostName = $this->config['hosts']['api'];

        return "{$this->scheme}://{$hostName}{$suffix}";
    }

	/**
	 * @return string
	 */
	public function getWebsiteRootUrl() {
		return $this->scheme . '://' . $this->settings()->getWebsiteDomain();
	}

	/*
		     * Request Referrer Helpers
	*/
	public function getReferer($default = '') {
		return array_get($this->server, 'HTTP_REFERER', $default);
	}

	/**
	 * @param null $param
	 * @return null
	 */
	public function getReferrerUrlParam($param = null) {
		$referring_page_params = parse_url($this->getReferer(), PHP_URL_QUERY);

		if (!empty($referring_page_params[$param])) {
			return $referring_page_params[$param];
		}

		return null;
	}

	/**
	 * @return mixed
	 */
	public function getRefererDomain()
    {
		return parse_url($this->getReferer(), PHP_URL_HOST);
	}

    /**
     * @return bool
     */
	public function isInternalReferer()
    {
		return $this->getRefererDomain() == 'localhost' || $this->getRefererDomain() == $this->settings()->getWebsiteDomain();
	}

	/**
	 * @param array $params
	 * @param string $prefix
	 * @param string $error
	 * @return string
	 */
	public function buildQuery($params = [], $prefix = '?', $error = '') {
		if ($error) {
			$params['e'] = $error;
		}

		return $params ? $prefix . http_build_query($params) : '';
	}

	/**
	 * @param string $uri
	 * @return string
	 */
	public function buildUrl($uri = '', $scheme = null)
    {
        if (!$scheme)
            $scheme = $this->scheme;

		return "{$scheme}://{$this->host}{$uri}";
	}

	/**
	 * @return string
	 */
	public function getSection() {
		return $this->getSlugForEntity(1);
	}

	/**
	 * @param int $url_key
	 * @return string
	 */
	public function getSlugForEntity($url_key = 2, $default = null) {
		return get_from_url($this, $url_key, $default);
	}

	/**
	 * @param int $url_key
	 * @return int
	 */
	public function getUrlKeyLength($url_key = 2) {
		return strlen($this->getSlugForEntity($url_key));
	}

	/**
	 * @param int $url_key
	 * @param bool|true $handle_error
	 * @return null|string
	 * @throws Http404
	 */
	public function getIdForEntity($url_key = 2, $handle_error = true) {
		$id = null;
		try {
			$id = get_id_from_url($this, $url_key);
		} catch (Http404 $e) {
			$id = null;
			if (!$handle_error) {
				throw $e;
			}

		}

		return $id;
	}

	/**
	 * @param $key
	 * @return bool
	 */
	public function hasPostParam($key = null) {
		if ($key === null) {
			return !empty($this->post);
		}

		return array_key_exists($key, $this->post);
	}

    /**
     * @param $key
     * @param null $default
     * @return mixed|null
     */
	public function readPostParam($key, $default = null)
    {
		return $this->hasPostParam($key) ? $this->post[$key] : $default;
	}

    /**
     * @return array|mixed|string
     */
	public function getRedirectBackUrl()
    {
        $next = $this->getWwwUrl();

        foreach ($this->config['hosts'] as $host) {
            if ($this->getRefererDomain() == $host)
                $next = $this->getReferer();
        }

		if (strpos($next, '/account/') === false && strpos($next, '/auth') !== false) {
		    $next = '/';
		}

		return $next;
	}

}

trait BootstrapArrayCountableIterator {

	protected $dataArray = [];

	/**
	 * BootstrapArrayCountableIterator constructor.
	 * @param $params
	 */
	public function __construct($params) {
		foreach ($params as $key => $value) {
			$this->offsetSet($key, $value);
		}

	}

	/**
	 * @param $offset
	 */
	public function offsetGet($offset) {
		$this->dataArray[$offset];
	}

	/**
	 * @param $offset
	 * @param $value
	 */
	public function offsetSet($offset, $value) {
		$this->dataArray[$offset] = $value;
	}

	/**
	 * @param $offset
	 * @return bool
	 */
	public function offsetExists($offset) {
		return isset($this->dataArray[$offset]);
	}

	/**
	 * @param $offset
	 */
	public function offsetUnset($offset) {
		unset($this->dataArray[$offset]);
	}

	/**
	 * @return ArrayIterator
	 */
	public function getIterator() {
		return new ArrayIterator($this->dataArray);
	}

	/**
	 * @return int
	 */
	public function count() {
		return count($this->dataArray);
	}
}

abstract class RequestMethod implements ArrayAccess, Countable, IteratorAggregate {

	use
		BootstrapArrayCountableIterator;

	/*
		     * Access any Query Parameter Key
		     *
		     * @param $param
		     * @param null $default
		     * @return mixed
	*/
	public function readParam($param, $default = null)
    {
		$value = array_get($this->dataArray, $param, $default);
		if (!$value)
		    $value = $default;
		return $value;
	}

	/**
	 * This method takes a variable amount of RequestMethod index params to check, and will return true if ANY one
	 * of them are set. Use this as singular or multi-validation that any one of specified params
	 * are part of the request.
	 *
	 * @param ...$params
	 * @return bool
	 */
	public function hasParam(...$params) {

		foreach ($params as $param) {
			if (isset($this->dataArray[$param])) {
				return true;
			}

		}
		return false;
	}

	/**
	 * @return array
	 */
	public function params() {
		return $this->dataArray;
	}


    /**
     * @param mixed ...$params
     * @return bool
     */
	public function hasParams(...$params)
    {
        if (!$params)
		    return !empty($this->dataArray);
        else {
            $valid = true;

            foreach ($params as $param) {
                if (!array_key_exists($param, $this->dataArray))
                    $valid = false;
            }

            return $valid;
        }

	}

}

class GetRequest extends RequestMethod {

	// GET Params - Generic / Global Request Parameters
	const PARAM_ID = 'id';
	const PARAM_T = 't';
	const PARAM_LANG = 'lang';
	const PARAM_QUERY = 'q';
	const PARAM_PAGE = 'p';
	const PARAM_CPAGE = 'cpage';
	const PARAM_FORUM_ID = 'f';
	const PARAM_GEO_REGION = 'geo_region';
	const PARAM_CONVERSATION_ID = 't';
	const PARAM_REVISION = 'r';
	const PARAM_SORT = 'sort';
	const PARAM_PERPAGE = 'perpage';
	const PARAM_PUBLISH = 'publish';
	const PARAM_USERNAME = 'username';
	const PARAM_ET_ID = 'etId';
	const PARAM_MT_ID = 'mtId';
	const PARAM_ES_ID = 'esId';
	const PARAM_CATEGORY_ID = 'categoryId';
	const PARAM_CHECKSUM = 'checksum';
	const PARAM_UTM_MEDIUM = 'utm_medium';
	const PARAM_UTM_SOURCE = 'utm_source';
	const PARAM_UTM_CAMPAIGN = 'utm_campaign';
	const PARAM_UTM_TERM = 'utm_term';
	const PARAM_LIST_TYPE = 'f';
	const PARAM_UNREAD_MENTIONS = 'ur';
	const PARAM_GOTO = 'goto';
	const PARAM_TO = 'to';
	const PARAM_INVITATION = 'invitation';
	const PARAM_ORGANIZATION_USER_INVITE_TOKEN = 'ouit';
	const PARAM_NEXT = 'next';

	const PARAM_MAGIC = 'magic';
	const PARAM_IDENT = 'ident';
	const PARAM_EXP = 'exp';
	const PARAM_PATH = 'path';

	/**
	 * @return array
	 */
	public function params($keysToExclude = [])
    {
	    if ($keysToExclude) {

	        $params = [];

	        foreach ($this->dataArray as $key => $value) {
	            if (!in_array($key, $keysToExclude))
	                $params[$key] = $value;
            }

            return $params;

        } else {
            return $this->dataArray;
        }
	}

	/**
	 * Build a URL query string from current array with ? prefix
	 *
	 * @param string $prefix
	 * @param array $keys_to_delete
	 * @return null|string
	 */
	public function buildQuery($prefix = '?', $keys_to_delete = []) {
		$params = $this->dataArray;
		foreach ($keys_to_delete as $key) {
			if (array_key_exists($key, $params)) {
				unset($params[$key]);
			}

		}

		return $params ? $prefix . http_build_query($params) : '';
	}

	/**
	 * @return array
	 */
	public function buildTrackingUrlParams() {
		$tracking_url_params = [];

		if ($this->etId()) {
			$tracking_url_params[self::PARAM_ET_ID] = $this->etId();
		}

		if ($this->checksum()) {
			$tracking_url_params[self::PARAM_CHECKSUM] = $this->checksum();
		}

		if ($this->utmMedium()) {
			$tracking_url_params[self::PARAM_UTM_MEDIUM] = $this->utmMedium();
		}

		if ($this->utmSource()) {
			$tracking_url_params[self::PARAM_UTM_SOURCE] = $this->utmSource();
		}

		if ($this->utmCampaign()) {
			$tracking_url_params[self::PARAM_UTM_CAMPAIGN] = $this->utmCampaign();
		}

		if ($this->utmTerm()) {
			$tracking_url_params[self::PARAM_UTM_TERM] = $this->utmTerm();
		}

		return $tracking_url_params;
	}

	/*
		     * Shorthand for Specific Query Parameters
	*/

	/**
	 * @return string|null
	 */
	public function query($default = null) {
		return $this->readParam(self::PARAM_QUERY, $default);
	}

	/**
	 * @param null $default
	 * @return string|null
	 */
	public function lang($default = null) {
		return $this->readParam(self::PARAM_LANG, $default);
	}

	/**
	 * @param null $default
	 * @return string|null
	 */
	public function sort($default = null) {
		return $this->readParam(self::PARAM_SORT, $default);
	}

	/*
		     * @return string|null
	*/
	public function conversationThreadId() {
		return $this->readParam(self::PARAM_CONVERSATION_ID, null);
	}

	/**
	 * @return string|null
	 */
	public function checksum($default = null) {
		return $this->readParam(self::PARAM_CHECKSUM, $default);
	}

	/**
	 * @return string|null
	 */
	public function mentionId($default = null) {
		return $this->readParam(self::PARAM_MT_ID, $default);
	}

	/**
	 * @param null $default
	 * @return mixed
	 */
	public function esId($default = null) {
		return $this->readParam(self::PARAM_ES_ID, $default);
	}

	/**
	 * @param null $default
	 * @return string|null
	 */
	public function username($default = null) {
		return $this->readParam(self::PARAM_USERNAME, $default);
	}

	/**
	 * Used to determine origin source medium by campaign and magic referrer parsing
	 *
	 * @return string
	 */
	public function utmMedium($default = null) {
		return $this->readParam(self::PARAM_UTM_MEDIUM, $default);
	}

	/**
	 * @return mixed
	 */
	public function utmSource($default = null) {
		return $this->readParam(self::PARAM_UTM_SOURCE, $default);
	}

	/**
	 * @return mixed
	 */
	public function utmCampaign($default = null) {
		return $this->readParam(self::PARAM_UTM_CAMPAIGN, $default);
	}

	/**
	 * @return mixed
	 */
	public function utmTerm($default = null) {
		return $this->readParam(self::PARAM_UTM_TERM, $default);
	}

	/**
	 * Email Tracking ID
	 *
	 * @return string
	 */
	public function etId($default = null) {
		$etId = $this->readParam(self::PARAM_ET_ID, $default);

		if (!intval($etId)) {
			$etId = null;
		}

		return $etId;
	}

	/**
	 * @param string $default
	 * @return string
	 */
	public function next($default = '') {
		return $this->readParam(self::PARAM_NEXT, $default);
	}

	/**
	 * @return int
	 */
	public function page($default = 1) {
		$page = $this->readParam(self::PARAM_PAGE, $default);
		if ($page < 1 || !intval($page)) {
			$page = 1;
		}

		return (int) $page;
	}

	/**
	 * @return int
	 */
	public function perPage($default = DEFAULT_PERPAGE) {
		$per_page = $this->readParam(self::PARAM_PERPAGE, $default);

        if (!intval($per_page))
			$per_page = $default;

		return (int) $per_page;
	}

	/**
	 * @return bool
	 */
	public function unread_mentions() {
		return $this->hasParam(self::PARAM_UNREAD_MENTIONS);
	}

	/**
	 * @return string|null
	 */
	public function sessionHash($default = null) {
		return $this->readParam(FormField::SESSION_HASH, $default);
	}

	/**
	 * @param null $default
	 * @return string|null
	 */
	public function guestHash($default = null) {
		return $this->readParam(FormField::GUEST_HASH, $default);
	}

	/**
	 * @param array $params
	 * @param string $error
	 * @return string
	 */
	public function buildUrlQuery($params = [], $error = '') {
		if (!$params) {
			$params = $this->dataArray;
		} elseif ($this->dataArray && $params) {
			$params = array_merge($this->dataArray, $params);
		}

		if ($error) {
			$params['e'] = $error;
		}

		return $params ? '?' . http_build_query($params) : '';
	}

	/**
	 * @param null $default
	 * @return mixed
	 */
	public function categoryId($default = null) {
		return $this->readParam(self::PARAM_CATEGORY_ID, $default);
	}

}