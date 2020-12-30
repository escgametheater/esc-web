<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/2/18
 * Time: 6:35 PM
 */

require "../../core/domain/controllers/base.php";
require "v1/base.php";

class ApiV1Controller extends BaseApiV1Controller {

    protected $url_key = 2;

    const REQUIRES_POST = false;
    const REQUIRES_AUTH = false;

    protected $pages = [
        // Handled by Base Class
        '' => 'handle_index',

        // Custom for this controller
        'auth' => 'handle_auth',
        'sms' => 'handle_sms',
        'account' => 'handle_account',
        'hosts' => 'handle_hosts',
        'host-auto-update' => 'handle_host_auto_update',
        'locations' => 'handle_locations',
        'screens' => 'handle_screens',
        'networks' => 'handle_networks',
        'games' => 'handle_games',
        'games-data' => 'handle_games_data',
        'games-instances' => 'handle_games_instances',
        'games-builds' => 'handle_games_builds',
        'hosts-instances' => 'handle_hosts_instances',
        'host-app' => 'handle_host_app_api',
        'sdk' => 'handle_sdk_api',
        'coins' => 'handle_coins',
    ];

    /**
     * @param Request $request
     * @return HttpResponseRedirect
     */
    protected function handle_index(Request $request) : HttpResponse
    {
        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_auth(Request $request) : HttpResponse
    {
        $usersManager = $request->managers->users();

        require "v1/auth.php";

        $authController = new AuthApiV1Controller($this->templateFactory, $this->url_key+1, $usersManager);
        return $authController->render($request);
    }

    /**
     * @param Request $request
     * @return JSONResponse
     */
    protected function handle_sms(Request $request)
    {
        $smsManager = $request->managers->sms();

        require "v1/sms.php";

        $smsController = new SmsApiV1Controller($this->templateFactory, $this->url_key+1, $smsManager);
        return $smsController->render($request);
    }

    /**
     * @param Request $request
     * @return HttpResponse
     */
    protected function handle_account(Request $request) : HttpResponse
    {
        $usersManager = $request->managers->users();

        require "v1/account.php";

        $accountController = new AccountApiV1Controller($this->templateFactory, $this->url_key+1, $usersManager);
        return $accountController->render($request);
    }

   /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_hosts(Request $request) : HttpResponse
    {
        $hostsManager = $request->managers->hosts();

        require "v1/hosts.php";

        $hostsController = new HostsApiV1Controller($this->templateFactory, $this->url_key+1, $hostsManager);
        return $hostsController->render($request);
    }

    /**
     * @param Request $request
     * @return HttpResponse
     */
    protected function handle_host_auto_update(Request $request) : HttpResponse
    {
        $hostBuildsManager = $request->managers->hostBuilds();

        require "v1/host-auto-update.php";

        $hostAutoUpdateController = new HostAutoUpdateApiV1Controller($this->templateFactory, $this->url_key+1, $hostBuildsManager);
        return $hostAutoUpdateController->render($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_locations(Request $request) : HttpResponse
    {
        $locationsManager = $request->managers->locations();

        require "v1/locations.php";

        $locationsController = new LocationsApiV1Controller($this->templateFactory, $this->url_key+1, $locationsManager);
        return $locationsController->render($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_screens(Request $request) : HttpResponse
    {
        $screensManager = $request->managers->screens();

        require "v1/screens.php";

        $screensController = new ScreensApiV1Controller($this->templateFactory, $this->url_key+1, $screensManager);
        return $screensController->render($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_networks(Request $request) : HttpResponse
    {
        $networksManager = $request->managers->networks();

        require "v1/networks.php";

        $networksController = new NetworksApiV1Controller($this->templateFactory, $this->url_key+1, $networksManager);
        return $networksController->render($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_games(Request $request) : HttpResponse
    {
        $gamesManager = $request->managers->games();

        require "v1/games.php";

        $gamesController = new GamesApiV1Controller($this->templateFactory, $this->url_key+1, $gamesManager);
        return $gamesController->render($request);
    }


    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_games_instances(Request $request) : HttpResponse
    {
        $gamesInstancesManager = $request->managers->gamesInstances();

        require "v1/games-instances.php";

        $gamesInstancesController = new GamesInstancesApiV1Controller($this->templateFactory, $this->url_key+1, $gamesInstancesManager);
        return $gamesInstancesController->render($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_games_data(Request $request) : HttpResponse
    {
        $gamesDataManager = $request->managers->gamesData();

        require "v1/games-data.php";

        $gamesDataController = new GamesDataApiV1Controller($this->templateFactory, $this->url_key+1, $gamesDataManager);
        return $gamesDataController->render($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_games_builds(Request $request) : HttpResponse
    {
        $gamesBuildsManager = $request->managers->gamesBuilds();

        require "v1/games-builds.php";

        $gamesBuildsController = new GamesBuildsApiV1Controller($this->templateFactory, $this->url_key+1, $gamesBuildsManager);
        return $gamesBuildsController->render($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_hosts_instances(Request $request) : HttpResponse
    {
        $hostsInstancesManager = $request->managers->hostsInstances();

        require "v1/hosts-instances.php";

        $hostsInstancesController = new HostsInstancesApiV1Controller($this->templateFactory, $this->url_key+1, $hostsInstancesManager);

        return $hostsInstancesController->render($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    protected function handle_host_app_api(Request $request) : HttpResponse
    {
        $hostVersionManager = $request->managers->hostVersions();

        require "v1/host-app.php";

        $hostsAppApiController = new HostsAppApiV1Controller($this->templateFactory, $this->url_key+1, $hostVersionManager);

        return $hostsAppApiController->render($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     * @throws BaseEntityException
     */
    protected function handle_sdk_api(Request $request) : HttpResponse
    {
        $sdkVersionManager = $request->managers->sdkVersions();


        // api public routes special case
        require "v1/download-sdk.php";

        $downloadSdkApiController = new DownloadSdkApiV1Controller($this->templateFactory, $this->url_key+1, $sdkVersionManager);
        if($downloadSdkApiController->resolve($request, $this->url_key+1)) {
            if(!$request->settings()->is_prod() && !$request->user->is_authenticated()) {
                return $downloadSdkApiController->render_404($request);
            }
            return $downloadSdkApiController->render($request);
        }

        require "v1/sdk.php";

        $sdkApiController = new SdkApiV1Controller($this->templateFactory, $this->url_key+1, $sdkVersionManager);

        return $sdkApiController->render($request);
    }

    /**
     * @param Request $request
     * @return HttpResponse
     */
    protected function handle_coins(Request $request) : HttpResponse
    {
        require "v1/coins.php";

        $coinsApiV1Controller = new CoinsApiV1Controller($this->templateFactory, $this->url_key+1);

        return $coinsApiV1Controller->render($request);
    }

}