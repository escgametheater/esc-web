<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 12/5/15
 * Time: 2:15 AM
 */

require "base-class.php";

class ESCConfigurationKeyNotSet extends Exception {}
class ESCFrameworkException extends Exception {}

class ESCApp {
    const APP_PLAY = 'play';
    const APP_WWW = 'www';
    const APP_IMAGES = 'images';
    const APP_GO = 'go';
    const APP_API = 'api';
}

class ESCConfiguration extends BaseAppConfiguration {

    /** @var  ESCConfiguration $instance */
    public static $instance;

    /**
     * @param null $config
     * @return ESCConfiguration
     */
    public static function getInstance($config = null)
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        } else {
            if (!$config) {
                global $CONFIG;
                $config = $CONFIG;
            }
            return self::$instance = new self($config);
        }
    }

    // Basic Settings
    const IS_DEV = 'is_dev';
    const IS_PROD = 'is_prod';
    const RUN_ETLS = 'run_etls';
    const SECRET = 'secret';
    const COMPANY_NAME = 'company_name';
    const WEBSITE_NAME = 'website_name';
    const WEBSITE_DOMAIN = 'website_domain';
    const EMAIL_DOMAIN = 'email_domain';
    const DATE_FORMAT = 'date_format';
    const DATE_FORMAT_SHORT = 'short_date_format';
    const DATE_FORMAT_SQL_POST_DATE = 'date_format_sql_post';
    const TIME_FORMAT = 'time_format';
    const ADMINS_EMAIL = 'admins_email';
    const SEND_EMAILS = 'send_emails';
    const DEBUG = 'debug';
    const BETA = 'beta';
    const TASKS = 'tasks';
    const MANAGERS = 'managers';
    const SIMPLE_MARKUP_DOMAINS = 'domains_with_simple_markup';
    const RAW_JS = 'raw_js';
    const RAW_CSS = 'raw_css';
    const RAW_ASSETS = 'raw_assets';
    const REDIRECTS = 'redirects';

    // Email Addresses
    const EMAIL_CONTENT_VIOLATION = 'content_violation_email';

    // Durations
    const SESSION_TIMEOUT = 'session_timeout';

    // CORS Settings
    const ALLOWED_REFERRERS = 'allowedreferrers';

    // Directories
    const DIR_PROJECT = 'project_dir';
    const DIR_FRAMEWORK = 'framework_dir';
    const DIR_LOG = 'log_dir';
    const DIR_MEDIA = 'media_dir';
    const DIR_UPLOAD = 'upload_dir';

    // Urls
    const STATIC_URL = 'static_url';
    const MEDIA_URL = 'media_url';
    const IMAGES_URL = 'images_url';
    const FORUMS_URL = 'forums_url';
    const CDN_URL = 'cdn_url';

    // Default Content Settings
    const DEFAULT_PAGE_DESCRIPTION = 'default_page_description';
    const DEFAULT_PAGE_IMAGE = 'default_page_image';
    const DEFAULT_PAGE_CREATE_DATE = 'default_page_create_date';
    const DEFAULT_PAGE_CONTENT_TYPE = 'default_content_type';
    const DEFAULT_CHARSET = 'default_charset';
    const DEFAULT_LANG = 'default_lang';
    const PAGE_COPYRIGHT = 'page_copyright';
    const PAGE_FBAPP_ID = 'page_fbapp_id';
    const GA_ID = 'ga_id';
    const MIXPANEL = 'mixpanel';

    // Locale and languages
    const LANGUAGES_MAP = 'languages_map';
    const DEFAULT_LOCALE = 'en-us';

    // Auth / Cookie Settings
    const COOKIE_DOMAIN = 'cookie_domain';
    const COOKIE_PREFIX = 'cookie_pre';
    const COOKIE_SECURE = 'cookie_secure';
    const JS_SALT = 'js_salt';

    // User Group Ids
    const GROUP_BANNED = 'banned_group';
    const GROUP_BANNED_SPECIAL = 'banned_special_group';
    const GROUP_REGISTERED = 'registered_group';
    const GROUP_AWAITING = 'awaiting_group';

    // SuperAdmins
    const SUPERADMINS = 'superadmins';

    // Sections
    const SECTIONS = 'sections';

    // Performance Settings
    const SLOW_PAGE_SECOND_COUNT = 'slow_page_cut';

    // CDN Settings
    const MEDIA_KEY = 'media_key';
    const MEDIA_PREFIX = 'media_prefix';
    const AWS = 'aws';

    /**
     * Basic Website Settings
     */

    /**
     * @return bool
     */
    public function is_dev()
    {
        return $this->offsetGet(ESCConfiguration::IS_DEV) ? true : false;
    }

    /**
     * @return bool
     */
    public function is_prod()
    {
        return $this->offsetGet(ESCConfiguration::IS_PROD) ? true : false;
    }


    /**
     * @return bool
     */
    public function raw_js()
    {
        return $this->offsetGet(ESCConfiguration::RAW_JS) ? true : false;
    }

    /**
     * @return bool
     */
    public function raw_css()
    {
        return $this->offsetGet(ESCConfiguration::RAW_CSS) ? true : false;
    }

    /**
     * @return bool
     */
    public function raw_assets()
    {
        return $this->offsetGet(ESCConfiguration::RAW_ASSETS) ? true : false;
    }

    /**
     * @return mixed
     * @throws ESCConfigurationKeyNotSet
     */
    public function getGaId()
    {
        $gaSettings = $this->offsetGetOrError(ESCConfiguration::GA_ID);

        if ($this->is_dev())
            return $gaSettings['test'];
        else
            return $gaSettings['live'];
    }

    /**
     * @return string|null
     */
    public function getMixpanelId()
    {
        $mixPanelSettings = $this->offsetGetOrError(ESCConfiguration::MIXPANEL);

        return $mixPanelSettings['client_id'] ?? null;
    }

    /**
     * @return string
     */
    public function getContentViolationEmail()
    {
        return $this->offsetGet(ESCConfiguration::EMAIL_CONTENT_VIOLATION);
    }


    /**
     * @return int|string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getWebsiteName()
    {
        return $this->offsetGetOrError(ESCConfiguration::WEBSITE_NAME);
    }

    /**
     * @return int|string
     */
    public function getCookieDomain()
    {
        return $this->offsetGetOrError(ESCConfiguration::COOKIE_DOMAIN);
    }

    /**
     * @return int|string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getWebsiteDomain()
    {
        return $this->offsetGetOrError(ESCConfiguration::WEBSITE_DOMAIN);
    }

    /**
     * @return int|string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getEmailDomain()
    {
        return $this->offsetGetOrError(ESCConfiguration::EMAIL_DOMAIN);
    }

    /**
     * @param string $schema_prefix
     * @return string
     */
    public function getWebsiteDomainRoot($schema_prefix = 'https')
    {
        return "{$schema_prefix}://{$this->getWebsiteDomain()}";
    }

    /**
     * @return int|string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getDateFormat()
    {
        return $this->offsetGetOrError(ESCConfiguration::DATE_FORMAT);
    }

    /**
     * @return int|string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getShortDateFormat()
    {
        return $this->offsetGetOrError(ESCConfiguration::DATE_FORMAT_SHORT);
    }

    /**
     * @return int|string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getSqlPostDateFormat()
    {
        return $this->offsetGetOrError(ESCConfiguration::DATE_FORMAT_SQL_POST_DATE);
    }

    /**
     * @return string
     */
    public function getNiceDateFormat()
    {
        return 'M d, Y';
    }

    /**
     * @return string
     */
    public function getNiceShortDateFormat()
    {
        return 'M Y';
    }

    /**
     * @return int|string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getTimeFormat()
    {
        return $this->offsetGetOrError(ESCConfiguration::TIME_FORMAT);
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getAdminsEmail()
    {
        return $this->offsetGetOrError(ESCConfiguration::ADMINS_EMAIL);
    }

    /**
     * Durations
     */
    public function getSessionTimeout()
    {
        return $this->offsetGetOrError(ESCConfiguration::SESSION_TIMEOUT);
    }

    /**
     * @return array
     * @throws ESCConfigurationKeyNotSet
     */
    public function getRedirectUrls()
    {
        return $this->offsetGetOrError(ESCConfiguration::REDIRECTS);
    }

    /**
     * Url Paths
     */

    public function getStaticUrl()
    {
        return $this->offsetGetOrError(ESCConfiguration::STATIC_URL);
    }

    /**
     * @param string $suffix
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getMediaUrl($suffix = '')
    {
        return $this->offsetGetOrError(ESCConfiguration::MEDIA_URL).$suffix;
    }

    /**
     * @param string $suffix
     * @return int|string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getImagesUrl($suffix = '')
    {
        return $this->offsetGetOrError(ESCConfiguration::IMAGES_URL).$suffix;
    }

    /**
     * @return int|string
     */
    public function getPlaceHolderAvatarPath()
    {
        return $this->getImagesUrl("avatars/no-avatar.jpg");
    }


    /**
     * @return int|string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getCdnUrl()
    {
        return $this->offsetGetOrError(ESCConfiguration::CDN_URL);
    }

    /**
     * Server Directory Paths
     */

    public function getProjectDir($suffix = null)
    {
        return $this->offsetGetOrError(ESCConfiguration::DIR_PROJECT).$suffix;
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getFrameworkDir()
    {
        return $this->offsetGetOrError(ESCConfiguration::DIR_FRAMEWORK);
    }

    /**
     * @return int|string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getLogDir()
    {
        return $this->offsetGetOrError(ESCConfiguration::DIR_LOG);
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getMediaDir()
    {
        return $this->offsetGetOrError(ESCConfiguration::DIR_MEDIA);
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getUploadDir()
    {
        return $this->offsetGetOrError(ESCConfiguration::DIR_UPLOAD);
    }

    /**
     * Default Content Settings
     */

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getContentDefaultPageDescription()
    {
        return $this->offsetGetOrError(ESCConfiguration::DEFAULT_PAGE_DESCRIPTION);
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getContentDefaultPageImage()
    {
        return $this->offsetGetOrError(ESCConfiguration::DEFAULT_PAGE_IMAGE);
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getContentDefaultPageCreateDate()
    {
        return $this->offsetGetOrError(ESCConfiguration::DEFAULT_PAGE_CREATE_DATE);
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getContentDefaultPageCopyright()
    {
        return $this->offsetGetOrError(ESCConfiguration::PAGE_COPYRIGHT);
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getContentFacebookAppId()
    {
        return $this->offsetGetOrError(ESCConfiguration::PAGE_FBAPP_ID);
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getContentDefaultContentType()
    {
        return $this->offsetGetOrError(ESCConfiguration::DEFAULT_PAGE_CONTENT_TYPE);
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getContentDefaultCharset()
    {
        return $this->offsetGetOrError(ESCConfiguration::DEFAULT_CHARSET);
    }

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getContentDefaultLang()
    {
        return $this->offsetGetOrError(ESCConfiguration::DEFAULT_LANG);
    }

    /**
     * Locale and Languages
     */

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getLocaleLanguagesMappings()
    {
        return $this->offsetGetOrError(ESCConfiguration::LANGUAGES_MAP);
    }


    /**
     * Auth/Cookie Settings
     */


    /**
     * @param $key
     * @return string
     */
    public function getFullCookieKey($key)
    {
        return $this->getCookiePrefix().$key;
    }


    /**
     * @return string|null
     * @throws ESCConfigurationKeyNotSet
     */
    public function getCookiePrefix()
    {
        return $this->offsetGetOrError(ESCConfiguration::COOKIE_PREFIX);
    }

    /**
     * @return bool
     * @throws ESCConfigurationKeyNotSet
     */
    public function getCookieIsSecure()
    {
        return $this->offsetGetOrError(ESCConfiguration::COOKIE_SECURE);
    }

    /**
     * @return mixed
     * @throws ESCConfigurationKeyNotSet
     */
    public function getJsSalt()
    {
        return $this->offsetGetOrError(ESCConfiguration::JS_SALT);
    }

    /**
     * @return mixed
     * @throws ESCConfigurationKeyNotSet
     */
    public function getSecret()
    {
        return $this->offsetGetOrError(ESCConfiguration::SECRET);
    }


    /**
     * User Group Ids
     */

    /**
     * @return int
     * @throws ESCConfigurationKeyNotSet
     */
    public function getGroupIdBannedUsers()
    {
        return $this->offsetGetOrError(ESCConfiguration::GROUP_BANNED);
    }

    /**
     * @return int
     * @throws ESCConfigurationKeyNotSet
     */
    public function getGroupIdSpecialBannedUsers()
    {
        return $this->offsetGetOrError(ESCConfiguration::GROUP_BANNED_SPECIAL);
    }

    /**
     * @return int
     * @throws ESCConfigurationKeyNotSet
     */
    public function getGroupIdVerifiedUsers()
    {
        return $this->offsetGetOrError(ESCConfiguration::GROUP_REGISTERED);
    }

    /**
     * @return int
     * @throws ESCConfigurationKeyNotSet
     */
    public function getGroupIdUnverifiedUsers()
    {
        return $this->offsetGetOrError(ESCConfiguration::GROUP_AWAITING);
    }

    /**
     * Super Admins
     */

    /**
     * @return string
     * @throws ESCConfigurationKeyNotSet
     */
    public function getSuperAdminUserIds()
    {
        return $this->offsetGetOrError(ESCConfiguration::SUPERADMINS);
    }

    /**
     * Performance Settings
     */

    /**
     * @return int
     * @throws ESCConfigurationKeyNotSet
     */
    public function getSlowPageSecondCount()
    {
        return $this->offsetGetOrError(ESCConfiguration::SLOW_PAGE_SECOND_COUNT);
    }

    /**
     * CDN Settings
     */


    /**
     * @return int
     * @throws ESCConfigurationKeyNotSet
     */
    public function getMediaCdnKey()
    {
        return $this->offsetGetOrError(ESCConfiguration::MEDIA_KEY);
    }


    /**
     * @return int
     * @throws ESCConfigurationKeyNotSet
     */
    public function getAwsSettings()
    {
        return $this->offsetGetOrError(ESCConfiguration::AWS);
    }

    /**
     * Wrapper function to fetch from Config Array or throw an Exception
     *
     * @param $key
     * @return int|string|array
     * @throws ESCConfigurationKeyNotSet
     */
    protected function offsetGetOrError($key)
    {
        if (isset($this->dataArray[$key]))
            return $this->dataArray[$key];
        else
            throw new ESCConfigurationKeyNotSet("Config Key: '".$key."' not set.");
    }

}

/**
 * Exceptions
 */

class ExtractionException extends Exception {
}
class UnrecoverableExtractionException extends Exception {
}