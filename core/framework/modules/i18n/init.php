<?php
/**
 * i18n
 *
 * @package i18n
 */

Modules::uses(Modules::HTTP);
Modules::uses(Modules::CACHE);
Modules::uses(Modules::DB);
Modules::uses(Modules::MANAGERS);

require "managers.php";
require "i18n-dictionary.php";
require "middleware.php";

Http::register_middleware(new i18nMiddleware());

class i18n implements ArrayAccess
{
    /**
     * Translations cache
     */
    private $translations;

    /**
     * Cache used to get translation Ids
     *
     * @var CacheBackend
     */
    private $cache;

    /**
     * Flag to know if the db has been accessed
     */
    private $accessed_db = false;

    /**
     * Lang we are using
     */
    private $langId;

    /**
     * Default lang
     */
    private $default_lang;

    /**
     * Display links to edit translations instead of plain text
     */
    private $edit_mode = false;

    /**
     * List of translations to show in the footer when edit mode is activated
     */
    private $edit_translations = [];

    private $authUserId = 1;

    /**
     * i18n constructor.
     * @param CacheBackend $cache
     * @param string $langId
     * @param string $defaultLangId
     * @param array $translations
     */
    public function __construct(CacheBackend $cache, $langId = 'en', $defaultLangId = 'en', $translations = [], $authUserId = 1)
    {
        $this->langId = $langId;
        $this->translations = $translations;
        $this->default_lang = $defaultLangId;
        $this->cache = $cache;
        $this->authUserId = $authUserId;
    }

    /**
     * Create a translation entry in the database
     *
     * @param $id
     * @param $langId
     */
    protected function create($id, $langId, $default = null)
    {
        $sqli = DB::inst(SQLN_SITE);
        $escaped_id = $sqli->escape_string($id);
        $escaped_lang = $sqli->escape_string($langId);

        $escaped_text = 'NULL';

        if ($default)
            $escaped_text = '"'.$sqli->escape_string($default).'"';

        $sqli->query_write(
            "INSERT IGNORE INTO phrase(phrase_id,language_id,text)
            VALUES(\"{$escaped_id}\", \"{$escaped_lang}\", {$escaped_text});"
        );



    }

    /**
     * @param $id
     * @param $text
     */
    protected function updateDefaultLangText($id, $text = null)
    {
        $sqli = DB::inst(SQLN_SITE);
        $escapedId = $sqli->escape_string($id);
        $escapedLangId = $sqli->quote_value($this->default_lang);
        $escapedUpdaterId = $sqli->quote_value($this->authUserId);
        $escapedUpdateTime = $sqli->quote_value(date(SQL_DATETIME, TIME_NOW));

        if (!$text)
            $text = $id;

        $escapedText = $sqli->quote_value($text);

        $sqli->query_write(
            "UPDATE phrase p set `p`.`text` = {$escapedText}, `p`.`updater_id` = {$escapedUpdaterId}, `p`.`update_time` = {$escapedUpdateTime}
             WHERE `p`.`phrase_id` = '{$escapedId}' and `p`.`language_id` = {$escapedLangId};"
        );

        $cacheKey = i18nManager::generateTranslationCacheKey($id, $this->default_lang);

        $this->cache->delete($cacheKey, true);

        $this->accessed_db = true;

        return $text;
    }

    /**
     * Fetch translation from database
     *
     * @param $id
     * @param $langId
     * @return mixed|string
     */
    protected function fetch($id, $langId, $default = null)
    {
        $cacheKey = i18nManager::generateTranslationCacheKey($id, $langId);

        try {
            $result = $this->cache[$cacheKey];
            $resultText = $result[DBField::TEXT];

        } catch (CacheEntryNotFound $c) {
            $sqli = DB::inst(SQLN_SITE);
            $escaped_id = $sqli->escape_string($id);
            $escaped_lang_id = $sqli->escape_string($langId);
            $row = $sqli->query_first(
                "SELECT phrase_id, text
            FROM phrase
            WHERE phrase_id = \"$escaped_id\"
            AND language_id = \"$escaped_lang_id\"");
            $this->accessed_db = true;

            if ($row) {
                $c->set($row, i18nManager::CACHE_TIME_TRANSLATION);
                $resultText = $row[DBField::TEXT];

            } else {
                if ($langId == $this->default_lang && !$default)
                    $default = $id;

                $this->create($id, $langId, $default);
                $resultText = '';
            }
        }

        if ($this->shouldUpdateDefaultLangText($id, $langId, $resultText, $default))
            $resultText = $this->updateDefaultLangText($id, $default);

        return $resultText;

    }

    /**
     * @param $decimalValue
     * @return string
     */
    public function displayCurrency($decimalValue, $decimalCount = 2)
    {
        if ($this->get_lang() == LanguagesManager::LANGUAGE_JAPANESE) {
            setlocale(LC_MONETARY, 'jp_JP');
            return money_format("$%.{$decimalCount}n", $decimalValue);
        } else {
            setlocale(LC_MONETARY, 'en_US');
            return money_format("%n", $decimalValue);
        }
    }

    /**
     * @param $durationTime
     * @return string
     */
    public function displayDuration($durationTime)
    {
        if ($durationTime == "00:00:00")
            return '';
        if ($this->get_lang() == LanguagesManager::LANGUAGE_JAPANESE) {
            $minutes = format_duration_as_minutes($durationTime);
            $minutesStr = $this['minutes'];
            return "{$minutes} {$minutesStr}";
        } else {
            return format_nice_time_duration($durationTime, $this);
        }
    }

    /**
     * @param $key
     * @param array $variables
     * @param null $langId
     * @return mixed|null|string
     */
    public function lookup($key, $variables = [], $langId = null)
    {
        if ($default = I18nDictionary::lookup($key)) {

            return $this->get($key, $default, $variables, $langId);

        } else {
            return null;
        }
    }

    /**
     * Get the translation for an id
     *
     * @param string id
     * @param string translated string
     */
    public function get($id, $default = '', $variables = [], $langId = null)
    {
        if (!$langId)
            $langId = $this->langId;

        if (array_key_exists($id, $this->translations) && $langId == $this->langId) {
            $text = $this->translations[$id];

            if ($this->shouldUpdateDefaultLangText($id, $langId, $text, $default))
                $text = $this->updateDefaultLangText($id, $default);

            if (!$text) {
                if ($default)
                    $text = $default;
                else
                    $text = $id;
            }
        } else {

            if ($langId == $this->default_lang)
                $text = $this->fetch($id, $langId, $default);
            else
                $text = $this->fetch($id, $langId);

            // Fallback to default language
            if ($text == '') {
                if ($langId != $this->default_lang)
                    $text = $this->fetch($id, $this->default_lang, $default);
                if ($text == '') {
                    if ($default)
                        $text = $default;
                    else
                        $text = $id;
                }
            }
            if ($langId == $this->langId) {
                $this->translations[$id] = $text;
            }
        }

        if ($this->edit_mode) {

            $this->edit_translations[$id] = [
                'id' => $id,
                'text' => $text,
                'default' => $default ? $default : $id,
                'variables' => $variables
            ];
        }

        if ($variables) {
            foreach ($variables as $name => $value)
                $text = str_replace('{'.$name.'}', $value, $text);
        }

        return $text;
    }

    /**
     * @param $id
     * @param $text
     * @param null $langId
     */
    public function updateTranslation($id, $text, $langId = null)
    {
        if (!$langId)
            $langId = $this->langId;

        if ($langId == $this->langId) {
            $this->translations[$id] = $text;
        }

        i18nManager::updateTranslationText($this->cache, $id, $langId, $text, $this->authUserId);
    }

    /**
     * @param $id
     * @param $langId
     * @param $text
     * @param null $default
     * @return string
     */
    private function shouldUpdateDefaultLangText($id, $langId, $text, $default = null)
    {
        if ($langId == $this->default_lang) {
            if (!$default)
                $default = $id;

            if ($text != $default)
                return true;
        } else {
            return false;
        }
    }

    public function get_edit_translations()
    {
        return $this->edit_translations;
    }

    /**
     * Edit mode accessor
     *
     * @return boolean $edit_mode
     */
    public function get_edit_mode()
    {
        return $this->edit_mode;
    }

    /**
     * Edit mode setter
     */
    public function set_edit_mode($value = true)
    {
        $this->edit_mode = $value;
    }

    /**
     * Check if the db has been accessed
     *
     * @return boolean true if the db has been accessed, false otherwise
     */
    public function has_accessed_db()
    {
        return $this->accessed_db;
    }

    /**
     * Returns the translations cache
     *
     * @return array translations cache
     */
    public function get_translations_cache()
    {
        return $this->translations;
    }

    /**
     * @return array
     */
    public function getAllTranslationIds()
    {
        return array_keys($this->translations);
    }

    /**
     * Wrapper to use translations in smarty template
     */
    public static function translate_tag(i18n &$i18n, $id)
    {
        return $i18n->get("phrase.{$id}", $id);
    }

    /**
     * $lang accessor
     */
    public function get_lang()
    {
        return $this->langId;
    }

    public function offsetSet($offset, $value)
    {
        throw new Exception('not implemented');
    }

    public function offsetExists($offset)
    {
        throw new Exception('not implemented');
    }

    public function offsetUnset($offset)
    {
        throw new Exception('not implemented');
    }

    public function offsetGet($offset)
    {
        return $this->get("phrase.{$offset}", $offset);
    }

}
