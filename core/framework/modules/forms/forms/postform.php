<?php
/**
 * PostForm class
 * wrapper for Post class for post forms
 *
 */

class PostForm extends Form
{
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
     * PostForm constructor.
     * @param FormField|FormField[] $fields
     * @param Request $request
     * @param null $data
     * @param null $files
     * @param null $user_id
     */
    public function __construct($fields, Request $request, $data = null, $files = null, $user_id = null)
    {
        $this->is_post = $request->is_post();
        $this->is_ajax = $request->is_ajax();
        $this->is_bot = $request->user->is_bot;
        $this->previous_url = $request->getRedirectBackUrl();
        $this->request_id = $request->requestId;

        $this->user = $request->user;

        if ($files === null)
            $files = $request->files;
        if ($user_id === null && $request->user->is_authenticated)
            $user_id = $request->user->id;
        parent::__construct($fields, $request->translations, $this->is_post ? $request->post : $data, $files, $user_id, $request);
    }

    /**
     * @return bool
     */
    public function is_valid()
    {
        if (!$this->is_post)
            return false;
        else
            return parent::is_valid();
    }


    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @param $next
     * @param null $content
     * @return JSONResponse
     */
    public function handleRenderJsonSuccessResponse($next, $content = null)
    {
        return new JSONResponse(
            new JsFormResponseEntity(
                JsFormResponseEntity::STATUS_SUCCESS,
                $next,
                $content
            )
        );
    }

    /**
     * @return JSONResponse
     */
    public function handleRenderJsonErrorResponse()
    {
        return new JSONResponse(
            new JsFormResponseEntity(
                JsFormResponseEntity::STATUS_ERROR,
                $this->previous_url,
                $this->render()
            )
        );
    }
}



class JsFormResponseEntity extends JSDataEntity
{
    const KEY_STATUS = 'status';
    const KEY_DATA = 'data';
    const KEY_DATA_CONTENT = 'content';
    const KEY_DATA_NEXT_URL = 'nextUrl';

    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR = 'error';

    /**
     * FormJsDataEntity constructor.
     * @param string $status
     * @param array $content
     */
    public function __construct($status = '', $nextUrl = '', $content = [])
    {
        parent::__construct([]);

        $this->assign([
            self::KEY_STATUS => $status,
            self::KEY_DATA => [
                self::KEY_DATA_NEXT_URL => $nextUrl,
                self::KEY_DATA_CONTENT => $content
            ]
        ]);
    }
}

require "image-upload.php";