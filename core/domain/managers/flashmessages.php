<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 8/28/16
 * Time: 4:19 PM
 */

Entities::uses("flashmessages");

class FlashMessagesManager extends Manager {

    protected $table = Table::FlashMessages;
    protected $entityClass = FlashMessageEntity::class;

    /** @var Session */
    protected $session;

    const TYPE_ALERT = 'alert';
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR = 'error';
    const TYPE_WARNING = 'warning';
    const TYPE_INFORMATION = 'information';
    const TYPE_CONFIRM = 'confirm';

    const OPTION_MODAL = 'modal';
    const OPTION_TIMEOUT = 'timeout';
    const OPTION_LAYOUT = 'layout';
    const OPTION_TEMPLATE = 'template';
    const OPTION_CLOSEWITH = 'closeWith';

    const LAYOUT_CENTER = 'center';
    const LAYOUT_BUTTONMODAL = 'buttonModal';
    const LAYOUT_BOTTOMRIGHT = 'bottomRight';
    const LAYOUT_FULL_MODAL = 'fullModal';

    const TEMPLATE_WIDE = '<div class="noty_message noty-wide"><span class="noty_text"></span><div class="noty_close"></div></div>';

    public static $fields = [
        DBField::USER_ID,
        DBField::CONTENT,
        DBField::TYPE
    ];

    protected static $typeMapping = [
        MSG_INFO => self::TYPE_INFORMATION,
        MSG_SUCCESS => self::TYPE_SUCCESS,
        MSG_FAILURE => self::TYPE_ERROR,
        MSG_CONFIRMATION => self::TYPE_CONFIRM,
        MSG_WARNING => self::TYPE_WARNING,
        MSG_ALERT => self::TYPE_ALERT
    ];

    /**
     * @param Session $session
     * @return $this
     */
    public function setSessionObject(Session $session)
    {
        $this->session = $session;
        return $this;
    }

    /**
     * @param $data
     * @return FlashMessageEntity
     */
    public static function createEntity($data) {
        return new FlashMessageEntity($data);
    }

    /**
     * @param $code
     * @return null
     */
    public static function getNotyTypeFromMessageCode($code)
    {
        return array_key_exists($code, self::$typeMapping) ? self::$typeMapping[$code] : null;
    }

    /**
     * @param $user_id
     * @param string $content
     * @param int $type
     * @param array $options
     * @throws Http404
     */
    public static function sendFlashMessage($user_id, $content = '', $type = MSG_INFO, $options = [])
    {
        Validators::id($user_id, true);
        Validators::uint($type, true);

        $contentData = [
            DBField::BODY => $content,
            DBField::OPTIONS => $options
        ];

        $message_data = [
            DBField::USER_ID => $user_id,
            DBField::TYPE => $type,
            DBField::CONTENT => json_encode($contentData)
        ];

        self::objects()->add($message_data);

        $cache_key = self::generateUserFlashMessagesCacheKey($user_id);

        Cache::delete($cache_key, true);
    }

    /**
     * @param $user_id
     * @return string
     */
    public static function generateUserFlashMessagesCacheKey($user_id)
    {
        return UsersManager::GNS_KEY_PREFIX.".${user_id}._messages";
    }

    /**
     * @param $user_id
     * @param bool|true $clear
     * @return array
     * @throws Http404
     */
    public static function getFlashMessagesForUserId($user_id, $clear = true)
    {
        Validators::id($user_id, true);

        $messages = [];

        $cacheResult = Cache::get($messages, self::generateUserFlashMessagesCacheKey($user_id), 60*15);

        if ($cacheResult->shouldset) {

            $userFilter = Q::Eq(DBField::USER_ID, $user_id);
            $messages = self::objects()->filter($userFilter)->get_list();

            if ($messages && $clear)
                self::objects()->filter($userFilter)->delete();

            $cacheResult->set([]);
        }

        foreach ($messages as $key => $message)
            $messages[$key] = FlashMessagesManager::createEntity($message);

        return $messages;
    }
}