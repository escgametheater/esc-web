<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/4/18
 * Time: 2:20 PM
 */

interface BaseApiControllerV1CRUDInterface {

    /**
     * @param Request $request
     * @return HttpResponse
     */
    public function handle_index(Request $request) : HttpResponse;

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_get(Request $request) : ApiV1Response;

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_create(Request $request) : ApiV1Response;

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_update(Request $request) : ApiV1Response;

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_delete(Request $request) : ApiV1Response;

}

abstract class BaseApiV1Controller extends BaseContent {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = false;

    protected $manager;

    /** @var ApiV1PostForm */
    protected $form;

    protected $responseCode = HttpResponse::HTTP_OK;
    protected $results = [];
    protected $facets = [];

    protected $errors = [];

    protected $perPage = 10;
    protected $page = 1;
    protected $totalResults = 1;

    /** @var int  */
    protected $url_key;

    /**
     * BaseApiController constructor.
     * @param null $template_factory
     * @param null $urlKey
     * @param BaseEntityManager|null $manager
     */
    public function __construct($template_factory = null, $urlKey = null, BaseEntityManager $manager = null)
    {
        if ($urlKey)
            $this->url_key = $urlKey;

        if ($manager)
            $this->manager = $manager;

        parent::__construct($template_factory);
    }

    /**
     * @param Request $request
     * @param null $url_key
     * @param null $pages
     * @param null $render_default
     * @param null $root
     * @return ApiV1Response
     */
    public function render(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)  : HttpResponse
    {
        if (static::REQUIRES_POST && !$request->is_post()) {
            $this->responseCode = HttpResponse::HTTP_METHOD_NOT_ALLOWED;
            return $this->renderApiV1Response($request);
        }

        if (static::REQUIRES_AUTH && !$request->user->is_authenticated()) {
            $this->responseCode = HttpResponse::HTTP_METHOD_NOT_ALLOWED;
            return $this->renderApiV1Response($request);
        }


        if (method_exists($this, 'pre_handle')) {
            $this->pre_handle($request);
        }

        return parent::render($request, $url_key, $pages, $render_default, $root);

    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function renderApiV1Response(Request $request) : ApiV1Response
    {
        $pagination = [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total_pages' => ceil($this->totalResults/$this->perPage)
        ];

        if (static::REQUIRES_POST && !$request->is_post())
            $this->errors['method'] = "Request method needs to be POST.";

        if (static::REQUIRES_AUTH && !$request->user->is_authenticated())
            $this->errors['auth'] = "Request requires authentication.";

        if ($this->form instanceof Form) {
            foreach ($this->form->getErrors() as $section => $errors) {

                if ($section == ApiV1PostForm::KEY_PAYLOAD && is_array($errors)) {
                    foreach ($errors as $key => $error) {
                        if (!$this->form->has_field($key)) {
                            $this->errors['form'][] = $error;
                        } else {
                            $this->errors['fields'][$key] = $error;
                        }
                    }
                } elseif ($section == ApiV1PostForm::KEY_META && is_array($errors)) {

                } else {
                    $this->errors['form'][] = $errors;
                }
            }
        }

        if (!$this->form || !$this->form->is_valid())
            $this->responseCode = HttpResponse::HTTP_BAD_REQUEST;

        elseif (!isset($this->results))
            $this->responseCode = HttpResponse::HTTP_NO_CONTENT;

        return new ApiV1Response($request, $this->results, $pagination, $this->errors, $this->responseCode);
    }

    /**
     * @param array $results
     */
    protected function setResults($results = null)
    {
        if ($results instanceof DBManagerEntity || is_string($results) || is_numeric($results))
            $results = [$results];

        $this->results = $results;
    }

    /**
     * @param Request $request
     * @return ApiV1PostForm
     */
    protected function buildGetEntityForm(Request $request, $fields = [], $defaults = [])
    {
        if (!$fields) {
            $fields = [
                new IntegerField(DBField::ID, 'Primary Key')
            ];
        }

        return new ApiV1PostForm($fields, $request, $defaults);
    }
}

class ApiV1PostForm extends Form {

    const KEY_PAYLOAD = 'payload';

    const KEY_META = 'meta';
    const KEY_META_CLIENT_REQUEST_ID = 'client_request_id';
    const KEY_META_EXPAND = 'expand';

    /** @var array $rawMeta */
    public $rawMeta = [];
    /** @var FormField[] $metaFields */
    public $metaFields = [];
    public $cleanedMetaData = [];

    /** @var bool|null */
    protected $isValid;

    protected $pkField = DBField::ID;

    protected $rawPost = [];

    /**
     * @var bool
     */
    protected $is_post = false;
    protected $is_bot = false;

    protected $previous_url;

    /**
     * @var bool
     */
    protected $is_ajax = false;

    protected $settings = [];

    /**
     * ApiV1PostForm constructor.
     * @param $fields
     * @param Request $request
     * @param array $data
     * @param null $files
     * @param null $user_id
     */
    public function __construct($fields, Request $request, $data = [], $files = null, $user_id = null)
    {
        $this->is_post = $request->is_post();
        $this->is_ajax = $request->is_ajax();
        $this->is_bot = $request->user->is_bot;

        $this->user = $request->user;

        $metaFields = [
            new CharField(self::KEY_META_CLIENT_REQUEST_ID, 'Client Request Id', 0, false),
            new JSONBooleanField(self::KEY_META_EXPAND, 'Expand sub-objects', false)
        ];

        $this->addMetaFields($metaFields);

        if ($files === null)
            $files = $request->files;
        if ($user_id === null && $request->user->is_authenticated)
            $user_id = $request->user->id;

        if ($request->is_post()) {
            $this->rawPost = $request->post;

            if (array_key_exists(self::KEY_PAYLOAD, $this->rawPost)) {
                $data = $this->rawPost[self::KEY_PAYLOAD];
            } else {
                $data = [];
            }

            if (array_key_exists(self::KEY_META, $this->rawPost)) {
                $this->rawMeta = $this->rawPost[self::KEY_META];
            }
        }

        parent::__construct($fields, $request->translations, $data, $files, $user_id);
    }


    /**
     * @return bool
     */
    public function is_valid()
    {
        if (is_null($this->isValid)) {

            $this->isValid = true;

            if (!$this->is_post || !$this->is_bound || $this->errors) {
                $this->isValid = false;
                return $this->isValid;
            } else {

                if (!array_key_exists(self::KEY_META, $this->rawPost)) {
                    $this->errors['envelope'] = 'Envelope master property meta[] is missing.';
                    $this->isValid = false;
                    return $this->isValid;
                }

                if (!array_key_exists(self::KEY_PAYLOAD, $this->rawPost)) {
                    $this->errors['envelope'] = 'Envelope master property payload[] is missing.';
                    $this->isValid = false;
                    return $this->isValid;
                }

                $validOptionIds = [];

                foreach ($this->fields as $field) {
                    if (!array_key_exists($field->get_name(), $this->rawPost[self::KEY_PAYLOAD])) {

                        if ($field->is_required) {
                            $this->errors[self::KEY_PAYLOAD][$field->get_name()]['message'] = 'Field is missing.';
                            $this->isValid = false;
                        }

                    } else {
                        if (!$field->is_valid($this)) {
                            $this->isValid = false;
                            $this->errors[self::KEY_PAYLOAD][$field->get_name()]['message'] = $field->getErrorMsg();
                            if ($field instanceof SelectField) {
                                if (isset($field->options[0])) {
                                    if ($field->options[0] instanceof DBManagerEntity)
                                        $validOptionIds = extract_pks($field->options);
                                    else
                                        $validOptionIds = array_extract(DBField::ID, $field->options);
                                }

                                $this->errors[self::KEY_PAYLOAD][$field->get_name()]['valid_options'] = $validOptionIds;
                            }

                        } else {
                            $this->cleaned_data[$field->get_name()] = $field->get_cleaned_value($this);
                        }
                    }
                }

                foreach ($this->metaFields as $field) {
                    if (!array_key_exists($field->get_name(), $this->rawMeta)) {
                        $this->isValid = false;
                        $this->errors[self::KEY_META][$field->get_name()] = "{$field->get_name()} field is required to exist";

                    } elseif (!$field->is_valid($this)) {

                        $this->isValid = false;
                        $this->errors[self::KEY_META][$field->get_name()]['message'] = $field->getErrorMsg();
                        if ($field instanceof SelectField) {
                            $validOptionIds = extract_pks($field->options);
                            $this->errors[self::KEY_META][$field->get_name()]['valid_options'] = $validOptionIds;
                        }

                    } else {
                        $this->cleanedMetaData[$field->get_name()] = $field->get_cleaned_value($this);
                    }
                }
            }
        }

        return $this->isValid;
    }

    /**
     * @param FormField[]|FormField $fields
     */
    public function addMetaFields($fields)
    {
        if ($fields instanceof FormField) {
            $this->metaFields[] = $fields;
        } else if (is_array($fields)) {
            foreach ($fields as $field) {
                if ($field instanceof FormField) {
                    $this->metaFields[] = $field;
                }
            }
        }
    }

    /**
     * @return int|null|string
     */
    public function getPkValue()
    {
        return $this->getCleanedValue($this->pkField);
    }


    /**
     * @return bool
     */
    public function getExpand()
    {
        return true;
        //return $this->cleanedMetaData[self::KEY_META_EXPAND];
    }

    /**
     * @return FormField
     */
    public function getClientRequestId()
    {
        return $this->cleanedMetaData[self::KEY_META_CLIENT_REQUEST_ID];
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }


}

class ApiV1Response extends JSONResponse {

    const KEY_META = 'meta';
    const KEY_PAYLOAD = 'payload';

    const KEY_DEBUG = 'debug';

    protected $rawResponse = [];

    /**
     * BaseApiV1ResponseEnvelope constructor.
     * @param Request $request
     * @param array $results
     * @param array $pagination
     * @param array $errors
     */
    public function __construct(Request $request, $results = [], $pagination = [], $errors = [], $code)
    {
        $processedResults = $results instanceof DBManagerEntity ? $results->getJSONData() : DBDataEntity::extractJsonDataArrays($results);

        $responseData = [
            self::KEY_META => [
                'request_id' => $request->requestId,
                'user_id' => $request->user->id
            ],
            self::KEY_PAYLOAD => [
                'results' => $processedResults,
                'pagination' => $pagination,
                'facets' => [],
                'errors' => $errors
            ]
        ];

        if ($request->user->is_superadmin()) {

            $cache = Cache::get_cache();
            $localCache = Cache::get_local_cache(true);

            $responseData[self::KEY_META][self::KEY_DEBUG] = [
                'sql' => [
                    'sql_queries' => Debug::sql_queries(true),
                    'sql_queryTime' => Debug::total_sql_query_time()
                ],
                'cache' => [
                    $cache->getBackendName() => [
                        "get" => $cache->get_get_queries(),
                        "set" => $cache->get_set_queries(),
                        'connect' => $cache->getConnectTime(),
                    ],
                    $localCache->getBackendName() => [
                        "get" => $localCache->get_get_queries(),
                        "set" => $localCache->get_set_queries(),
                        'connect' => $localCache->getConnectTime(),
                    ]
                ]
            ];
        }

        $responseData[self::KEY_META]['response_time'] = Debug::get_time_elapsed();

        $this->rawResponse = $responseData;

        parent::__construct($responseData, $code);
    }

    /**
     * @return array
     */
    public function getRawResponse()
    {
        return $this->rawResponse;
    }
}