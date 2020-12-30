<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 5/20/18
 * Time: 9:34 PM
 */


class i18nManager extends BaseEntityManager
{
    protected $entityClass = TranslationEntity::class;
    protected $table = Table::i18n;
    protected $table_alias = TableAlias::i18n;
    protected $pk = DBField::PHRASE_ID;

    public static $fields = [
        DBField::PHRASE_ID,
        DBField::LANGUAGE_ID,
        DBField::TEXT,
        DBField::UPDATE_TIME,
        DBField::UPDATER_ID,
        DBField::CREATE_TIME
    ];

    const GNS_KEY_PREFIX = GNS_ROOT.'.i18n';

    const CACHE_TIME_PATH = ONE_DAY;
    const CACHE_TIME_TRANSLATION = ONE_WEEK;

    /**
     * @param $path
     * @return string
     */
    public function getUrlPathCacheKey($langId, $path)
    {
        $pathHash = md5($path);
        return self::GNS_KEY_PREFIX.".urls.{$langId}.{$pathHash}";
    }

    /**
     * @param $translationId
     * @param $langId
     * @return string
     */
    public static function generateTranslationCacheKey($translationId, $langId)
    {
        return self::GNS_KEY_PREFIX.".{$langId}.{$translationId}";
    }

    /**
     * @param Request $request
     * @return mixed|string
     */
    public static function getCookieLang(Request $request)
    {
        return $request->readCookie(Request::COOKIE_UI_LANG) ? $request->readCookie(Request::COOKIE_UI_LANG) : LanguagesManager::LANGUAGE_ENGLISH; ENGLISH;
    }


    /**
     * @param Request $request
     * @param $ids
     * @return array
     */
    public function getTranslationsByIds(Request $request, $ids, $langId = 'en')
    {
        $cacheTime = self::CACHE_TIME_TRANSLATION;

        $uncachedPks = [];
        $results = [];
        $cache_keys = [];
        $uncachedResults = [];

        if ($ids) {
            // First, let's generate all the cache keys for the requested translations by their primary key
            foreach ($ids as $id)
                $cache_keys[] = $this->generateTranslationCacheKey($id, $langId);

            // Submit a Memcached Multi-get request for all cache keys and extract all un-cached entity PKs so
            // we can fetch the data for these from DB instead.
            /** @var array $cachedResults */
            if ($cachedResults = $request->cache->multi_get($cache_keys, $cacheTime)) {
                $cachedResults = array_index($cachedResults, $this->getPkField());
                foreach ($ids as $id) {
                    if (!array_key_exists($id, $cachedResults))
                        $uncachedPks[] = $id;
                }
            } else {
                $uncachedPks = $ids;
            }

            if ($uncachedPks) {

                // Ensure we're not getting any duplicates here.
                $uncachedPks = array_unique($uncachedPks);

                $fields = [
                    $this->getPkField(),
                    DBField::TEXT
                ];

                // Create Query Builder Object and apply filters, get results, then set cache for un-cached keys.
                $uncachedResults = $this->query($request->db)
                    ->fields($fields)
                    ->filter($this->filters->byPk($uncachedPks))
                    ->filter($this->filters->byLanguageId($langId))
                    ->get_list();

                if ($cacheTime) {
                    foreach ($uncachedResults as $uncachedResult) {
                        $cacheKey = $this->generateTranslationCacheKey($uncachedResult[$this->getPkField()], $langId);
                        $request->cache->set($cacheKey, $uncachedResult, $cacheTime, true);
                    }
                }

                $uncachedResults = array_index($uncachedResults, $this->getPkField());
            }

            // Combine cached and un-cached results to single array and preserve order of original ID list
            foreach ($ids as $id) {
                if (array_key_exists($id, $cachedResults))
                    $results[$id] = $cachedResults[$id];
                elseif (array_key_exists($id, $uncachedResults))
                    $results[$id] = $uncachedResults[$id];
            }
        }

        $translations = [];

        foreach ($results as $result)
            $translations[$result[$this->getPkField()]] = $result[DBField::TEXT];

        return $translations;
    }

    /**
     * @param CacheBackend $request
     * @param $id
     * @param $langId
     * @param $text
     */
    public static function updateTranslationText(CacheBackend $cache, $id, $langId, $text, $updaterId = 1)
    {
        $updatedTranslation = [
            DBField::TEXT => $text,
            DBField::UPDATE_TIME => date(SQL_DATETIME, TIME_NOW),
            DBField::UPDATER_ID => $updaterId
        ];
        self::objects()
            ->filter(Q::Eq(DBField::ID, $id))
            ->filter(Q::Eq(DBField::LANGUAGE_ID, $langId))
            ->update($updatedTranslation);

        $cache->delete(self::generateTranslationCacheKey($id, $langId), true);
    }
}

