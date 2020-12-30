<?php
/**
 * Rights class
 *
 * @package auth
 */

abstract class Auth
{
    const COOKIE_USERID = 'userid';
    const COOKIE_DATE = 'date';
    const COOKIE_AUTH = 'auth';

    const INFO_USERID = 'userid';
    const INFO_IDENT = 'ident';
    const INFO_DATE = 'date';
    const INFO_AUTH = 'auth';


    public $redirect = '';

    protected $accessToken;

    public static function load_backend($name)
    {
        $backend_file = strtolower(str_replace("Auth", "", $name));
        require_once("backends/${backend_file}.php");
        return $name;
    }

    /**
     * @param Request $request
     * @param Session $session
     * @param Guest $guest
     * @param $info
     * @return User
     */
    protected function make_authenticated_user(Request $request, $info)
    {
        $user = new User($request, $info);

        return $user;
    }

    public function make_user(Request $request)
    {
        $info = $this->check_ident($request);
        $userid = array_get($info, self::INFO_USERID, UsersManager::ANONYMOUS_USER_ID);

        if ($userid)
            $user = $this->make_authenticated_user($request, $info);
        else
            $user = new AnonymousUser($request, $info);

        $user->applicationUserAccessToken = $this->accessToken;

        return $user;
    }

    /**
     * Generates a new user salt string
     *
     * @param    integer (Optional) the length of the salt string to generate
     *
     * @return   string
     */
    public static function generate_user_salt($length = SALT_LENGTH)
    {
        $salt = '';

        for ($i = 0; $i < $length; $i++)
            $salt .= chr(rand(33, 126));

        return $salt;
    }

    /**
     * @param $join_date
     * @param $secret
     * @return string
     */
    public function computeActivationChecksum($join_date, $secret)
    {
        return sha1($join_date . $secret);
    }

    /**
     * @param $password
     * @param $hash
     * @param null $salt
     * @return bool
     */
    public function checkPassword($password, $hash, $salt = null)
    {
        if ($salt)
            $password_string = $salt.$password.$this->config[ESCConfiguration::SECRET];
        else
            $password_string = $password.$this->config[ESCConfiguration::SECRET];

        return password_verify($password_string, $hash);
    }
    
    /*
     * Used by initialisation
     */

    /**
     * Fetch user data from the forum database
     *
     * @param string user id or username
     * @return array
     */
    abstract protected function fetch_ident_infos($s_user);

}
