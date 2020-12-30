<?php
/**
 * i18n middleware
 *
 * @package i18n
 */
class i18nMiddleware extends Middleware
{

    protected $dictionaries = [];

    /**
     * Translations cache needs refresh
     */
    private $refresh_cache = false;

    /** @var  string */
    private $pathCacheKey;

    /**
     * Fetches all translations need for generating the page
     *
     * @param Request
     */
    public function process_request(Request $request)
    {
        $this->registerDictionary($request);

        $langsManager = $request->managers->languages();
        $i18nManager = $request->managers->i18n();

        $langId = $this->determine_language($request, $langsManager->getActiveI18nLanguageIds($request));

        $defaultLangId = $request->settings()->getContentDefaultLang();

        $this->pathCacheKey = $i18nManager->getUrlPathCacheKey($langId, $request->path);

        $translations = [];

        try {
            $translationKeys = $request->cache[$this->pathCacheKey];
            $translationKeys = array_unique($translationKeys);

            $translations = $i18nManager->getTranslationsByIds($request, $translationKeys, $langId);

        } catch (CacheEntryNotFound $c) {
            $this->refresh_cache = true;
        }

        $request->translations = new i18n($request->cache, $langId, $defaultLangId, $translations, $request->user->id);

        if ($request->user->is_authenticated && $request->readCookie('i18n_mode', false)) {
            $request->translations->set_edit_mode();
        }
    }

    /**
     * Saves all translations need for generating the page
     *
     * @param Request
     */
    public function process_response(Request $request, HttpResponse $response)
    {
        if ($request->translations->has_accessed_db() || $this->refresh_cache) {
            $translations = $request->translations->get_translations_cache();
            $translationKeys = array_unique(array_keys($translations));
            $request->cache->set($this->pathCacheKey, $translationKeys, i18nManager::CACHE_TIME_PATH);
        }

    }

    /**
     * Create a unique identifier from a url
     * We can't use the url directly since it can
     * contain special characters
     *
     * @param string $url
     * @return string
     */

    /**
     * Determine language
     *
     * @param Request $request
     * @param $available_langs
     * @return mixed|string
     */
    public static function determine_language(Request $request, $available_langs)
    {
        // default to english
        $lang = LanguagesManager::LANGUAGE_ENGLISH;
        if ($request->hasCookie(Request::COOKIE_UI_LANG) && in_array($request->readCookie(Request::COOKIE_UI_LANG), $available_langs)) {
            $lang = $request->readCookie(Request::COOKIE_UI_LANG);
        } elseif (array_key_exists('HTTP_ACCEPT_LANGUAGE', $request->server)) {
            $http_langs = explode(';', $request->server['HTTP_ACCEPT_LANGUAGE']);
            $http_langs = explode(',', array_get($http_langs, 0, ''));
            foreach ($http_langs as $l) {
                $l = array_get($request->config['languages_map'], $l, $l);
                if (in_array($l, $available_langs)) {
                    $lang = $l;
                    break;
                }
            }
        }
        return $lang;
    }

    /**
     * @param Request $request
     */
    protected function registerDictionary(Request $request)
    {
        $projectDir = $request->settings()->getProjectDir();
        $localFile = "${projectDir}/core/domain/modules/i18n.php";

        if (!in_array($localFile, $this->dictionaries)) {
            if (is_file($localFile)) {
                require($localFile);
                $this->dictionaries[] = $localFile;
                I18nDictionary::register_dictionary(T::get_dictionary());
            }
        }

    }

}

