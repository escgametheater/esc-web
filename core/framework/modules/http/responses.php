<?php
/**
 * Stores http response content and headers
 */
class HttpResponse
{
    /**
     * HTTP Response Codes Constants
     */
    const HTTP_CONTINUE = 100;
    const HTTP_SWITCHING_PROTOCOLS = 101;
    const HTTP_PROCESSING = 102;            // RFC2518
    const HTTP_OK = 200;
    const HTTP_CREATED = 201;
    const HTTP_ACCEPTED = 202;
    const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    const HTTP_NO_CONTENT = 204;
    const HTTP_RESET_CONTENT = 205;
    const HTTP_PARTIAL_CONTENT = 206;
    const HTTP_MULTI_STATUS = 207;          // RFC4918
    const HTTP_ALREADY_REPORTED = 208;      // RFC5842
    const HTTP_IM_USED = 226;               // RFC3229
    const HTTP_MULTIPLE_CHOICES = 300;
    const HTTP_MOVED_PERMANENTLY = 301;
    const HTTP_FOUND = 302;
    const HTTP_SEE_OTHER = 303;
    const HTTP_NOT_MODIFIED = 304;
    const HTTP_USE_PROXY = 305;
    const HTTP_RESERVED = 306;
    const HTTP_TEMPORARY_REDIRECT = 307;
    const HTTP_PERMANENTLY_REDIRECT = 308;  // RFC7238
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_PAYMENT_REQUIRED = 402;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_METHOD_NOT_ALLOWED = 405;
    const HTTP_NOT_ACCEPTABLE = 406;
    const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    const HTTP_REQUEST_TIMEOUT = 408;
    const HTTP_CONFLICT = 409;
    const HTTP_GONE = 410;
    const HTTP_LENGTH_REQUIRED = 411;
    const HTTP_PRECONDITION_FAILED = 412;
    const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    const HTTP_REQUEST_URI_TOO_LONG = 414;
    const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    const HTTP_EXPECTATION_FAILED = 417;
    const HTTP_I_AM_A_TEAPOT = 418;                                               // RFC2324
    const HTTP_UNPROCESSABLE_ENTITY = 422;                                        // RFC4918
    const HTTP_LOCKED = 423;                                                      // RFC4918
    const HTTP_FAILED_DEPENDENCY = 424;                                           // RFC4918
    const HTTP_RESERVED_FOR_WEBDAV_ADVANCED_COLLECTIONS_EXPIRED_PROPOSAL = 425;   // RFC2817
    const HTTP_UPGRADE_REQUIRED = 426;                                            // RFC2817
    const HTTP_PRECONDITION_REQUIRED = 428;                                       // RFC6585
    const HTTP_TOO_MANY_REQUESTS = 429;                                           // RFC6585
    const HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;                             // RFC6585
    const HTTP_INTERNAL_SERVER_ERROR = 500;
    const HTTP_NOT_IMPLEMENTED = 501;
    const HTTP_BAD_GATEWAY = 502;
    const HTTP_SERVICE_UNAVAILABLE = 503;
    const HTTP_GATEWAY_TIMEOUT = 504;
    const HTTP_VERSION_NOT_SUPPORTED = 505;
    const HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL = 506;                        // RFC2295
    const HTTP_INSUFFICIENT_STORAGE = 507;                                        // RFC4918
    const HTTP_LOOP_DETECTED = 508;                                               // RFC5842
    const HTTP_NOT_EXTENDED = 510;                                                // RFC2774
    const HTTP_NETWORK_AUTHENTICATION_REQUIRED = 511;                             // RFC6585

    /**
     * HTTP Response Code to Status Texts Mapping
     */
    public static $http_response_status_texts = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC7238
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        422 => 'Unprocessable Entity',                                        // RFC4918
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Reserved for WebDAV advanced collections expired proposal',   // RFC2817
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates (Experimental)',                      // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    ];


    /**
     * Content
     *
     * @var string
     */
    public $content;

    /** @var $code */
    protected $code;

    /**
     * Headers
     *
     * @var array
     */
    public $headers;

    /**
     * Time used to generate the template
     *
     * @var init
     */
    public $template_time = 0;

    /**
     * Time used to generate the default tree
     *
     * @var init
     */
    public $default_tree_time = 0;

    /** @var string $requestId */
    public $requestId;

    /**
     * Constructor
     *
     * @param string $content Response body
     */

    public function __construct($content, $code = self::HTTP_OK)
    {
        global $CONFIG;

        $this->code = $code;

        $this->content = &$content;
        $this->headers = [
            'Content-Type' => $CONFIG['default_content_type'] .'; charset='.$CONFIG['default_charset']
        ];
    }

    /**
     * @param $requestId
     */
    public function setRequestId($requestId)
    {
        $this->requestId = $requestId;
    }

    public function set_header($name, $value)
    {
        $this->headers[$name] = $value;
    }

    public function prependHeader($header, $value)
    {
        $this->headers = array_merge([$header => $value], $this->headers);
    }

    public static function getStatusMessageByCode($code = self::HTTP_OK)
    {
        return self::$http_response_status_texts[$code];
    }

    /**
     * Send response to browser
     *
     * Flushes output buffer before sending content to browser
     *
     * @param $ob_flush bool
     */
    public function display()
    {
        foreach($this->headers as $name => $value) {
            header("$name: $value");
        }

        if ($this->requestId) {
            header("X-ESC-Request-ID: {$this->requestId}");
        }

        http_status($this->code);

        if ($this->code != HtmlResponse::HTTP_NO_CONTENT) {
            echo $this->content;
        }
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return float|int|mixed
     */
    public function getResponseTime()
    {
        return Debug::get_time_elapsed();
    }
}

/**
 * Stores a redirect response
 *
 */
class HttpResponseRedirect extends HttpResponse
{
    public function __construct($content, $code = 302)
    {
        parent::__construct($content, $code);
    }

    public function display($ob_flush = false)
    {
        http_status($this->code);
        location($this->content);
    }
}

/**
 * Stores an html response
 */
class HtmlResponse extends HttpResponse {

    public function __construct($content, $code = HttpResponse::HTTP_OK)
    {
        parent::__construct($content, $code);
    }
}

/**
 * Stores a json response
 */
class JSONResponse extends HttpResponse
{
    protected $code = HttpResponse::HTTP_OK;

    public function __construct($content, $code = HttpResponse::HTTP_OK, $encoded = false)
    {
        global $CONFIG;

        $this->content = $encoded ? $content : json_encode($content);
        $this->code = $code;
        $this->headers = ['Content-Type' => 'application/json; charset='.$CONFIG['default_charset']];
    }

    public function display()
    {
        http_status($this->code);
        parent::display();
    }
}

class YamlResponse extends HttpResponse
{
    protected $code = HttpResponse::HTTP_OK;

    public function __construct($content, $code = HttpResponse::HTTP_OK)
    {
        parent::__construct($content, $code);

        global $CONFIG;
        $this->headers = ['Content-Type' => 'text/yaml; charset='.$CONFIG['default_charset']];
    }

    public function display()
    {
        http_status($this->code);
        parent::display();
    }
}

class JavaScriptResponse extends HttpResponse {

    public function __construct($content, $code = HttpResponse::HTTP_OK)
    {
        global $CONFIG;

        $this->content = $content;
        $this->code = $code;
        $this->headers = ['Content-Type' => 'text/javascript; charset='.$CONFIG['default_charset']];
    }}

/**
 * Stores an xml response
 */
class XmlResponse extends HttpResponse
{
    public function __construct($content, $code = HttpResponse::HTTP_OK)
    {
        global $CONFIG;

        $this->content = $content;
        $this->code = $code;
        $this->headers = ['Content-Type' => 'text/xml; charset='.$CONFIG['default_charset']];
    }
}

class TwimlResponse extends HttpResponse
{
    public function __construct($content, $code = HttpResponse::HTTP_OK)
    {
        global $CONFIG;

        $this->content = $content;
        $this->code = $code;
        $this->headers = [];
    }

    public function display()
    {
        print($this->content);
    }
}

/**
 * Stores an xml response
 */
class ImageGifResponse extends HttpResponse
{
    public function __construct($base64_data, $code = HttpResponse::HTTP_OK)
    {
        $this->code = $code;
        $this->content = $base64_data;
        $this->headers = ['Content-Type' => 'image/gif;'];
    }
}

class GameControllerAssetResponse extends HttpResponse
{
    public function __construct(GameControllerAssetEntity $gameAsset, $content, $code = HttpResponse::HTTP_OK, $eTag = null)
    {
        parent::__construct($content, $code);

        $contentType = $gameAsset->getMimeType();

        $extension = strtolower($gameAsset->getExtension());

        if ($extension == "css")
            $contentType = 'text/css';
        elseif ($extension == "js")
            $contentType = 'application/javascript';
        elseif ($extension == 'json')
            $contentType = 'application/json';
        elseif (in_array($extension, ["png", "jpg", "jpeg"]))
            $contentType = 'image/*';
        elseif (in_array($extension, ["svg"]))
            $contentType = 'image/svg+xml';

        $this->headers = [
            'Content-Type' => $contentType,
        ];

        if ($eTag)
            $this->headers['ETag'] = $eTag;
    }
}

class HostControllerAssetResponse extends HttpResponse
{
    /**
     * HostControllerAssetResponse constructor.
     * @param HostControllerAssetEntity $hostControllerAsset
     * @param $content
     * @param int $code
     */
    public function __construct(HostControllerAssetEntity $hostControllerAsset, $content, $code = HttpResponse::HTTP_OK, $etag = null)
    {
        parent::__construct($content, $code);

        $contentType = $hostControllerAsset->getMimeType();

        if ($hostControllerAsset->getExtension() == "css")
            $contentType = 'text/css';
        elseif ($hostControllerAsset->getExtension() == "js")
            $contentType = 'application/javascript';
        elseif ($hostControllerAsset->getExtension() == 'json')
            $contentType = 'application/json';
        elseif (in_array($hostControllerAsset->getExtension(), ["png", "PNG", "jpg", "JPG"]))
            $contentType = 'image/*';
        elseif (in_array($hostControllerAsset->getExtension(), ["svg", "SVG"]))
            $contentType = 'image/svg+xml';

        $this->headers = [
            'Content-Type' => $contentType,
        ];

        if ($etag)
            $this->headers['ETag'] = $etag;
    }
}

class DownloadZipResponse extends HttpResponse
{
    /**
     * DownloadResponse constructor.
     * @param $content
     * @param $fileName
     * @param int $code
     * @param bool $downloadAttachment
     */
    public function __construct($content, $fileName, $code = HttpResponse::HTTP_OK, $downloadAttachment = true)
    {
        $this->content = $content;
        $this->code = $code;

        $attachmentString = $downloadAttachment ? "attachment; " : "";

        $this->headers = [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "{$attachmentString} filename=\"{$fileName}\""
        ];
    }
}

class DownloadXlsxResponse extends HttpResponse
{

    protected $writer;
    protected $fileName;

    /**
     * DownloadXlsxResponse constructor.
     * @param \PhpOffice\PhpSpreadsheet\Writer\Xlsx $writer
     * @param $fileName
     * @param int $code
     */
    public function __construct(\PhpOffice\PhpSpreadsheet\Writer\Xlsx $writer, $fileName, $code = HttpResponse::HTTP_OK)
    {
        $this->writer = $writer;
        $this->fileName = $fileName;
        $this->code = $code;

        $this->headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\""
        ];
    }

    /**
     * @return null|void
     */
    public function display()
    {
        foreach($this->headers as $name => $value) {
            header("$name: $value");
        }

        if ($this->requestId) {
            header("X-ESC-Request-ID: {$this->requestId}");
        }

        http_status($this->code);

        try {
            $this->writer->save('php://output');
        } catch (Exception $e) {
            return null;
        }
    }
}

class GameAssetResponse extends HttpResponse
{

    /**
     * GameAssetResponse constructor.
     * @param GameAssetEntity|GameBuildAssetEntity|CustomGameAssetEntity|GameInstanceLogAssetEntity $gameAsset
     * @param $content
     * @param int $code
     * @param bool $downloadAttachment
     * @param null $eTag
     */
    public function __construct($gameAsset, $content, $code = HttpResponse::HTTP_OK, $downloadAttachment = true, $eTag = null)
    {
        $this->content = $content;
        $this->code = $code;

        $attachmentString = $downloadAttachment ? "attachment; " : "";

        $this->headers = [
            'Content-Type' => $gameAsset->getMimeType(),
            'Content-Disposition' => "{$attachmentString} filename=\"{$gameAsset->getFileName()}\""
        ];

        if ($eTag)
            $this->headers['ETag'] = $eTag;
    }
}

class HostAssetResponse extends HttpResponse {

    public function __construct(HostBuildAssetEntity $hostBuildAsset, $content, $code = HttpResponse::HTTP_OK)
    {
        $this->content = $content;
        $this->code = $code;

        $this->headers = [
            'Content-Type' => $hostBuildAsset->getMimeType(),
            'Content-Disposition' => "attachment; filename=\"{$hostBuildAsset->getFileName()}\""
        ];
    }
}

class SdkAssetResponse extends HttpResponse {

    public function __construct(SdkBuildAssetEntity $sdkBuildAsset, $content, $download = true, $code = HttpResponse::HTTP_OK)
    {
        $this->content = $content;
        $this->code = $code;

        $attachmentString = $download ? 'attachment; ' : '';

        $this->headers = [
            'Content-Type' => $sdkBuildAsset->getMimeType(),
            'Content-Disposition' => "{$attachmentString}filename=\"{$sdkBuildAsset->getFileName()}\"",
            'ETag' => $sdkBuildAsset->getMd5()
        ];
    }
}

class ImageAssetResponse extends HttpResponse {

    public function __construct(ImageEntity $image, ImageTypeSizeEntity $imageTypeSize, $content, $code = HttpResponse::HTTP_OK)
    {
        $this->content = $content;
        $this->code = $code;

        $expiration = time()+(ONE_WEEK*4);

        $this->headers = [
            'Content-Type' => $image->getMimeType(),
            'Cache-Control' => "public, max-age: {$expiration}",
            'ETag' => $imageTypeSize->generateCacheBuster()
        ];
    }
}


/**
 * Stores an xml response
 */
class PdfResponse extends HttpResponse
{
    public function __construct($pdfContent)
    {
        $this->content = $pdfContent;
        $this->headers = [
            'Content-Type' => 'application/pdf;',
            'Cache-Control'=> 'no-cache, no-store, must-revalidate;',
            'Pragma' => 'no-cache;',
            'Expires: 0;'
        ];
    }
}