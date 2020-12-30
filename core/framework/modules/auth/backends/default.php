<?php

/**
 * Typical user auth
 *
 * @package auth
 */

define('USER_DB', SQLN_SITE);
define('GROUP_TABLE', 'usergroups');
define('GROUP_PK', 'id');


class DefaultAuth extends Auth
{
    protected $ident_info;
    protected $config;
    public $profile;

    /**
     * DefaultAuth constructor.
     * @param $request
     */
    function __construct($request)
    {
        $this->config = $request->config;
    }

    /**
     * @param Request $request
     * @param array $info
     * @param int $userId
     * @return array
     */
    protected function checkValidLoginLink(Request $request, $info = [], $userId = UsersManager::ANONYMOUS_USER_ID)
    {
        $usersManager = $request->managers->users();
        $activityManager = $request->managers->activity();

        if (!$request->is_post() && $request->get->hasParams(GetRequest::PARAM_IDENT, GetRequest::PARAM_EXP, GetRequest::PARAM_MAGIC)) {

            $emailAddress = base64_decode($request->get->readParam(GetRequest::PARAM_IDENT));
            $exp = $request->get->readParam(GetRequest::PARAM_EXP);
            $magicLink = $request->get->readParam(GetRequest::PARAM_MAGIC);
            $usePathRestricted = $request->get->readParam(GetRequest::PARAM_PATH, 0);

            if ($emailAddress && Validators::int($exp) && $magicLink) {

                if ($exp > strtotime($request->getCurrentSqlTime())) {
                    $profileUser = $usersManager->getUserByEmailAddress($request, $emailAddress);

                    if ($profileUser) {
                        $path = $usePathRestricted ? $request->buildUrl($request->path) : null;

                        $generatedMagicLink = $usersManager->generateMagicLoginUrlParamsForUser($request, $profileUser, $exp, $path)[GetRequest::PARAM_MAGIC];

                        if ($generatedMagicLink == $magicLink) {

                            if (!$profileUser->is_verified()) {
                                $usersManager->verifyUser($request, $profileUser);
                                AuthMiddleware::$trackAuthVerified = true;
                                AuthMiddleware::$profileUser = $profileUser;
                            }

                            $expireTime = $userId == $profileUser->getPk() ? TWO_MONTH : FIVE_MINUTES;

                            $this->makeCookies($request, $profileUser->getPk(), true, $expireTime);

                            $info = [
                                self::INFO_IDENT => true,
                                self::INFO_USERID => $profileUser->getPk()
                            ];
                        }
                    }
                }
            }
        }
        return $info;
    }

    /**
     * @param Request $request
     * @return array
     */
    protected function check_ident(Request $request)
    {
        $info = [
            self::INFO_IDENT => false,
            self::INFO_USERID => UsersManager::ANONYMOUS_USER_ID
        ];

        // Check user cookies
        if ($userid = $this->check_cookies($request)) {

            $info = [
                self::INFO_IDENT => true,
                self::INFO_USERID => $userid
            ];

            $info = $this->checkValidLoginLink($request, $info, $userid);

        } else {
            $info = $this->checkValidLoginLink($request, $info);
        }

        return $info;
    }

    /**
     * @param Request $request
     * @param array $params
     * @return array
     */
    protected function fetch_auth_info(Request $request, $params = [])
    {
        // data
        $date = array_get($params, self::INFO_DATE, $request->readCookie(self::COOKIE_DATE, TIME_NOW));
        $userid = array_get($params, self::INFO_USERID, $request->readCookie(self::COOKIE_USERID, ''));

        $info = $this->fetch_ident_infos($userid);

        return [
            self::INFO_USERID => (int)$userid,
            self::INFO_DATE => (int)$date,
            self::INFO_AUTH => hash('sha256', (int)$userid.$info['salt'].(int)$date)
        ];
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function check_cookies(Request $request)
    {
        if (!$request->readCookie(self::COOKIE_USERID, ''))
            return false;

        try {
            $info = $this->fetch_auth_info($request);
            $auth = $request->readCookie(self::COOKIE_AUTH);
            if ($info[self::INFO_DATE] - TIME_NOW > ONE_MONTH*2)
                return false;
            return $auth == $info[self::INFO_AUTH] ? $info[self::INFO_USERID] : false;
        } catch (ObjectNotFound $e) {
            return false;
        }
    }

    /**
     * @param Request $request
     * @param $userid
     * @param bool|true $remember_me
     */
    public function makeCookies(Request $request, $userid, $remember_me = true, $expireTime = TWO_MONTH)
    {
        // reset profile cache to be sure to fetch the right salt
        unset($this->profile);
        $info = $this->fetch_auth_info($request, [
            self::INFO_USERID => $userid,
            self::INFO_DATE => TIME_NOW
        ]);

        $expire = $remember_me ? TIME_NOW + $expireTime : 0;
        $request->setCookie(self::COOKIE_USERID, $info[self::INFO_USERID], $expire, COOKIE_PATH, null, $request->settings()->getCookieIsSecure(), /*httponly*/true);
        $request->setCookie(self::COOKIE_DATE, $info[self::INFO_DATE], $expire, COOKIE_PATH, null, $request->settings()->getCookieIsSecure(), /*httponly*/true);
        $request->setCookie(self::COOKIE_AUTH, $info[self::INFO_AUTH], $expire, COOKIE_PATH, null, $request->settings()->getCookieIsSecure(), /*httponly*/true);
    }

    /**
     * @param $userid
     * @param $revision
     * @return string
     */
    protected function make_avatar_url($userid, $revision)
    {
        return '';
    }

    /**
     * @param $userid
     * @return array
     * @throws ObjectNotfound
     */
    protected function fetch_ident_infos($userid)
    {
        if (!isset($this->profile))
            $this->profile = UsersManager::objects()
                ->filter(Q::Eq(DBField::USER_ID, $userid))
                ->get(DBField::TIMEZONE_OFFSET, DBField::USERNAME, DBField::USERGROUP_ID, DBField::SALT);

        return [
            'timezoneoffset' => $this->profile[DBField::TIMEZONE_OFFSET],
            'usergroupid'    => $this->profile[DBField::USERGROUP_ID],
            'username'       => $this->profile[DBField::USERNAME],
            'salt'           => $this->profile[DBField::SALT],
            'dstauto'        => 0,
            'dstonoff'       => 0,
            'membergroupids' => '',
            'avatarrevision' => '1',
        ];
    }

    /**
     * @param $password
     * @param null $salt
     * @return bool|string
     */
    public function compute_password_hash($password, $salt = null)
    {
        if ($salt)
            $password_string = $salt.$password.$this->config[ESCConfiguration::SECRET];
        else
            $password_string = $password.$this->config[ESCConfiguration::SECRET];

        return password_hash($password_string, PASSWORD_BCRYPT);
    }

    /**
     * @param $password
     * @return string
     */
    public function compute_password_js_hash($password)
    {
        $js_salt = $this->config[ESCConfiguration::JS_SALT];
        return hash('sha256', $password.$js_salt);
    }

}