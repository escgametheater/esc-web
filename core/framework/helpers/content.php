<?php
/**
 * Content Base Class
 * All Content classes should extend this class
 * It provides basic functionality for rendering a page:
 * - a dispatch function to parse the url
 * - functions to parse the menu and fetch forum posts
 *
 */

abstract class Content

{
    // Page Head Meta Open Graph PageType Definitions
    const OG_PAGE_TYPE_PRODUCT = 'product';
    const OG_PAGE_TYPE_PRODUCT_GROUP = 'product.group';
    const OG_PAGE_TYPE_PRODUCT_ITEM = 'product.item';
    const OG_PAGE_TYPE_BLOG = 'blog';
    const OG_PAGE_TYPE_ARTICLE = 'article';
    const OG_PAGE_TYPE_PROFILE = 'profile';
    const OG_PAGE_TYPE_WEBSITE = 'website';

    const TWITTER_CARD_SUMMARY = 'summary';
    const TWITTER_CARD_SUMMARY_LARGE = 'summary_large_image';

    // Content Controller Methods
    const METHOD_RENDER_DEFAULT = 'render_default';

    // Intended Page Audiences
    const AUDIENCE_CONSUMERS = 1;
    const AUDIENCE_CREATORS = 2;
    const AUDIENCE_COMMUNITY = 3;


    /**
     * request URL component used to dispatch to the page
     * Level1 content will use url1 for /something
     * Level2 content will use url2 for /sec/something
     * etc
     *
     * @var int
     */
    protected $url_key = 1;

    protected $useToolTip = false;
    protected $useCharts = false;

    protected $bodyBgClass = '';
    protected $isMarketingPage = false;

    /**
     * Pages available
     *
     * @var array
     */
    protected $pages = [];

    /**
     * The page identifier
     *
     * @var string
     */
    protected $page_identifier;

    /**
     * If the page/url represents a specific entity id/slug
     *
     * @var string|int
     */
    protected $page_entity_identifier;

    /**
     * Function to call in case of page not found
     *
     * @var string
     */
    protected $render_default = null;

    /**
     * Class responsible for instantiating the template renderer
     *
     * @var TemplateFactory
     */
    protected $templateFactory;

    /**
     * @var JSDataEntity
     */
    protected $global_js_data = [];

    /**
     * @var JSDataEntity
     */
    protected $page_js_data = [];

    /**
     * @var Template
     */
    protected $template;

    protected $tree_default_is_set = false;

    /**
     * @var int $content_audience;
     */
    protected $content_audience;

    protected $controllerArgs = [];

    /**
     * Constructor
     *
     * @param object Template factory
     */
    public function __construct($template_factory = null)
    {
        $this->templateFactory = $template_factory;
        $this->content_audience = self::AUDIENCE_CONSUMERS;
        //$this->template = $this->createBaseTemplate($request);
        $this->global_js_data = new JSDataEntity();
        $this->page_js_data = new JSDataEntity();
    }

    /**
     * Resolve and render a view
     *
     * @param Request request object
     * @param integer url index used to fetch the page name
     * @param array list of all the pages
     * @param string function to call if no page was matched
     * @param string to add to $request->root, defaults to $page/
     */
    public function render(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)
    {
        $func = $this->resolve($request, $url_key, $pages, $render_default, $root);

        if ($func === null)
            throw new Http404();

        return $this->$func($request, ...$this->controllerArgs);
    }

    /**
     * Resolve which view to call according to the url
     *
     * @param Request request object
     * @param integer url index used to fetch the page name
     * @param array list of all the pages
     * @param string function to call if no page was matched
     * @param string to add to $request->root, defaults to $page/
     */
    public function resolve(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)
    {
        default_to($url_key, $this->url_key);
        default_to($pages, $this->pages);
        default_to($render_default, $this->render_default);

        // Update $request->root
        if ($root !== null)
            $request->root .= $root;
        elseif (array_key_exists(strval($url_key - 1), $request->url))
            $request->root .= get_from_url($request, $url_key - 1) . '/';

        $page = get_from_url($request, $url_key);
        return array_get($pages, $page, $render_default);
    }

    /**
     * @return Template
     * @throws BaseTemplateException
     */
    protected function createBaseTemplate(Request $request = null)
    {
        if ($this->templateFactory === null)
            throw new BaseTemplateException('Template factory not set');
        return $this->template instanceof Template ? $this->template : $this->template = $this->templateFactory->get($request);
    }



    /**
     * Extracts JS Data from passed source array. Handles single dimension array of varying index content types.
     * Example: array with indexes containing all the following: a raw array, index with a single entity, an array
     * of only DBDataEntities, or single data.
     *
     * @param array $source_data
     * @param bool|false $dbOnlyFields
     * @return array
     */
    protected function extractJsArrayData(array $source_data, $dbOnlyFields = false)
    {
        $converted_data = [];
        foreach ($source_data as $key => $data) {
            $converted_data[$key] = $this->extractJsData($data, $dbOnlyFields);
        }
        return $converted_data;
    }

    /**
     * Extracts JS Data from passed source data variable. Handles single content source
     * Ex: array of same type of entities, or single entity, or single data types.
     *
     * @param $source_data
     * @param bool|false $dbOnlyFields
     * @return array|null
     */
    protected function extractJsData($source_data, $dbOnlyFields = false)
    {
        $data = null;

        // Data source can be either an object of type DBEntity, an array of DBEntities,
        // or generic forms of data like an array, a string, or an integer. Let's check and handle.
        if ($source_data instanceof DBDataEntity) {
            // Data is single DBEntity
            $data = $source_data->getJSONData($dbOnlyFields);
        } elseif ($source_data instanceof JSDataEntity) {
            $data = $source_data->getDataArray();
        } elseif (is_array($source_data)) {
            $first_key = reset($source_data);
            if ($first_key && $first_key instanceof DBDataEntity) {
                // Data is array of DBEntities
                $data = DBDataEntity::extractJsonDataArrays($source_data, $dbOnlyFields);
            } else {
                // Data is regular array
                $data = $source_data;
            }
        } elseif (!empty($source_data)) {
            // Data is String, Integer, Float, or Bool.
            $data = $source_data;
        } else {
            // Data is empty.
            $data = null;
        }
        return $data;
    }

    /**
     * @param array $viewData
     * @return $this
     * @throws BaseManagerEntityException
     */
    protected function assignPageJsViewData(array $viewData)
    {
        $this->page_js_data->assign($viewData);
        return $this;
    }

    /**
     * @param $cssClass
     * @returns $this
     */
    protected function setBodyBgClass($cssClass)
    {
        $this->bodyBgClass = $cssClass;
        return $this;
    }

    /**
     * @param bool $isMarketingPage
     * @return $this
     */
    protected function setIsMarketingPage($isMarketingPage = true)
    {
        $this->isMarketingPage = $isMarketingPage;
        return $this;
    }

    /**
     * @param bool $useTooltip
     * @return $this
     */
    protected function setUseTooltip($useTooltip = true)
    {
        $this->useToolTip = $useTooltip;

        return $this;
    }

    /**
     * @param bool $useCharts
     * @return $this
     */
    protected function setUseCharts($useCharts = true)
    {
        $this->useCharts = $useCharts;
        return $this;
    }

    /**
     * @param array $content_template_vars
     * @param Template|null $template
     * @return $this|Template
     * @throws Exception
     */
    public function assignContentTemplateVars(array $content_template_vars, Template $template = null)
    {
        if (!$template) {
            $this->template->assign($content_template_vars);
            return $this;
        } else {
            $template->assign($content_template_vars);
            return $template;
        }
    }

    /**
     * @param $trackerName
     * @param $gaId
     * @return $this
     */
    public function addExternalGaTracker($trackerName, $gaId)
    {
        if (!$this->page_js_data->offsetExists(TemplateVars::GA_IDS))
            $this->page_js_data->offsetSet(TemplateVars::GA_IDS, []);

        $externalGaIds = $this->page_js_data->offsetGet(TemplateVars::GA_IDS);

        $externalGaIds[$trackerName] = $gaId;

        $this->page_js_data->offsetSet(TemplateVars::GA_IDS, $externalGaIds);

        return $this;
    }

    /**
     * Populates the variables needed in every template
     *
     * @param Request request object
     * @return Template
     */
    protected function tree_default(Request $request)
    {
        return $this->createBaseTemplate($request);
    }

    /**
     * Propagate a 404 page
     * We override this to support fancy 404 pages and such.
     *
     * @param Request request object
     * @return Template
     */
    protected function render_404(Request $request, $message = 'Page not found', $is_ajax = false)
    {
        throw new Http404();
    }

    /**
     * Todo: deprecate this function - it moved to $request
     *
     * @param Request $request
     * @return array|mixed|string
     */
    protected function getRedirectBackUrl(Request $request) {
        return $request->getRedirectBackUrl();
    }

}


class TemplateVars {

    // Anon User Global Template Keys
    const FACEBOOK_LOGIN_URL = 'facebook_login_url';

    // Authenticated User Global Template Keys
    const REQUEST_USER_NOTY_MESSAGES = 'notifications';
    const REQUEST_USER_HAS_NOTIFICATIONS = 'has_notifications';
    const REQUEST_USER_UNREAD_PM_COUNT = 'pmunread_count';
    const REQUEST_USER_UNREAD_PMS = 'pmunread';
    const REQUEST_USER_READ_PMS = 'all_messages';
    const REQUEST_USER_VERIFICATION_EMAIL_RECORD = 'verification_email_record';
    const REQUEST_USER_INFO = 'user_info';

    // Shared Global Template Keys
    const SUMMARY = 'summary';
    const SUMMARIES = 'summaries';
    const ACTIVE_PAGE = 'active_page';
    const STATS = 'stats';
    const TODAY = 'today';
    const YEAR_NOW = 'year_now';
    const START_DAY = 'startday';
    const END_DAY = 'endday';
    const DEFAULT_LANG = 'default_lang';
    const DEFAULT_SORT = 'default_sort';
    const DEFAULTS = 'defaults';
    const CONTENT_LANG = 'content_lang';
    const CONTENT_LANG_DISPLAY = 'content_lang_display';
    const TEMPLATE_NAME = 'template_name';
    const LAYOUT = 'layout';
    const SUPERADMIN_IDS = 'superadmin_ids';

    // Shared Global Request Template Keys
    const REQUEST = 'request';
    const REQUEST_USER_UI_LANG = 'ui_lang';
    const REQUEST_USER = 'user';
    const REQUEST_USER_IS_AUTHENTICATED = 'is_authenticated';
    const REQUEST_USER_IS_STAFF = 'is_staff';
    const REQUEST_USER_IS_SUPERADMIN = 'is_superadmin';
    const REQUEST_USER_ENTITY = 'thisUser';
    const REQUEST_USER_HIDE_ADS = 'hide_ads';
    const REQUEST_USER_COUNTRY = 'country';
    const REQUEST_USER_GUEST_DATA = 'guest_data';
    const REQUEST_TRANSLATIONS = 'translations';
    const REQUEST_ROOT = 'root';
    const REQUEST_PATH = 'path';
    const REQUEST_SCHEME = 'scheme';
    const REQUEST_LI = 'li';
    const REQUEST_IS_AJAX = 'ajax';
    const REQUEST_GET_PARAMS = 'get';
    const REQUEST_COMMENT_PAGINATION = 'cpage';
    const REQUEST_QUERY = 'q';
    const REQUEST_SORT = 'sort';
    const REQUEST_IS_MOBILE = 'is_mobile';
    const REQUEST_IS_TABLET = 'is_tablet';
    const REQUEST_IS_PHONE = 'is_phone';
    const REQUEST_IS_COMPUTER = 'is_computer';
    const REQUEST_BUILD_QUERY = 'build_query';
    const REQUEST_HOST = 'host';
    const REQUEST_IS_FIRST_VISIT_OF_GUEST = 'is_first_visit_of_guest';
    const REQUEST_IS_FIRST_VISIT_OF_SESSION = 'is_first_visit_of_session';
    const REQUEST_SESSION_NOTY_MESSAGES = 'session_messages';
    const REQUEST_SESSION_HAS_NOTIFICATIONS = 'has_session_messages';
    const REQUEST_SESSION_MIXPANEL_EVENTS = 'mixpanel_events';
    const GUEST_ID = 'guest_id';
    const GUEST_HASH = 'guest_hash';
    const SESSION_ID = 'session_id';
    const SESSION_HASH = 'session_hash';


    // Tracking
    const REQUEST_ID = 'request_id';
    const REQUEST_ET_ID = 'request_et_id';
    const REQUEST_CHECKSUM = 'request_checksum';
    const REQUEST_UTM_MEDIUM = 'request_utm_medium';
    const REQUEST_UTM_SOURCE = 'request_utm_source';
    const REQUEST_UTM_CAMPAIGN = 'request_utm_campaign';
    const REQUEST_UTM_TERM = 'request_utm_term';

    // Browse
    const REQUEST_BUILD_QUERY_POPULAR = 'build_query_popular';
    const REQUEST_BUILD_QUERY_RECENT = 'build_query_recent';
    const REQUEST_BUILD_QUERY_FEATURED = 'build_query_featured';

    // Config Data
    const CONFIG = 'config';
    const RSS_TITLE = 'rss_title';
    const RSS_LINK = 'rss_link';
    const SHOW_ADS = 'showads';
    const WEBSITE_NAME = 'website_name';
    const WEBSITE_DOMAIN = 'website_domain';
    const EMAIL_DOMAIN = 'email_domain';
    const JS_SALT = 'js_salt';
    const MD5_CSS = 'md5_css';
    const MD5_JS = 'md5_js';
    const SHORT_DATE_FORMAT = 'short_date_format';
    const DATE_FORMAT = 'date_format';
    const TIME_FORMAT = 'time_format';
    const RAW_JS = 'raw_js';
    const RAW_CSS = 'raw_css';
    const IS_DEV = 'is_dev';
    const GA_ID = 'ga_id';
    const MIXPANEL_ID = 'mixpanel_id';
    const GA_IDS = 'ga_ids';
    const WWW_URL = 'www_url';
    const PLAY_URL = 'play_url';
    const DEVELOP_URL = 'develop_url';
    const API_URL = 'api_url';
    const HOST_URL = 'host_url';
    const APP = 'app';

    // Config - URL Prefixes
    const STATIC_URL = 'static_url';
    const UPLOAD_URL = 'upload_url';
    const REDIRECT_URL = 'redirect_url';
    const MEDIA_URL = 'media_url';
    const IMAGES_URL = 'images_url';
    const FORUM_URL = 'forum_url';
    const CDN_URL = 'cdn_url';
    const THUMBS_URL = 'thumbs_url';
    const URLS = 'urls';
    const CREATOR_DEMO_URL = 'creator_demo_url';
    const READER_DEMO_URL = 'reader_demo_url';

    // Page URL Vars
    const VIEW = 'view';
    const NEXT_CHAPTER_URL = 'next_chapter_url';
    const NEXT_IMAGE_URL = 'next_image_url';
    const CURRENT_IMAGE_URL = 'current_image_url';
    const PAGE_URI_PREFIX = 'uri_prefix';
    const PAGE_CURRENT_URL = 'current_url';
    const CURRENT_URI = 'current_uri';

    // Debug Keys
    const DEBUG = 'debug';
    const DEBUG_ETLS = 'debug_etls';
    const DEBUG_MODE_ENABLED = 'debug_mode_enabled';
    const DEBUG_DATA = 'debug_data';
    const DEBUG_HAS_USER = 'has_user';
    const DEBUG_DEFAULT_TREE_TIME = 'default_tree_time';
    const DEBUG_MEMORY_USED = 'memory_used';
    const DEBUG_MEMORY_LIMIT = 'memory_limit';
    const DEBUG_GET_DATA = 'getdata';
    const DEBUG_POST_DATA = 'postdata';
    const DEBUG_COOKIES = 'cookies';

    const TIMEZONE_OFFSET = 'timezone_offset';
    const CACHE = 'cache';
    const LOCAL_CACHE = 'local_cache';
    const SHOW_FOOTER = 'show_footer';
    const I18N = 'i18n';
    const I18N_LANGS = 'i18n_langs';
    const I18N_ACTIVE_LANG = 'i18n_active_lang';

    // Email
    const EMAIL_BODY = 'email_body';
    const RECIPIENT = 'recipient';
    const SENDER = 'sender';
    const CTA = 'cta';
    const MESSAGE = 'message';
    const CHECKSUM = 'checksum';
    const CURRENT_DATE = 'current_date';
    const TRACKING_PIXEL_URL = 'tracking_pixel_url';
    const ET_ID = 'et_id';
    const EMAIL = 'email';
    const EMAIL_RECORD = 'email_record';
    const EMAIL_GENERATOR = 'email_generator';

    // Generic Page Template Keys
    const PAGE_CONTENT_AUDIENCE = 'page_content_audience';
    const PAGE_IDENTIFIER = 'page';
    const PAGE_ENTITY_IDENTIFIER = 'page_entity_id';
    const PAGE_ARTIST_SLUG = 'page_artist_id';

    const USE_CROPPIE = 'use_croppie';
    const USE_SORTABLE = 'use_sortable';
    const USE_QTIP = 'use_qtip';
    const USE_CHARTS = 'use_charts';
    const BODY_BG_CLASS = 'body_bg_class';
    const IS_MARKETING_PAGE = 'is_marketing_page';
    const CAN_EDIT = 'can_edit';
    const CAN_MODERATE = 'can_moderate';
    const H1 = 'h1';
    const H2 = 'h2';
    const P = 'p';
    const COUNT = 'count';
    const EDGE_COUNT = 'edge_count';
    const NEAR_COUNT = 'near_count';
    const ADDRESS = 'address';

    // JS Data Array Keys
    const GLOBAL_JS_DATA = 'global_js_data';
    const PAGE_JS_DATA = 'page_js_data';
    const GLOBAL_JS_REQUEST = 'request';
    const GLOBAL_JS_TRACKING = 'tracking';

    const DISPLAY_FOOTER = 'display_footer';
    const DISPLAY_ACCOUNT_HEADER = 'display_account_header';


    // Page Header Keys
    const PAGE_TITLE = 'title';
    const PAGE_CANONICAL = 'page_canonical';
    const PAGE_DESCRIPTION = 'page_description';
    const PAGE_CREATE_DATE = 'page_create_date';
    const PAGE_COPYRIGHT = 'page_copyright';
    const PAGE_FB_APP_ID = 'page_fbapp_id';
    const PAGE_SECTION = 'section';
    const PAGE_APP = 'app';
    const PAGE_IS_ENTITY_PROFILE_ADMIN = 'page_is_profile_admin';
    const PAGE_USES_BBCODE_EDITOR = 'uses_bbcode_editor';
    const PAGE_OG_IMAGE = 'page_image';
    const PAGE_OG_IMAGE_WIDTH = 'page_image_width';
    const PAGE_OG_IMAGE_HEIGHT = 'page_image_height';
    const PAGE_OG_TYPE = 'page_type';
    const PAGE_TWITTER_CARD = 'page_twitter_card';
    const PAGE_EXTRA_CSS_FILES = 'extra_css';
    const PAGE_EXTRA_JS_FILES = 'extra_js';

    // Language Page Content Keys
    const PAGE_ACTIVE_LANGUAGE = 'thisLang';
    const PAGE_ACTIVE_LANGUAGE_NAME = 'full_lang';
    const ACTIVE_CONVERSATION = 'active_conversation';
    const ACTIVE_THREAD = 'active_thread';
    const PAGE_LANGUAGE_FILTERS = 'langFilters';
    const PAGE_LANGUAGE_FILTER_COUNT = 'langCount';
    const PAGE_DISPLAY_LANGUAGE_FILTER = 'display_language_filter';
    const PAGE_ACTIVE_RELEASES_TYPE = 'releasesType';
    const PAGE_LIST_TYPE = 'listType';

    // Category Page Content Keys
    const PAGE_CATEGORY_FILTERS = 'categoryFilters';
    const PAGE_ACTIVE_CATEGORY = 'active_category';
    const PAGE_ALL_CATEGORES = 'all_categories';
    const PAGE_FEED_COMMENTS_COUNT = 'feed_comments_count';

    const REGEN_DURATION = 'regen_duration';
    const REGEN_TIME = 'regen_time';
    const REGEN_TYPE = 'regen_type';

    const KPI_SUMMARY_TYPES = 'kpi_summary_types';

    const PAGE_ACTIVE_TYPE_NAME = 'type_name';
    const HOSTS_FORM = 'hosts_form';
    const FORM = 'form';
    const FORMS = 'forms';
    const EMAIL_FORM = 'email_form';
    const GAME_FORM = 'game_form';
    const ADDRESS_FORM = 'address_form';
    const PASSWORD_FORM = 'password_form';
    const PROFILE_FORM = 'profile_form';
    const BRANDING_FORM = 'branding_form';
    const SPONSOR_FORM = 'sponsor_form';
    const PERMISSIONS_FORM = 'permissions_form';
    const NEXT = 'next';
    const PREVIOUS = 'previous';
    const PROFILE_USER = 'profile_user';
    const USERS = 'users';
    const USERGROUPS = 'usergroups';
    const USERGROUP = 'usergroup';
    const USERGROUPS_RIGHTS = 'usergroups_rights';
    const RIGHTS = 'rights';
    const RIGHT = 'right';
    const GROUPED_RIGHTS = 'grouped_rights';
    const RIGHT_GROUPS = 'right_groups';
    const RIGHT_GROUP = 'right_group';
    const PAGINATOR = 'paginator';
    const INCLUDE_PREVNEXT = 'include_prevnext';
    const TOTAL_PAGES = 'total_pages';
    const MAX_USES = 'max_uses';

    const UI_LANGUAGES = 'ui_languages';

    const CONTROLLER_JS_FILE = 'controller_js_file';

    const CONTROLLER = 'controller';
    const TASKS = 'tasks';

    const ORGANIZATIONS = 'organizations';
    const ORGANIZATION_INVITE = 'organization_invite';
    const ORGANIZATION = 'organization';
    const ORG_ONBOARDED = 'org_onboarded';
    const ORGANIZATION_USERS = 'organization_users';
    const ORGANIZATION_USER = 'organization_user';
    const ORGANIZATION_USERS_INVITES = 'organization_users_invites';
    const ORGANIZATION_USER_INVITE = 'organization_user_invite';
    const ORGANIZATION_BASE_ROLE = 'organization_base_role';
    const ORGANIZATION_BASE_ROLES = 'organization_base_roles';
    const ORGANIZATION_BASE_RIGHT = 'organization_base_right';
    const ORGANIZATION_BASE_RIGHTS = 'organization_base_rights';
    const ORGANIZATION_RIGHTS = 'organization_rights';
    const ORGANIZATION_RIGHT = 'organization_right';
    const ORGANIZATION_ROLE = 'organization_role';
    const TOTAL_PENDING_INCOME = 'total_pending_income';
    const TOTAL_PAYABLE_PENDING_INCOME = 'total_payable_pending_income';
    const SETUP_ORGANIZATION = 'setup_organization';

    const HOSTS = 'hosts';
    const ACTIVE_HOST = 'active_host';
    const HOST_VERSION = 'host_version';
    const HOST_VERSIONS = 'host_versions';
    const HOST_ASSETS = 'host_assets';
    const HOST_INSTANCES = 'host_instances';
    const HOST_INSTANCE  = 'host_instance';
    const HOST_CONTROLLER = 'host_controller';
    const HOST_BUILD = 'host_build';
    const ACTIVE_HOST_BUILDS = 'active_host_builds';
    const SLUG = 'slug';
    const PLATFORM_SLUG = 'platform_slug';
    const PLATFORMS = 'platforms';

    const SDK = 'sdk';
    const SDK_VERSION = 'sdk_version';
    const SDK_VERSIONS = 'sdk_versions';
    const SDK_ASSETS = 'sdk_assets';
    const SDK_INSTANCES = 'sdk_instances';
    const SDK_INSTANCE  = 'sdk_instance';
    const SDK_CONTROLLER = 'sdk_controller';
    const SDK_BUILD = 'sdk_build';
    const ACTIVE_SDK_BUILDS = 'active_sdk_builds';
    
    const LATEST_BUILD_MAC = 'latest_build_mac';
    const LATEST_BUILD_WIN = 'latest_build_win';
    const OS = 'os';

    const GLOBAL = 'global';
    const BASE_HREF = 'base_href';
    const GAME_INSTANCE = 'game_instance';
    const USER_IS_HOST_ADMIN = 'user_is_host_admin';
    const INVITE_HASH = 'invite_hash';
    const CONTROLLER_PUB_SUB_CHANNELS = 'controller_pub_sub_channels';
    const PUB_NUB_CONFIG = 'pub_nub_config';
    const HOST = 'host';
    const HOST_DEVICE = 'host_device';
    const PUB_NUB = 'pub_nub';
    const SUBSCRIBE_KEY = 'subscribe_key';
    const PUBLISH_KEY = 'publish_key';
    const SSL = 'ssl';

    const CLOUD_GAMES = 'cloud_games';
    const LBE_GAMES = 'lbe_games';
    const GAMES = 'games';
    const GAME = 'game';
    const GAME_BUILDS = 'game_builds';
    const GAME_MOD_BUILDS = 'game_mod_builds';
    const GAME_MOD_BUILD = 'game_mod_build';
    const GAME_BUILD = 'game_build';
    const LIVE_GAME_BUILD = 'live_game_build';
    const LIVE_GAME_MOD_BUILD = 'live_game_mod_build';
    const GAME_VIDEO = 'game_video';
    const GAME_CONTROLLERS = 'game_controllers';
    const GAME_CONTROLLER = 'game_controller';
    const GAME_DATA = 'game_data';
    const GAME_ASSET = 'game_asset';
    const GAME_ASSETS = 'game_assets';
    const GAME_MOD_DATA = 'game_mod_data';
    const CUSTOM_GAME_MOD_ASSETS = 'custom_game_mod_assets';
    const CUSTOM_GAME_ASSETS = 'custom_game_assets';
    const CUSTOM_GAME_ASSET_LINK = 'custom_game_asset_link';
    const CUSTOM_GAME_ASSET_LINKS = 'custom_game_asset_links';
    const VTT_INTERFACE = 'vtt_interface';
    const GAME_DATA_DEFINITION = 'game_data_definition';
    const UPDATE_CHANNEL = 'update_channel';
    const ACTIVE_UPDATE_CHANNEL = 'active_update_channel';
    const UPDATE_CHANNELS = 'update_channels';
    const ACTIVE_BUILD_VERSION_SUMMARY = 'active_build_version_summary';
    const ACTIVE_BUILD_SUMMARIES = 'active_build_summaries';
    const GAME_BUILD_OPTIONS = 'game_build_options';
    const GAME_MOD_BUILD_OPTIONS = 'game_mod_build_options';
    const SELECTED_GAME_BUILD_ID = 'selected_game_build_id';
    const SELECTED_GAME_MOD_BUILD_ID = 'selected_game_mod_build_id';
    const GAME_PHASES = "game_phases";

    const GAME_DAY_TYPE = 'game_day_type';
    const CMS_SEAT_TYPE = 'cms_seat_type';

    const CHANGE_SUMMARY = 'change_summary';

    const HAS_BETA_ACCESS = 'has_beta_access';
    const CAN_HOST = 'can_host';
    const IS_NEW = 'is_new';
    const IS_KIOSK = 'is_kiosk';
    const HOST_DOWNLOAD_URL = 'host_download_url';
    const HOST_IMAGE = 'host_image';

    const LICENSE = 'license';
    const LICENSES = 'licenses';
    const LICENSE_USERS = 'license_users';

    const MANAGED_GAMES = 'managed_games';
    const ADMIN_GAMES = 'admin_games';
    const OTHER_GAMES = 'other_games';

    const ACTIVITIES = 'activities';


    // Orders, Invoices, Payments, Payment Service Tokens, etc.
    const ORDER = 'order';
    const ORDERS = 'orders';
    const ORDER_STATUSES = 'order_statuses';
    const ORDER_HELPER = 'order_helper';
    const PAYOUTS = 'payouts';
    const PAYMENTS = 'payments';
    const PAYMENT = 'payment';
    const PAYMENT_SERVICES = 'payment_services';
    const OWNER_PAYMENT_SERVICES = 'owner_payment_services';
    const OWNER_PAYMENT_SERVICE_TOKENS = 'owner_payment_service_tokens';
    const REVENUES = 'revenues';
    const REVENUES_CONFIRMED = 'revenues_confirmed';

    const HOST_LICENSE = 'host_license';


    const GAME_MODS = 'game_mods';
    const GAME_MOD = 'game_mod';
    const ACTIVE_ORGANIZATION = 'active_organization';

    const ACTIVATIONS = 'activations';
    const ACTIVATION = 'activation';
    const ACTIVATION_GROUPS = 'activation_groups';
    const ACTIVATION_GROUP = 'activation_group';

    const USER_STATS = 'user_stats';
    const USER_ORGANIZATIONS = 'user_organizations';
    const TOTAL_COINS = 'total_coins';

    const SERVICE_ACCESS_TOKEN_INSTANCES = 'service_access_token_instances';
    const SERVICE_ACCESS_TOKENS = 'service_access_tokens';

    const ORGANIZATION_ACTIVITIES = 'organization_activities';

}

class Sections {

    /**
     * My Home / Inbox
     */

    const MY_INDEX = '';
    const MY_SUBSECTION_INBOX = 'inbox';
    const MY_SUBSECTION_NOTIFICATIONS = 'notifications';


    const INBOX_SUBSECTION_NEW_CONVERSATION = 'new-conversation';
    const INBOX_SUBSECTION_REPLY = 'reply';
    const INBOX_SUBSECTION_DELETE_CONVERSATION = 'delete-pm';
    const INBOX_SUBSECTION_REPORT_CONVERSATION = 'report-pm';
    const INBOX_SUBSECTION_ADD_RECIPIENT = 'add-recipient';

    /**
     * Forums
     */

    // Thread Subsection Url Components
    const FORUMS_SUBSECTION_THREADS = 'threads';
    const FORUMS_SUBSECTION_DELETE_THREAD = 'delete-thread';
    const FORUMS_SUBSECTION_EDIT_THREAD = 'edit-thread';
    const FORUMS_SUBSECTION_REPORT_THREAD = 'report-thread';
    const FORUMS_SUBSECTION_NEW_THREAD = 'post';
    const FORUMS_SUBSECTION_TOGGLE_THREAD_STICKY = 'togglesticky';
    const FORUMS_SUBSECTION_SITEMAP_THREADS = 'sitemap-threads.xml';

    // Reply Subsection Url Components
    const FORUMS_SUBSECTION_REPORT_REPLY = 'report-reply';
    const FORUMS_SUBSECTION_DELETE_REPLY = 'delete-reply';
    const FORUMS_SUBSECTION_EDIT_REPLY = 'edit-reply';
    const FORUMS_SUBSECTION_REPLY = 'reply';

    // Forums + Shared Subsections
    const FORUMS_SUBSECTION_SUBSCRIBE = 'subscribe';
    const FORUMS_SUBSECTION_SITEMAP_FORUMS = 'sitemap-forums.xml';
    const FORUMS_SUBSECTION_AJAX_PREVIEW = 'ajax-preview';

    /**
     * Comics
     */

    const COMICS_SUBSECTION_CHAPTERS = 'chapters';
    const SUBSECTION_PROFILE_ADMIN = 'admin';

    /**
     * Artists
     */

    const ARTISTS_SUBSECTION_HOME = 'home';
    const ARTISTS_SUBSECTION_NEWS = 'news';


    const COMICS_SUBSECTION_PROFILE = 'details';
    const BROWSE_ARTISTS_SUBSECTION_BECOME = 'become-artist';

    const ARTISTS_SUBSECTION_DASHBOARD = 'publisher-dashboard';
    const ARTISTS_SUBSECTION_MANAGE_COMICS = 'manage-comics';
    const ARTISTS_SUBSECTION_MANAGE_NEWS = 'manage-news';
    const ARTISTS_SUBSECTION_EDIT_PROFILE = 'edit-profile';

    /**
     * Generic SubSection Slugs
     */

    const SUBSECTION_SITEMAP_XML = 'sitemap.xml';
}

class GlobalJsDataEntity extends JSDataEntity {
    public function setPageIdentifier($pageIdentifier) {
        $this->dataArray[TemplateVars::GLOBAL_JS_REQUEST][TemplateVars::PAGE_IDENTIFIER] = $pageIdentifier;
    }
}