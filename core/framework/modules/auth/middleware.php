<?php
/**
 * Authentification Middleware
 *
 * @package auth
 */
class AuthMiddleware extends Middleware {

    public static $trackAuthVerified = false;
    /** @var UserEntity|null */
    public static $profileUser;

	/**
	 * Authentification information
	 */
	public function process_request(Request $request) {
		// Create auth object

        $backend = Auth::load_backend($request->config['auth']);

        // Assign auth object to request (used to log you in)
		$request->auth = new $backend($request);

		// Assign user to request
		$request->setUser($request->auth->make_user($request));

		// Redirect to registration if info is not set
		if ($this->userHasRedirect($request)) {
			return new HttpResponseRedirect($request->user->info['redirect']);
		}

		// Superadmins can override debug mode
		if ($request->config['debug']) {
			$new_debug = true;
		} elseif ($request->user->is_superadmin) {
			$new_debug = ($request->readCookie('debug') ?? '0') == '1';
		} else {
			$new_debug = false;
		}
		Debug::setDebug($request, $new_debug);
	}

	// Returns true if the user session info has a redirect.
	private function userHasRedirect(Request $request) {
		return array_key_exists('redirect', $request->user->info) &&
		$request->user->info['redirect'] != urldecode($request->server['REQUEST_URI']);
	}

    /**
     * @param Request $request
     * @param HttpResponse $response
     * @return string|void
     */
	public function process_response(Request $request, HttpResponse $response)
    {
        if (static::$trackAuthVerified && $profileUser = static::$profileUser) {

            $activityManager = $request->managers->activity();
            $activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_REGISTRATION_VERIFICATION_EMAIL,
                $profileUser->getPk(),
                $profileUser->getPk(),
                $profileUser->getUiLanguageId(),
                $profileUser
            );

        }
    }
}
