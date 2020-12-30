<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/19/18
 * Time: 3:04 PM
 */



class ApiAuth extends Auth
{
    const HEADER_AUTHORIZATION = 'Authorization';

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
     * @return array
     */
    protected function check_ident(Request $request)
    {
        $applicationsUsersAccessTokensManager = $request->managers->applicationsUsersAccessTokens();

        $headers = $request->headers;

        $ident = false;
        $userId = UsersManager::ANONYMOUS_USER_ID;

        $token = $this->getTokenFromHeaders($headers);

        if ($token) {

            $userApplicationAccessToken = $applicationsUsersAccessTokensManager->getApplicationUserAccessTokenByToken(
                $request,
                ApplicationsManager::ID_ESC_API,
                $token
            );

            if ($userApplicationAccessToken) {
                $userId = $userApplicationAccessToken->getUserId();
                $ident = true;

                $this->accessToken = $userApplicationAccessToken;
            }
        }

        return [
            self::INFO_IDENT => $ident,
            self::INFO_USERID => $userId
        ];
    }

    /**
     * @param array $headers
     * @return null
     */
    protected function getTokenFromHeaders($headers = [])
    {
        $token = null;

        if (array_key_exists(self::HEADER_AUTHORIZATION, $headers))
            $header = self::HEADER_AUTHORIZATION;
        elseif (array_key_exists(strtolower(self::HEADER_AUTHORIZATION), $headers))
            $header = strtolower(self::HEADER_AUTHORIZATION);
        else
            $header = null;

        if ($header) {

            $headerData = explode(" ", $headers[$header]);

            if (isset($headerData[0]) && $headerData[0] == 'Bearer' && isset($headerData[1])) {
                $token = $headerData[1];
            }
        }

        return $token;
    }

    /**
     * @param Request $request
     * @param array $params
     * @return array
     */
    protected function fetch_auth_info($token)
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
     * @param $userid
     * @param bool|true $remember_me
     */
    public function makeCookies(Request $request, $userid, $remember_me = true)
    {
        // Not used in API Auth
    }

    /**
     * @param $userid
     * @return array
     * @throws ObjectNotfound
     */
    protected function fetch_ident_infos($userid)
    {
        if (!isset($this->profile)) {

            $this->profile = UsersManager::objects()
                ->filter(Q::Eq(DBField::USER_ID, $userid))
                ->get(DBField::TIMEZONE_OFFSET, DBField::USERNAME, DBField::USERGROUP_ID, DBField::SALT);
        }

        return [
            'timezoneoffset' => $this->profile[DBField::TIMEZONE_OFFSET],
            'usergroupid'    => $this->profile[DBField::USERGROUP_ID],
            'username'       => $this->profile[DBField::USERNAME],
            'salt'           => $this->profile[DBField::SALT],
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
     * @param $hash
     * @param null $salt
     * @return bool
     */
    public function check_password($password, $hash, $salt = null)
    {
        if ($salt)
            $password_string = $salt.$password.$this->config[ESCConfiguration::SECRET];
        else
            $password_string = $password.$this->config[ESCConfiguration::SECRET];

        return password_verify($password_string, $hash);
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