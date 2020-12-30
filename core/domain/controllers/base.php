<?php
/**
 * Content base class
 *
 * @package content
 */
/** @var $request Request */
abstract class BaseContent extends Content
{
    const SLUG_INDEX = '';

    /**
     * Override of default ad tags
     */
    protected $ads = [];

    /**
     * @param Request $request
     * @param $template_data
     * @param string $template_file
     * @param bool|true $set_page_js_data
     * @return HtmlResponse
     */
    protected function renderPageResponse(Request $request, $template_data = [], $template_file = '',  $custom_bone = false, $set_page_js_data = false)
    {
        if (!$this->tree_default_is_set/* && !$custom_bone*/)
            $this->initializeResponseTemplate($request, [], !$custom_bone);

        if ($template_data)
            $this->assignTemplateData($template_data, $set_page_js_data);

        return $this->template->render_response($template_file, $custom_bone);
    }

    /**
     * @param Request $request
     * @param array $viewData
     * @param string $templateFile
     * @return string
     */
    protected function renderTemplate(Request $request, $viewData = [], $templateFile = '')
    {
        if (!$this->tree_default_is_set)
            $this->initializeResponseTemplate($request);

        if ($viewData)
            $this->assignTemplateData($viewData);

        return $this->template->render_template($templateFile);
    }

    /**
     * @param array $template_data
     * @param string $template_file
     * @return XmlResponse
     */
    protected function renderSitemapResponse($template_data = [], $template_file = '')
    {
        if ($template_data)
            $this->assignTemplateData($template_data);

        return $this->template->render_sitemap($template_file);
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function renderJsonModel(Request $request)
    {
        if (!$this->tree_default_is_set)
            $this->tree_default($request);

        $windowEsc = [
            'global' => $this->global_js_data->getDataArray(),
            'page' => $this->page_js_data->getDataArray(),
        ];

        return $windowEsc;
    }

    /**
     * @param array $template_data
     * @return JSONResponse
     */
    protected function renderJsonResponse($template_data = [], $responseCode = HttpResponse::HTTP_OK)
    {
        return new JSONResponse($template_data, $responseCode);
    }

    /**
     * @param Request $request
     * @param array $template_data
     * @param string $template_file
     * @return HtmlResponse
     */
    protected function renderAjaxResponse(Request $request, $template_data = [], $template_file = '')
    {
        return $this->renderPageResponse($request, $template_data, $template_file, true, false);
    }

    /**
     * @param string $url
     * @param int $code
     * @return HttpResponseRedirect
     */
    protected function redirect($url = '/', $code = HttpResponse::HTTP_FOUND)
    {
        return new HttpResponseRedirect($url, $code);
    }

    /**
     * @param Request $request
     * @return HttpResponseRedirect
     */
    protected function redirectToLogin(Request $request)
    {
        $targetUrl = base64_encode($request->getFullUrl());
        return $this->redirect($request->getWwwUrl("/auth/login?next={$targetUrl}"));
    }

    /**
     * @param $viewData
     * @param bool|false $set_page_js_data
     * @return $this
     * @throws Exception
     */
    protected function assignViewData($viewData, $set_page_js_data = false)
    {
        $this->assignTemplateData($viewData, $set_page_js_data);
        return $this;
    }

    /**
     * @param $viewData
     * @param bool|false $set_page_js_data
     * @return Template
     * @throws BaseManagerEntityException
     */
    protected function assignTemplateData($viewData = [], $set_page_js_data = false)
    {
        if ($set_page_js_data)
            $this->page_js_data->assign($this->extractJsArrayData($viewData));

        $this->template->assign($viewData);
        return $this->template;
    }

    /**
     * @param Request $request
     * @return $this
     */
    protected function initializeResponseTemplate(Request $request, $viewData = [], $displayModal = false)
    {
        if ($viewData)
            $this->template->assign($viewData);
        $this->template = $this->tree_default($request, $displayModal);
        return $this;
    }


    /**
     * Populates the variables needed in every template
     *
     * @param string $name list name
     * @return Template
     */

    protected function tree_default(Request $request, $displayModal = false)
    {
        $langsManager = $request->managers->languages();
        $emailTrackingManager = $request->managers->emailTracking();

        // Profiling
        $start_time = microtime(true);

        $t = parent::tree_default($request);

        if ($request->debug) {
            $t->enable_profiling();
            $t->enable_append_time();
        }

        // if ($request->user->is_staff)
        // $t['alerts'] = self::get_alerts($request->db, $request->config, $request->user);

        $t->assign($this->getDefaultTemplateVars($request));

        $this->tree_default_is_set = true;

        // Authenticated users variables
        if ($request->user->is_authenticated)
            $t->assign($this->getDefaultAuthUserTemplateVars($request, $displayModal));
        else  // Set my variables for anonymous
            $t->assign($this->getAnonymousUserVars($request));

        // Attempt to Acknowledge Email Click Tracking Parameters if both required params are set.
        $etId = $request->get->etId();
        $checksum = $request->get->checksum();

        // Check if params set
        // If the url is not the email tracking url ('pixels')
        // then the user has clicked a link from the email
        // Otherwise the user simply opened the email
        // We have 2 distinct times for opening and email and
        // clicking on a link from an email

        if ($etId && $checksum && $this->isNotPixelUrl($request) && !$this->isAdminEmailViewer($request)) {
            $emailTrackingManager->setEmailClickTime($request, $etId, $checksum);
        }

        $hasSessionFlashMessages = $request->user->session->getHasSessionMessages();
        $sessionFlashMessages = $request->user->session->getSessionMessages(true);

        $mixPanelEvents = $request->user->session->getSessionMixpanelEvents(true);
        // Always send page view event last.
        $mixPanelEvents[] = [
            'name' => 'pageView',
            'properties' => []
        ];

        // Temporary hack because session store is not initialized for guests
        // Todo: make SessionFlashMessages usable for non-authenticated users

        if ($request->get->readParam('betaSuccess') == 'true') {

            $successMessage = FlashMessagesManager::createEntity([
                DBField::USER_ID => null,
                DBField::TYPE => MSG_SUCCESS,
                DBField::CONTENT => json_encode([
                    DBField::BODY => 'Beta Signup Success!',
                    DBField::OPTIONS => []
                ])
            ]);

            array_unshift($sessionFlashMessages, $successMessage);
            $hasSessionFlashMessages = true;
        }

        // Assign window.esc data.
        $this->global_js_data->assign([
            // Base data
            TemplateVars::DEBUG_MODE_ENABLED => $request->debug,
            //TemplateVars::REQUEST_USER_IS_AUTHENTICATED => $request->user->is_authenticated() ? true : false,
            TemplateVars::REQUEST_SESSION_HAS_NOTIFICATIONS => $hasSessionFlashMessages,
            TemplateVars::REQUEST_SESSION_NOTY_MESSAGES => $sessionFlashMessages,
            TemplateVars::REQUEST_SESSION_MIXPANEL_EVENTS => $mixPanelEvents,
            TemplateVars::REQUEST_USER => $request->user,
            TemplateVars::USE_QTIP => $this->useToolTip,
            TemplateVars::USE_CHARTS => $this->useCharts,
            //TemplateVars::REQUEST_USER => $request->user->is_authenticated() ? $request->user->getEntity()->getJSONData() : [],
            // Constants in the template
            TemplateVars::CONFIG => [
                TemplateVars::STATIC_URL => $request->settings()->getStaticUrl(),
                TemplateVars::MEDIA_URL => $request->settings()->getMediaUrl(),
                TemplateVars::IMAGES_URL => $request->settings()->getImagesUrl(),
                TemplateVars::RAW_JS => $request->settings()->raw_js(),
                TemplateVars::RAW_CSS => $request->settings()->raw_css(),
                TemplateVars::IS_DEV => $request->settings()->is_dev(),
                TemplateVars::GA_ID => $request->settings()->getGaId(),
                TemplateVars::WWW_URL => $request->getWwwUrl(),
                TemplateVars::PLAY_URL => $request->getPlayUrl(),
                TemplateVars::DEVELOP_URL => $request->getDevelopUrl(),
                TemplateVars::API_URL => $request->getApiUrl(),
                TemplateVars::MIXPANEL_ID => $request->settings()->getMixpanelId(),
            ],
            TemplateVars::GLOBAL_JS_REQUEST => [
                // Request Attribution Information
                TemplateVars::REQUEST_ID => $request->requestId,
                TemplateVars::REQUEST_ET_ID => $request->get->etId(),
                TemplateVars::REQUEST_CHECKSUM => $request->get->checksum(),
                TemplateVars::REQUEST_UTM_MEDIUM => $request->get->utmMedium(),
                TemplateVars::REQUEST_UTM_SOURCE => $request->get->utmSource(),
                TemplateVars::REQUEST_UTM_CAMPAIGN => $request->get->utmCampaign(),
                TemplateVars::REQUEST_UTM_TERM => $request->get->utmTerm(),
                TemplateVars::REQUEST_GET_PARAMS => $request->get->params(),
                TemplateVars::PAGE_CURRENT_URL => $request->path,
                TemplateVars::REQUEST_BUILD_QUERY => !empty($request->get) ? '?'.http_build_query($request->get) : null,
                TemplateVars::PAGE_SECTION => $request->getSection(),
                TemplateVars::PAGE_APP => $request->app,
                TemplateVars::PAGE_CONTENT_AUDIENCE => $this->content_audience,
                TemplateVars::REQUEST_IS_AJAX => $request->is_ajax(),
                TemplateVars::GUEST_ID => $request->user->guest->getGuestId(),
                TemplateVars::GUEST_HASH => $request->user->guest->getGuestHash(),
                TemplateVars::SESSION_ID => $request->user->session->getSessionId(),
                TemplateVars::SESSION_HASH => $request->user->session->getSessionHash(),
                TemplateVars::REQUEST_IS_FIRST_VISIT_OF_GUEST => $request->user->guest->is_new_guest(),
                TemplateVars::REQUEST_IS_FIRST_VISIT_OF_SESSION => $request->user->session->is_new_session(),
                TemplateVars::REQUEST_HOST => $request->host,
            ],
        ]);
        if ($request->user->is_superadmin() )
            $this->global_js_data->assign([TemplateVars::REQUEST_USER_IS_SUPERADMIN => $request->user->is_superadmin()]);

        $pageJsData = '';
        $globalJsData = '';

        // Todo: this is a nasty hack
        if ($request->user->is_bot) {
            if ($request->app == 'play') {
                $pageJsData = $this->page_js_data->getJsonObject();
                $globalJsData = $this->global_js_data->getJsonObject();
            }
        } else {
            $pageJsData = $this->page_js_data->getJsonObject();
            $globalJsData = $this->global_js_data->getJsonObject();
        }



        $t->assign([
            TemplateVars::REQUEST_SESSION_HAS_NOTIFICATIONS => $hasSessionFlashMessages,
            TemplateVars::REQUEST_SESSION_NOTY_MESSAGES => $sessionFlashMessages,
            TemplateVars::REQUEST_GET_PARAMS => $request->get->params(),
            TemplateVars::REQUEST_IS_AJAX => $request->is_ajax(),
            TemplateVars::DEBUG => $request->debug,
            TemplateVars::DEBUG_ETLS => $request->debug ? $request->etls : [],
            TemplateVars::SUPERADMIN_IDS => Rights::getSuperAdminIds(),
            TemplateVars::REQUEST_USER => $request->user,
            TemplateVars::REQUEST_IS_PHONE => $request->user->isPhone,
            TemplateVars::REQUEST_IS_TABLET => $request->user->isTablet,
            TemplateVars::REQUEST_IS_MOBILE => $request->user->isMobile,
            TemplateVars::REQUEST_IS_COMPUTER => !$request->user->isMobile,
            TemplateVars::YEAR_NOW => date("Y"),
            TemplateVars::TIMEZONE_OFFSET => $request->user->get_timezone_offset(),
            TemplateVars::CACHE => $request->cache,
            TemplateVars::LOCAL_CACHE => $request->local_cache,
            TemplateVars::SHOW_FOOTER => true,
            TemplateVars::I18N => $request->translations,
            TemplateVars::I18N_LANGS => $langsManager->getActiveI18nLanguages($request),
            TemplateVars::I18N_ACTIVE_LANG => $langsManager->getLanguageById($request, $request->translations->get_lang()),
            TemplateVars::PAGE_ACTIVE_LANGUAGE => LanguagesManager::LANGUAGE_ENGLISH,
            // debug bar handling if auth module is not defined
            TemplateVars::DEBUG_HAS_USER => true,
            TemplateVars::DISPLAY_FOOTER => true,
            TemplateVars::DISPLAY_ACCOUNT_HEADER => true,
            // urls
            TemplateVars::REQUEST_ROOT => $request->root,
            TemplateVars::REQUEST_PATH => $request->path,
            TemplateVars::REQUEST_SCHEME => $request->scheme,
            TemplateVars::REQUEST_LI => array_get($request->get, 'li', null),
            TemplateVars::PAGE_CANONICAL => $request->path,
            TemplateVars::PAGE_SECTION => $request->getSection(),
            TemplateVars::REQUEST_USER_GUEST_DATA => $request->user->guest,
            TemplateVars::DEBUG_DEFAULT_TREE_TIME => 0,
            TemplateVars::PAGE_USES_BBCODE_EDITOR => false,
            TemplateVars::WWW_URL => $request->getWwwUrl(),
            TemplateVars::PLAY_URL => $request->getPlayUrl(),
            TemplateVars::DEVELOP_URL => $request->getDevelopUrl(),
            TemplateVars::API_URL => $request->getApiUrl(),
            TemplateVars::APP => $request->app,
            // Stats
            //TemplateVars::STATS => $stats,
            TemplateVars::REQUEST_USER_COUNTRY => $request->geoIpMapping->getCountryId(),
            TemplateVars::GLOBAL_JS_DATA => $globalJsData,
            TemplateVars::PAGE_JS_DATA => $pageJsData,
            TemplateVars::PAGE_IDENTIFIER => !empty($t->context[TemplateVars::PAGE_IDENTIFIER]) ? $t->context[TemplateVars::PAGE_IDENTIFIER] : $this->page_identifier,
            TemplateVars::USE_QTIP => $this->useToolTip,
            TemplateVars::USE_CHARTS => $this->useCharts,
            TemplateVars::BODY_BG_CLASS => $this->bodyBgClass,
            TemplateVars::IS_MARKETING_PAGE => $this->isMarketingPage,
        ]);

        // Profiling
        if ($request->debug) {
            $t->debugging = ($t->debugging || array_key_exists('template_debug', $request->get));
            $t->default_tree_time = microtime(true) - $start_time;
            $t->assign([
                TemplateVars::DEBUG_DEFAULT_TREE_TIME => round($t->default_tree_time * 1000, 2),
                TemplateVars::DEBUG_MEMORY_USED => format_filesize(memory_get_peak_usage()),
                TemplateVars::DEBUG_MEMORY_LIMIT => format_filesize(ini_get("memory_limit")),
                TemplateVars::DEBUG_GET_DATA => $request->get,
                TemplateVars::DEBUG_POST_DATA => $request->post,
                TemplateVars::DEBUG_COOKIES => $request->cookies
            ]);
        }

        return $t;
    }

    public function getFrameworkTemplateVars(Request $request)
    {
        return [
            TemplateVars::DEBUG => $request->debug,
            TemplateVars::REQUEST_USER => $request->user,
            TemplateVars::TIMEZONE_OFFSET => $request->user->get_timezone_offset(),
            TemplateVars::CACHE => $request->cache,
            TemplateVars::I18N => $request->translations
        ];
    }

    /**
     * Generic template vars (assigned on every page)
     *
     * @return array
     */
    public function getDefaultTemplateVars(Request $request)
    {
        $config = $request->config;
        $translations = $request->translations;

        return [
            // Request
            TemplateVars::REQUEST => $request,
            // General Settings
            TemplateVars::PAGE_TITLE => '',
            TemplateVars::CONFIG => $config,
            TemplateVars::RSS_TITLE => $config[ESCConfiguration::WEBSITE_NAME].' '.$translations['news'],
            TemplateVars::RSS_LINK => $config['rss_link'],
            TemplateVars::SHOW_ADS => $config['show_ads'],
            TemplateVars::WEBSITE_NAME => $config[ESCConfiguration::WEBSITE_NAME],
            TemplateVars::WEBSITE_DOMAIN => $config[ESCConfiguration::WEBSITE_DOMAIN],
            TemplateVars::EMAIL_DOMAIN => $config[ESCConfiguration::EMAIL_DOMAIN],
            TemplateVars::JS_SALT => $config['js_salt'],
            // Urls
            TemplateVars::STATIC_URL => $config[ESCConfiguration::STATIC_URL],
            TemplateVars::MEDIA_URL => $config[ESCConfiguration::MEDIA_URL],
            TemplateVars::IMAGES_URL => $config[ESCConfiguration::IMAGES_URL],
            TemplateVars::CDN_URL => $config['cdn_url'],
            // Page metadata
            TemplateVars::PAGE_DESCRIPTION => $config['default_page_description'],
            TemplateVars::PAGE_CREATE_DATE => $config['default_page_create_date'],
            TemplateVars::PAGE_OG_IMAGE => $request->getWwwUrl($request->settings()->getImagesUrl('ecosystem_diagram.png')),
            TemplateVars::PAGE_COPYRIGHT => $config['page_copyright'],
            TemplateVars::PAGE_FB_APP_ID => $config['page_fbapp_id'],
            TemplateVars::MD5_CSS => array_get($config, 'md5_css', ''),
            TemplateVars::MD5_JS => array_get($config, 'md5_js', ''),
            TemplateVars::PAGE_FEED_COMMENTS_COUNT => 2,
            // Translations
            TemplateVars::I18N => $translations,
            TemplateVars::REQUEST_USER_UI_LANG => $translations->get_lang(),
            TemplateVars::SHORT_DATE_FORMAT => $translations->get('locale.short-date-format'),
            TemplateVars::DATE_FORMAT => $translations->get('locale.date-format'),
            TemplateVars::TIME_FORMAT => $translations->get('locale.time-format'),
            // Extra files to load
            TemplateVars::PAGE_EXTRA_CSS_FILES => [],
            TemplateVars::PAGE_EXTRA_JS_FILES => [],
        ];
    }


    /**
     * User info (assigned for logged in users)
     *
     * @param Request $request
     * @return array
     */

    protected function getDefaultAuthUserTemplateVars(Request $request, $displayModal = false)
    {
        $usersManager = $request->managers->users();
        $emailTrackingManager = $request->managers->emailTracking();
        $organizationsManager = $request->managers->organizations();

        $email = [];
        $user = $request->user->getEntity();

        // If the user is logged in but not verified, check for invitation email record. We use this to display
        // account verification reminder notice in the site-wide header
        if ($request->user->is_authenticated() && !$request->user->is_verified())
            $email = $emailTrackingManager->getLatestUserActivationEmail($request, $user);

        $useCroppie = false;

        if ($displayModal && $request->app !== ESCApp::APP_PLAY && $user->display_profile_roadblock()) {

            $fields = $usersManager->getRoadBlockFormFields($request, $user);

            $form = new PostForm($fields, $request);
            $form->setTemplateFile('forms/auth/modal-onboarding.twig');

            $modalViewData = [
                TemplateVars::FORM => $form,
                TemplateVars::PROFILE_USER => $user,
                TemplateVars::WWW_URL => $request->getWwwUrl()
            ];

            // Load Croppie
            $croppieVars = [TemplateVars::USE_CROPPIE => $useCroppie = true];

            $this->page_js_data->assign($croppieVars);

            $this->displayNotyModal($request, 'forms/auth/user-onboarding.twig', $modalViewData);
        }

        if (in_array($request->app, ['develop', 'www', 'api'])) {
            $organizations = $organizationsManager->getOrganizationsByUserId($request, $request->user->id, false, false);
        } else {
            $organizations = [];
        }

        $flashMessages = $messages = $request->user->getFlashMessages();

        $this->global_js_data->assign([
            TemplateVars::REQUEST_USER_NOTY_MESSAGES => $flashMessages,
            TemplateVars::REQUEST_USER_HAS_NOTIFICATIONS => count($messages) > 0,
        ]);

        return [
            TemplateVars::REQUEST_USER_NOTY_MESSAGES => $flashMessages,
            TemplateVars::REQUEST_USER_HAS_NOTIFICATIONS => count($messages) > 0,
            TemplateVars::REQUEST_USER_VERIFICATION_EMAIL_RECORD => $email,
            TemplateVars::REQUEST_USER_INFO => $request->user->getEntity(),
            TemplateVars::USE_CROPPIE => $useCroppie,
            TemplateVars::USER_ORGANIZATIONS => $organizations
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function getAnonymousUserVars(Request $request)
    {
        $this->global_js_data->assign([TemplateVars::REQUEST_USER_HAS_NOTIFICATIONS => false]);

        return [

        ];
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function isAdminEmailViewer(Request $request)
    {
        $referer = $this->getRedirectBackUrl($request);
        return strpos($referer, '/admin/email-history/') !== false;
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function isNotPixelUrl(Request $request)
    {
        return get_from_url($request, 1) != 'pixels';
    }

    /**
     * @param Request $request
     * @param string $message
     * @return HtmlResponse
     * @throws BaseEntityException
     */
    public function render_404(Request $request, $message = 'Page not found', $is_ajax = false)
    {
        $t = $this->tree_default($request);
        $t->assign([
            // Page info
            TemplateVars::PAGE_TITLE => $message,
        ]);

        return $t->render_response('errors/404.twig', $is_ajax, HttpResponse::HTTP_NOT_FOUND);
    }

    /**
     * @param Request $request
     * @param string $template
     * @param array $viewData
     */
    protected function displayNotyModal(Request $request, $template_file = '', $viewData = [], $options = [])
    {
        $template = new Template($this->getDefaultTemplateVars($request));

        if ($viewData)
            $template->assign($viewData);

        $defaultOptions = [
            FlashMessagesManager::OPTION_MODAL => true,
            FlashMessagesManager::OPTION_TEMPLATE => FlashMessagesManager::TEMPLATE_WIDE,
            FlashMessagesManager::OPTION_LAYOUT => FlashMessagesManager::LAYOUT_FULL_MODAL,
            FlashMessagesManager::OPTION_CLOSEWITH => 'button'
        ];

        $options = array_merge($defaultOptions, $options);

        $content = $template->render_template($template_file);

        $request->user->session->sendSessionFlashMessage($content, MSG_MODAL, $options);
    }

}