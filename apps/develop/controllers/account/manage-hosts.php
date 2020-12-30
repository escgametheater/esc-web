<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 9/14/19
 * Time: 10:00 PM
 */


class OrganizationHostsController extends BaseContent {

    /** @var OrganizationEntity[] $organizations */
    protected $organizations = [];

    protected $pages = [
        '' => 'edit_host',
    ];

    /** @var OrganizationEntity $activeOrganization */
    protected $activeOrganization;
    /** @var UserEntity $user */
    protected $user;

    /** @var HostEntity */
    protected $host;
    /** @var HostEntity[] $hosts */
    protected $hosts;
    /** @var HostEntity[] $hostsBySlug */
    protected $hostsBySlug;


    /**
     * OrganizationActivationsController constructor.
     * @param null $template_factory
     * @param OrganizationEntity $organization
     * @param UserEntity $user
     * @param ActivationGroupEntity $activationGroup
     * @param array $organizations
     */
    public function __construct($template_factory = null, OrganizationEntity $organization, UserEntity $user, $organizations = [])
    {
        parent::__construct($template_factory);

        $this->activeOrganization = $organization;
        $this->user = $user;
        $this->organizations = $organizations;
    }

    /**
     * @param Request $request
     * @param null $url_key
     * @param null $pages
     * @param null $render_default
     * @param null $root
     * @return mixed
     */
    public function render(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)
    {
        $this->url_key = $url_key++;

        if (!$request->user->is_authenticated())
            return $this->redirectToLogin($request);

        if (!$request->user->permissions->has(RightsManager::RIGHT_ORGANIZATIONS, Rights::USE))
            return $this->redirect($request->getWwwUrl());

        $hostsManager = $request->managers->hosts();
        $this->hosts = $hostsManager->getHostsByOrganizationId($request, $this->activeOrganization->getPk(), 1, 100);
        $this->hostsBySlug = $hostsManager->index($this->hosts, DBField::SLUG);

        if ($hostSlug = $request->getSlugForEntity($this->url_key)) {

            /** @var HostEntity[] $hostsBySlug */

            if ($host = $this->hostsBySlug[$hostSlug] ?? []) {
                $this->host = $host;
            } else {
                return $this->render_404($request, 'Host Not Found');
            }
        } else {
            return $this->index($request, $this->user, $this->activeOrganization);
        }

        $func = $this->resolve($request, $url_key, $pages, $render_default, $root);


        if ($func === null)
            throw new Http404();

        return $this->$func($request, $this->user, $this->activeOrganization, $this->host);
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function index(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_HOSTS, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $hostsManager = $request->managers->hosts();
        $hosts = $hostsManager->getHostsByOrganizationId($request, $activeOrganization->getPk(), 1, 100);

        $gamesManager = $request->managers->games();
        $gamesModsManager = $request->managers->gamesMods();

        $gameIds = unique_array_extract(DBField::OFFLINE_GAME_ID, $hosts);
        $games = $gamesManager->getGamesByIds($request, $gameIds);
        /** @var GameEntity[] $games */
        $games = $gamesManager->index($games);

        $fields = [];
        $defaults = [];

        foreach ($hosts as $host) {
            $fields[] = new CharField($host->getDynamicFormField(DBField::DISPLAY_NAME), "", 0, true, $host->getUrl());
            $fields[] = new URLField($host->getDynamicFormField(DBField::ALTERNATE_URL), "", 127, false, "Ex: https://example.com/myurl", ['http', 'https']);
            $defaults[$host->getDynamicFormField(DBField::DISPLAY_NAME)] = $host->getDisplayName();
            $defaults[$host->getDynamicFormField(DBField::ALTERNATE_URL)] = $host->getAlternateUrl();
            if ($host->getOfflineGameId()) {
                $gameMods = $gamesModsManager->getGameModsByOrganizationAndLicenses($request, $activeOrganization->getPk(), $host->getOfflineGameId());
                $fieldName = $host->getDynamicFormField(DBField::GAME_MOD_ID);
                $fields[] = new SelectField($fieldName, "", $gameMods, false, "A mod is an active game customization pack.", "forms/custom-fields/offline_game_mod_select_field.twig");
                $defaults[$fieldName] = $host->getOfflineGameModId();
                $host->setGame($games[$host->getOfflineGameId()]);
            }

        }

        $form = new PostForm($fields, $request, $defaults);

        $formViewData = [
            TemplateVars::HOSTS => $hosts
        ];

        $form->assignViewData($formViewData)->setTemplateFile('account/forms/form-edit-hosts.twig');

        if ($isValid = $form->is_valid()) {

            foreach ($hosts as $host) {

                $host->updateField(DBField::DISPLAY_NAME, $form->getCleanedValue($host->getDynamicFormField(DBField::DISPLAY_NAME)));

                $host->updateField(DBField::ALTERNATE_URL, $form->getCleanedValue($host->getDynamicFormField(DBField::ALTERNATE_URL)));

                $fieldName = $host->getDynamicFormField(DBField::GAME_MOD_ID);
                if ($form->has_field($fieldName)) {
                    $gameModId = $form->getCleanedValue($fieldName);
                    $host->updateField(DBField::OFFLINE_GAME_MOD_ID, $gameModId);
                }

                $host->saveEntityToDb($request);
            }

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }


        $viewData = [
            TemplateVars::PAGE_TITLE => "Hosts - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Hosts - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-hosts',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::HOSTS => $hosts,
            TemplateVars::FORM => $form
        ];

        return $this->renderPageResponse($request, $viewData, 'account/hosts.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @param HostEntity $host
     * @return HtmlResponse|JSONResponse
     * @throws BaseManagerEntityException
     */
    protected function edit_host(Request $request, UserEntity $user, OrganizationEntity $activeOrganization, HostEntity $host)
    {
        $hostsManager = $request->managers->hosts();
        $gamesManager = $request->managers->games();
        $activationGroupsManager = $request->managers->activationsGroups();
        $hostInstancesManager = $request->managers->hostsInstances();
        $gamesModsManager = $request->managers->gamesMods();

        $cloudGames = $gamesManager->getAvailableGamesByTypeAndOrganizationId(
            $request,
            $activeOrganization->getPk(),
            GamesTypesManager::ID_CLOUD_GAME,
            GamesManager::UPDATE_CHANNEL_LIVE
        );

        $gamesManager->postProcessGames($request, $cloudGames);

        /** @var GameEntity[] $cloudGames */
        $cloudGames = $gamesManager->index($cloudGames);

        if ($request->get->readParam('modal') == 'change-game') {
            return $this->handle_modal_change_host_game($request, $user, $activeOrganization, $host, $cloudGames);
        }

        $fields = [];
        $defaults = [];

        $fields[] = new CharField(DBField::DISPLAY_NAME, "Display Name", 0, true, "The display name is used in various dropdown selectors and in reports.");
        $fields[] = new URLField(DBField::ALTERNATE_URL, "Redirect Url", 127, false, "(Optional) Enter a url that you own which redirects to the main host url. Ex: https://example.com/myurl", ['http', 'https']);
        $defaults[DBField::DISPLAY_NAME] = $host->getDisplayName();
        $defaults[DBField::ALTERNATE_URL] = $host->getAlternateUrl();
        if ($host->getOfflineGameId()) {
            $gameMods = $gamesModsManager->getGameModsByOrganizationAndLicenses($request, $activeOrganization->getPk(), $host->getOfflineGameId());
            $fields[] = new SelectField(DBField::GAME_MOD_ID, "", $gameMods, false, "A mod is an active game customization pack.", "forms/custom-fields/offline_game_mod_select_field.twig");
            $defaults[DBField::GAME_MOD_ID] = $host->getOfflineGameModId();
            $host->setGame($cloudGames[$host->getOfflineGameId()]);
        }

        $formViewData = [
            TemplateVars::HOST => $host,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
        ];

        $form = new PostForm($fields, $request, $defaults);

        if ($form->is_valid()) {
            $host->updateField(DBField::DISPLAY_NAME, $form->getCleanedValue(DBField::DISPLAY_NAME));

            $host->updateField(DBField::ALTERNATE_URL, $form->getCleanedValue(DBField::ALTERNATE_URL));

            if ($form->has_field(DBField::GAME_MOD_ID)) {
                $gameModId = $form->getCleanedValue(DBField::GAME_MOD_ID);
                $host->updateField(DBField::OFFLINE_GAME_MOD_ID, $gameModId);
            }

            $host->saveEntityToDb($request);

            $request->cache->delete($hostsManager->generateHostSlugCacheKey($host->getSlug()), true);

            $request->user->sendFlashMessage('Saved Settings');

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());
        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $form->assignViewData($formViewData)->setTemplateFile('account/forms/form-edit-host.twig');

        $hostInstances = $hostInstancesManager->getActiveLocalHostInstancesByHostId($request, $host->getPk(), true);
        $activationGroups = $activationGroupsManager->getAllActivationGroupsByHostId($request, $host->getPk(), true, true);

        foreach ($hostInstances as $hostInstance) {
            $activeActivationGroup = [];
            foreach ($activationGroups as $activationGroup) {

                $activationGroupDt = new DateTime($activationGroup->getStartTime());
                $hostInstanceDt = new DateTime($hostInstance->getStartTime());
                $hostInstancePingDt = new DateTime($hostInstance->getLastPingTime());

                $activationGroupDate = $activationGroupDt->format(SQL_DATE);

                if ($activationGroupDate == $hostInstanceDt->format(SQL_DATE) || $activationGroupDate == $hostInstancePingDt->format(SQL_DATE)) {
                    $activeActivationGroup = $activationGroup;

                    break;
                }
            }

            $hostInstance->updateField(VField::ACTIVATION_GROUP, $activeActivationGroup);
        }


        $this->assignPageJsViewData(
            [
                TemplateVars::PUB_NUB_CONFIG => [
                    TemplateVars::PUBLISH_KEY => $request->config['pubnub']['publish_key'],
                    TemplateVars::SUBSCRIBE_KEY => $request->config['pubnub']['subscribe_key'],
                    TemplateVars::SSL => true
                ],
                TemplateVars::HOST_INSTANCES => DBManagerEntity::extractJsonDataArrays($hostInstances),
                TemplateVars::HOST => DBManagerEntity::extractJsonDataArrays($host),
            ]
        );

        $viewData = [
            TemplateVars::PAGE_TITLE => "Hosts - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Hosts - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-edit-host',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::HOST => $host,
            TemplateVars::FORM => $form,
            TemplateVars::HOST_INSTANCES => $hostInstances,
            TemplateVars::ACTIVATION_GROUPS => $activationGroups,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/edit-host.twig');


    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @param HostEntity $host
     * @param GameEntity[] $cloudGames
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_modal_change_host_game(Request $request, UserEntity $user, OrganizationEntity $activeOrganization,
                                                     HostEntity $host, $cloudGames)
    {
        $fields = [
            new SelectField(DBField::GAME_ID, "Cloud Game", $cloudGames, false, "The cloud game is the default experience on your host url when not running the host or gamedays.", "forms/custom-fields/offline_game_select_field.twig")
        ];
        $defaults = [
            DBField::GAME_ID => $host->getOfflineGameId()
        ];

        $form = new PostForm($fields, $request, $defaults);

        if ($form->is_valid()) {
            $gameId = $form->getCleanedValue(DBField::GAME_ID);
            $oldOfflineGameId = $host->getOfflineGameId();

            $host->updateField(DBField::OFFLINE_GAME_ID, $gameId);

            if ($oldOfflineGameId != $gameId) {
                $host->updateField(DBField::OFFLINE_GAME_MOD_ID, null);
            }

            $host->saveEntityToDb($request);

            $request->user->sendFlashMessage('Saved Settings');

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());
        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Hosts - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Hosts - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-edit-host',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::HOST => $host,
            TemplateVars::FORM => $form,
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/edit-host-games.twig');
    }
}