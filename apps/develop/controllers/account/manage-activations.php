<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 7/19/19
 * Time: 3:55 PM
 */

class OrganizationActivationsController extends BaseContent
{
    protected $url_key = 3;
    /** @var OrganizationEntity $activeOrganization */
    protected $activeOrganization = [];
    protected $user;

    /** @var OrganizationsMetaManager $organizationsMetaManager */
    protected $organizationsMetaManager;
    /** @var ServicesAccessTokensManager $servicesAccessTokensManager */
    protected $servicesAccessTokensManager;
    /** @var ServicesAccessTokensInstancesManager $servicesAccessTokensInstancesManager */
    protected $servicesAccessTokensInstancesManager;
    /** @var HostsManager $hostsManager */
    protected $hostsManager;
    /** @var HostsInstancesManager $hostsInstancesManager */
    protected $hostsInstancesManager;
    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var OrganizationsActivityManager $organizationsActivityManager */
    protected $organizationsActivityManager;

    /** @var ActivationsManager */
    protected $activationsManager;
    /** @var ActivationsGroupsManager */
    protected $activationsGroupsManager;

    /** @var GamesManager */
    protected $gamesManager;
    /** @var GamesModsManager */
    protected $gamesModsManager;

    /** @var OrganizationEntity[]  */
    protected $organizations = [];

    /*
     * Time frames are the duration in which a graph can represent its' data in. If we don't provide a time frame
     * we will default to 3 months.
     */

    const TIMEFRAME_1_WEEK = '1w';
    const TIMEFRAME_1_MONTH = '1m';
    const TIMEFRAME_3_MONTHS = '3m';
    const TIMEFRAME_6_MONTHS = '6m';
    const TIMEFRAME_1_YEAR = '1y';
    const TIMEFRAME_ALL_TIME = 'a';

    protected $timeFrames = [
        self::TIMEFRAME_1_WEEK => '-1 week',
        self::TIMEFRAME_1_MONTH => '-1 month',
        self::TIMEFRAME_3_MONTHS => '-3 month',
        self::TIMEFRAME_6_MONTHS => '-6 month',
        self::TIMEFRAME_1_YEAR => '-1 year',
        self::TIMEFRAME_ALL_TIME => 'all'
    ];

    protected $pages = [

        // Index
        '' => 'handle_list_activations',

        'create' => 'handle_create_activation_group',
        'edit' => 'handle_edit_activation_group',
        'add-activation' => 'handle_add_activation',
        'delete-activation' => 'handle_delete_activation',
        'delete-activation-group' => 'handle_delete_activation_group',

        'analytics' => 'handle_analytics',
        'analytics-stats.json' => 'handle_analytics_stats'
    ];

    protected $activationTypeIds = [
        ActivationsTypesManager::ID_CLOUD,
        ActivationsTypesManager::ID_LOCATION_BASED
    ];

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
     * @return HtmlResponse|HttpResponseRedirect
     */
    public function render(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)
    {
        if (!$request->user->is_authenticated()) {
            return $this->redirectToLogin($request);
        } else {
            $user = $request->user->getEntity();
        }

        if (!$this->activeOrganization)
            return $this->render_404($request);

        $this->url_key = $url_key;

        $func = $this->resolve($request, $url_key, $pages, $render_default, $root);

        if ($func === null)
            throw new Http404();

        $this->hostsManager = $request->managers->hosts();
        $this->hostsInstancesManager = $request->managers->hosts();
        $this->imagesManager = $request->managers->images();
        $this->organizationsMetaManager = $request->managers->organizationsMeta();
        $this->servicesAccessTokensManager = $request->managers->servicesAccessTokens();
        $this->servicesAccessTokensInstancesManager = $request->managers->servicesAccessTokensInstances();

        $this->activityManager = $request->managers->activity();
        $this->organizationsActivityManager = $request->managers->organizationsActivities();
        $this->activationsManager = $request->managers->activations();
        $this->activationsGroupsManager = $request->managers->activationsGroups();

        $this->gamesManager = $request->managers->games();
        $this->gamesModsManager = $request->managers->gamesMods();

        return $this->$func($request, $user, $this->activeOrganization);
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse
     */
    protected function handle_list_activations(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_HOSTS, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $activationGroups = $this->activationsGroupsManager->getAllActivationGroupsByOrganizationId($request, $activeOrganization->getPk(), true);
        $usableServiceAccessTokenInstances = $this->servicesAccessTokensInstancesManager->getAvailableActivationServiceAccessTokenInstancesForOwner(
            $request,
            EntityType::ORGANIZATION,
            $activeOrganization->getPk()
        );

        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'dev-manage-activations',
            TemplateVars::PAGE_TITLE => "Manage Activations - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Manage Activations - {$request->settings()->getWebsiteName()}",
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ACTIVATION_GROUPS => $activationGroups,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::SERVICE_ACCESS_TOKEN_INSTANCES => $usableServiceAccessTokenInstances,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/activations.twig');
    }

    /**
     * @return array
     */
    protected function getTimeZones()
    {
        $timeZones = [
            ['America/New_York', 'US/Eastern'],
            ['America/Chicago', 'US/Central'],
            ['America/Los_Angeles', 'US/Pacific'],
            ['America/Phoenix', 'US/Arizona (No DST)']
        ];

        return $timeZones;
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @param ActivationGroupEntity $activationGroup
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_edit_activation_group(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activationGroupId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Activation ID Not Found');

        if (!$activationGroup = $this->activationsGroupsManager->getActivationGroupById($request, $activationGroupId, $activeOrganization->getPk()))
            return $this-$this->render_404($request, 'Activation Not Found');

        $hostGameId = $activationGroup->getHost()->getOfflineGameId();

        if ($hostGameId) {

            $offlineHostGame = $this->gamesManager->getGameById($request, $hostGameId);
            $activationGroup->getHost()->setGame($offlineHostGame);
        }

        //$request->managers->gamesBuilds()->getUserPlayableGameBuildsByGameIds($request, [32, 28], 454);

        $fields = [
            new CharField(DBField::DISPLAY_NAME, '', 64, true, 'Choose a title that easily identifies the context of this activation.'),
            new DateField(VField::START_DATE, '', true, '', null, 'date'),
            new TimeField(DBField::START_TIME, '', true, '', null, 'time'),
            new TimeField(DBField::END_TIME, '', true, '', null, 'time'),
            new SelectField(DBField::TIME_ZONE, '', $this->getTimeZones(), true)
        ];

        $defaultTz = new DateTimeZone($activationGroup->getTimeZone());

        $defaultStartDt = new DateTime($activationGroup->getStartTime());
        $defaultStartDt->setTimezone($defaultTz);

        $defaultEndDt = new DateTime($activationGroup->getEndTime());
        $defaultEndDt->setTimezone($defaultTz);

        $defaults = [
            DBField::DISPLAY_NAME => $activationGroup->getDisplayName(),
            DBField::START_TIME => date('H:i', strtotime($defaultStartDt->format(SQL_DATETIME))),
            DBField::END_TIME => date('H:i', strtotime($defaultEndDt->format(SQL_DATETIME))),
            VField::START_DATE => date('Y-m-d', strtotime($defaultStartDt->format(SQL_DATETIME))),
            DBField::TIME_ZONE => $activationGroup->getTimeZone(),
        ];

        foreach ($activationGroup->getActivations() as $activation) {
            $gameMods = $this->gamesModsManager->getGameModsByOrganizationAndLicenses($request, $activeOrganization->getPk(), $activation->getGameId());

            $fieldName = $activation->getDynamicFormField(DBField::GAME_MOD_ID);
            $fields[] = new SelectField($fieldName, '', $gameMods, false, '', 'forms/custom-fields/offline_game_mod_select_field.twig');
            $defaults[$fieldName] = $activation->getGameModId();
        }

        $form = new PostForm($fields, $request, $defaults);

        $formViewData = [
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ACTIVATION_GROUP => $activationGroup
        ];

        $form->assignViewData($formViewData)->setTemplateFile('account/activations/forms/form-edit-activation-group.twig');


        if ($isValid = $form->is_valid()) {

            $displayName = $form->getCleanedValue(DBField::DISPLAY_NAME);

            $startDate = $form->getCleanedValue(VField::START_DATE);
            $startTime = $form->getCleanedValue(DBField::START_TIME);
            $endTime = $form->getCleanedValue(DBField::END_TIME);
            $timeZone = $form->getCleanedValue(DBField::TIME_ZONE);

            $tz = new DateTimeZone($timeZone);
            $utcTz = new DateTimeZone("UTC");

            $startDt = new DateTime("{$startDate} {$startTime}", $tz);
            $startDt->setTimezone($utcTz);

            $endDate = $startDate;

            if ($endTime == '00:00') {
                $endDateDt = new DateTime("{$startDate} {$startTime}", $tz);
                $endDateDt->modify("+1 day");
                $endDate = $endDateDt->format("Y-m-d");
            }

            $endDt = new DateTime("{$endDate} {$endTime}", $tz);
            $endDt->setTimezone($utcTz);

            $activationTimeData = [
                DBField::DISPLAY_NAME => $displayName,
                DBField::START_TIME => $startDt->format(SQL_DATETIME),
                DBField::END_TIME => $endDt->format(SQL_DATETIME),
            ];


            foreach ($activationGroup->getActivations() as $activation) {
                $fieldName = $activation->getDynamicFormField(DBField::GAME_MOD_ID);
                $gameModId = $form->getCleanedValue($fieldName);
                $activation->updateField(DBField::GAME_MOD_ID, $gameModId);

                if ($activation->getActivationTypeId() == ActivationsTypesManager::ID_LOCATION_BASED) {
                    $activationData = [
                        DBField::HOST_ID => $activationGroup->getHostId(),
                        DBField::ACTIVATION_STATUS_ID => ActivationsStatusesManager::ID_LIVE
                    ];

                    $activation->assign($activationData);
                    $activation->assign($activationTimeData);
                }

                $activation->saveEntityToDb($request);

            }

            $activationTimeData[DBField::TIME_ZONE] = $timeZone;

            $activationGroup->assign($activationTimeData)->saveEntityToDb($request);

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'dev-edit-activation',
            TemplateVars::PAGE_TITLE => "Edit Activation - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Edit Activation - {$request->settings()->getWebsiteName()}",
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ACTIVATION_GROUP => $activationGroup,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::FORM => $form
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/activations/edit-activation-group.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_create_activation_group(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_HOSTS, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $usableServiceAccessTokenInstances = $this->servicesAccessTokensInstancesManager->getAvailableActivationServiceAccessTokenInstancesForOwner(
            $request,
            EntityType::ORGANIZATION,
            $activeOrganization->getPk()
        );

        if (!$usableServiceAccessTokenInstances)
            return $this->render_404($request, 'No Access Tokens Available');

        $hostsManager = $request->managers->hosts();

        $hosts = $hostsManager->getSelectableHostsByOrganizationId($request, $activeOrganization->getPk());

        $fields = [
            new CharField(DBField::DISPLAY_NAME, '', 64, true, 'Choose a title that easily identifies the context of this activation.'),
            new DateField(VField::START_DATE, '', true, '', null, 'date'),
            new TimeField(DBField::START_TIME, '', true, '', null, 'time'),
            new TimeField(DBField::END_TIME, '', true, '', null, 'time'),
            new SelectField(DBField::TIME_ZONE, '', $this->getTimeZones(), true),
            new SelectField(DBField::HOST_ID, 'Host URL', $hosts, true, null, "forms/custom-fields/host_url_select_field.twig")
        ];

        $defaultTz = new DateTimeZone('America/New_York');

        $defaultStartDt = new DateTime($request->getCurrentSqlTime());
        $defaultStartDt->setTimezone($defaultTz);

        $defaultEndDt = new DateTime($request->getCurrentSqlTime());
        $defaultEndDt->setTimezone($defaultTz);
        $defaultEndDt->modify("tomorrow");

        $defaults = [
//            DBField::START_TIME => date('H:i', strtotime($defaultStartDt->format(SQL_DATETIME))),
            DBField::START_TIME => "09:00",
            DBField::END_TIME => date('H:i', strtotime($defaultEndDt->format(SQL_DATETIME))),
            VField::START_DATE => date('Y-m-d', strtotime($defaultStartDt->format(SQL_DATETIME))),
            DBField::TIME_ZONE => 'America/New_York',
            DBField::HOST_ID => $hosts[0]->getPk()
        ];

        $form = new PostForm($fields, $request, $defaults);

        $formViewData = [
            TemplateVars::ORGANIZATION => $activeOrganization,
        ];

        $form->assignViewData($formViewData)->setTemplateFile('account/activations/forms/form-create-new-activation-group.twig');

        if ($isValid = $form->is_valid()) {

            $displayName = $form->getCleanedValue(DBField::DISPLAY_NAME);
            $hostId = $form->getCleanedValue(DBField::HOST_ID);

            $startDate = $form->getCleanedValue(VField::START_DATE);
            $startTime = $form->getCleanedValue(DBField::START_TIME);
            $endTime = $form->getCleanedValue(DBField::END_TIME);
            $timeZone = $form->getCleanedValue(DBField::TIME_ZONE);

            $tz = new DateTimeZone($timeZone);
            $utcTz = new DateTimeZone("UTC");

            $startDt = new DateTime("{$startDate} {$startTime}", $tz);
            $startDt->setTimezone($utcTz);

            $endDt = new DateTime("{$startDate} {$endTime}", $tz);
            $endDt->setTimezone($utcTz);

            $serviceAccessTokenInstance = array_shift($usableServiceAccessTokenInstances);

            /** @var HostEntity[] $hosts */
            $hosts = $this->hostsManager->index($hosts);
            $host = $hosts[$hostId];

            $activationGroup = $this->activationsGroupsManager->createNewActivationGroup(
                $request,
                $activeOrganization->getPk(),
                $hostId,
                $displayName,
                $startDt->format(SQL_DATETIME),
                $endDt->format(SQL_DATETIME),
                $host->is_prod() ? $serviceAccessTokenInstance->getPk() : null,
                $timeZone
            );

            $activity = $this->activityManager->trackActivity(
              $request,
              ActivityTypesManager::ACTIVITY_TYPE_USER_ADD_ACTIVATION_GROUP,
              $activeOrganization->getPk(),
              $activationGroup->getPk(),
              $user->getUiLanguageId(),
              $user
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $activeOrganization,
                $activeOrganization->getOrganizationUserByUserId($user->getPk())
            );

            $request->user->sendFlashMessage('GameDay Successfully Created');

            $next = $request->getDevelopUrl("/teams/{$activeOrganization->getSlug()}/activations/edit/{$activationGroup->getPk()}");

            return $form->handleRenderJsonSuccessResponse($next);


        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/activations/create-activation-group.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_add_activation(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!in_array($activationTypeId = $request->get->readParam('type'), $this->activationTypeIds))
            return $this->render_404($request, "Invalid Activation Type");

        if (!$activationGroupId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Activation Group Id Not Found');

        if (!$activationGroup = $this->activationsGroupsManager->getActivationGroupById($request, $activationGroupId, $activeOrganization->getPk()))
            return $this->render_404($request, 'Activation Group Not Found');

        if ($activationTypeId == ActivationsTypesManager::ID_CLOUD) {
            $games = $this->gamesManager->getAvailableGamesByTypeAndOrganizationId(
                $request,
                $activeOrganization->getPk(),
                GamesTypesManager::ID_CLOUD_GAME,
                GamesManager::UPDATE_CHANNEL_LIVE
            );
        } else {
            $games = $this->gamesManager->getAvailableGamesByTypeAndOrganizationId(
                $request,
                $activeOrganization->getPk(),
                GamesTypesManager::ID_MULTI_PLAYER,
                GamesManager::UPDATE_CHANNEL_LIVE
            );
        }

        if (!$games)
            return $this->render_404($request, 'No Games Available', true);

        $singleGame = false;

        $fields = [
            new HiddenField(DBField::ACTIVATION_TYPE_ID, '', 1, true),
            new HiddenField(DBField::ACTIVATION_GROUP_ID, '', 0, true)
        ];

        $defaults = [
            DBField::ACTIVATION_TYPE_ID => $activationTypeId,
            DBField::GAME_ID => $games[0]->getPk()
        ];

        $isSingleGame = count($games) == 1;

        $fields =  [new SelectField(DBField::GAME_ID, 'Game', $games, true, '', 'forms/custom-fields/offline_game_select_field.twig')];

        if (!$isSingleGame && count($games) > 1) {

        } else {

        }

        if ($singleGame) {
            // Hack to allow adding game directly.
        }

        $form = new PostForm($fields, $request, $defaults);

        if ($isValid = $form->is_valid()) {

            $gameId = $form->getCleanedValue(DBField::GAME_ID);

            $activation = $this->activationsManager->createNewActivation(
                $request,
                $activationTypeId,
                $activationGroup->getHostId(),
                $activationGroup->getDisplayName(),
                $activationGroupId,
                1,
                $activationGroup->getStartTime(),
                $activationGroup->getEndTime(),
                $gameId,
                null,
                ActivationsStatusesManager::ID_LIVE
            );

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_ADD_ACTIVATION,
                $activationGroupId,
                $activation->getPk(),
                $user->getUiLanguageId(),
                $user
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $activeOrganization,
                $activeOrganization->getOrganizationUserByUserId($user->getPk())
            );

            $request->user->sendFlashMessage('Activation Added Successfully');

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form,
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/activations/add-activation.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_delete_activation(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activationGroupId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Activation Group Id not found');

        if (!$activationGroup = $this->activationsGroupsManager->getActivationGroupById($request, $activationGroupId, $activeOrganization->getPk()))
            return $this->render_404($request, 'Activation Group Not Found');

        if (!$activationId = $request->getIdForEntity($this->url_key+2))
            return $this->render_404($request, 'Activation Id not found');

        if (!$activation = $activationGroup->getActivationById($activationId))
            return $this->render_404($request, 'Activation not found');

        $fields = [];
        $form = new PostForm($fields, $request, []);

        if ($form->is_valid()) {

            $this->activationsManager->deactivateEntity($request, $activation);

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/activations/delete-activation.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_delete_activation_group(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activationGroupId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Activation Group Id not found');

        if (!$activationGroup = $this->activationsGroupsManager->getActivationGroupById($request, $activationGroupId, $activeOrganization->getPk()))
            return $this->render_404($request, 'Activation Group Not Found');

        $fields = [];
        $form = new PostForm($fields, $request, []);

        if ($form->is_valid()) {

            foreach ($activationGroup->getActivations() as $activation) {
                $this->activationsManager->deactivateEntity($request, $activation);
            }

            $this->activationsGroupsManager->deactivateEntity($request, $activationGroup);

            return $form->handleRenderJsonSuccessResponse($request->getDevelopUrl("/teams/{$activeOrganization->getSlug()}/activations"));

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/activations/delete-activation-group.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_analytics(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $hosts = $this->hostsManager->getSelectableHostsByOrganizationId($request, $activeOrganization->getPk());

        $activeHost = $hosts ? $hosts[0] : [];

        $tf = $request->get->readParam('tf', self::TIMEFRAME_1_MONTH);

        if ($hostSlug = $request->get->readParam('slug')) {
            foreach ($hosts as $host) {
                if ($host->getSlug() == $hostSlug)
                    $activeHost = $host;
            }
        }

        /** @var MySQLBackend $conn */
        $conn = $request->db->get_connection(SQLN_BI);

        $hostInstanceString = $activeHost ? "AND hi.host_id = {$conn->quote_value($activeHost->getPk())}" : "";

        $dt = new DateTime();
        $dt->modify($this->getTimeFrame($tf));

        $sql = "
            SELECT
                IFNULL(ceil(count(distinct s.guest_id) / count(distinct gi.game_instance_id)), 0) as count_avg_players,
                count(distinct dr.date_range_id) as count_days,
                count(distinct s.guest_id) as count_unique_players,
                count(distinct girp.game_instance_round_player_id) as count_player_sessions,
                IFNULL(TIME(FROM_UNIXTIME(FLOOR(AVG(UNIX_TIMESTAMP(COALESCE(girp.end_time, NOW())) - UNIX_TIMESTAMP(girp.start_time))))), '00:00:00') as avg_time_played,
                count(distinct a.activation_group_id) as count_gamedays
            FROM date_range dr
                
            LEFT JOIN (`host_instance` hi, `host` h)
              on hi.start_time >= dr.start_time 
              and hi.start_time < dr.end_time
              AND h.owner_type_id = 7 
              AND h.owner_id = {$conn->quote_value($activeOrganization->getPk())}
              AND h.host_id = hi.host_id
                {$hostInstanceString}            
            LEFT JOIN game_instance gi
            	on hi.host_instance_id = gi.host_instance_id
            LEFT JOIN `activation` a
                on a.activation_id = gi.activation_id
            LEFT JOIN game_instance_round gir
              on gi.game_instance_id = gir.game_instance_id
            LEFT JOIN game_instance_round_player girp
              on girp.game_instance_round_id = gir.game_instance_round_id
            LEFT JOIN `session` s
              on s.session_id = girp.session_id
            WHERE
              dr.start_time >= DATE('{$dt->format(SQL_DATETIME)}')
              AND dr.start_time >= DATE('{$activeOrganization->getCreateTime()}')
              AND dr.end_time <= NOW()
              AND dr.date_range_type_id = {$conn->quote_value(1)};            
        ";

        $queryData = $this->hostsInstancesManager->query()->sql($sql);

        $fields = [
            new CharField(DBField::ID, 'Google Analytics ID', 20, false, null, "e.g. UA-YOURCODE-1")
        ];

        $gaIdMeta = $this->organizationsMetaManager->getOrganizationMetaByKey($request, $activeOrganization->getPk(), OrganizationsMetaManager::KEY_GOOGLE_ANALYTICS_ID);

        if (!$gaIdMeta)
            $gaIdMeta = $this->organizationsMetaManager->createUpdateOrganizationMeta(
                $request,
                $activeOrganization->getPk(),
                OrganizationsMetaManager::KEY_GOOGLE_ANALYTICS_ID,
                null
            );

        $defaults = [
            DBField::ID => $gaIdMeta ? $gaIdMeta->getValue() : ""
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->setTemplateFile("account/forms/form-edit-analytics.twig");

        if ($form->is_valid()) {

            $gaId = $form->getCleanedValue(DBField::ID);

            $gaIdMeta->updateField(DBField::VALUE, $gaId)->saveEntityToDb($request);

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());
        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $interval = $request->get->readParam('interval', 1);

        if (!in_array($interval, [1,2,3,4]))
            $interval = 1;


        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'dev-activation-analytics',
            TemplateVars::PAGE_TITLE => "Analytics - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Analytics - {$request->settings()->getWebsiteName()}",
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::HOSTS => $hosts,
            TemplateVars::ACTIVE_HOST => $activeHost,
            'tf' => $request->get->readParam('tf', self::TIMEFRAME_1_MONTH),
            'interval' => $interval,
            TemplateVars::SUMMARY => $queryData[0],
            TemplateVars::FORM => $form

        ];

        return $this->setUseCharts()->renderPageResponse($request, $viewData, 'account/activations/analytics.twig');
    }

    /**
     * @param $timeFrame
     * @return string
     */
    private function getTimeFrame($timeFrame)
    {
        if (array_key_exists($timeFrame, $this->timeFrames)) {

            $offset = $this->timeFrames[$timeFrame];

            if ($offset == 'all')
                $offset = "-10 year";

            return $offset;
        }
        else
            return $this->timeFrames[self::TIMEFRAME_1_MONTH];
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return JSONResponse
     */
    public function handle_analytics_stats(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $hosts = $this->hostsManager->getSelectableHostsByOrganizationId($request, $activeOrganization->getPk());

        /** @var MySQLBackend $conn */
        $conn = $request->db->get_connection(SQLN_BI);

        $activeHost = $hosts ? $hosts[0] : [];

        $hostSlug = $request->get->readParam('slug');
        $tf = $request->get->readParam('tf', self::TIMEFRAME_1_MONTH);
        $interval = $request->get->readParam('interval', 1);

        if (!in_array($interval, ["1","2","3","4"]))
            $interval = 1;

        if ($hostSlug) {
            foreach ($hosts as $host) {
                if ($host->getSlug() == $hostSlug)
                    $activeHost = $host;
            }
        }

        $hostInstanceString = $activeHost ? "AND hi.host_id = {$conn->quote_value($activeHost->getPk())}" : "";

        $dt = new DateTime();
        $dt->modify($this->getTimeFrame($tf));

        $sql = "
            SELECT
                date_format(dr.start_time, '%Y-%m-%d') as instance_date,
                -- count(distinct hi.host_instance_id) as host_instances,
                -- count(distinct gi.game_id) as unique_games,
                count(distinct gi.game_instance_id) as game_instances,
                count(distinct girp.game_instance_round_player_id) as total_views,
                -- count(distinct gir.game_instance_round_id) as game_rounds,
                (count(distinct scloud.session_id) + count(distinct slocal.session_id)) as unique_game_players,
                count(distinct scloud.session_id) as unique_cloud_game_players,
                count(distinct slocal.session_id) as unique_local_game_players
            FROM date_range dr
                
            LEFT JOIN (host_instance hi, `host` h)
              on (
                ( hi.start_time >= dr.start_time and hi.start_time <= dr.end_time )
                OR ( hi.end_time >= dr.start_time and hi.end_time <= dr.end_time AND hi.end_time is not null )
                OR ( hi.start_time <= dr.start_time and
                    (
                      hi.end_time > dr.end_time
                      or hi.end_time is null                
                    ) 
                )
              )
              AND h.host_id = hi.host_id
              AND h.owner_type_id = 7 
              AND h.owner_id = {$conn->quote_value($activeOrganization->getPk())}              
              {$hostInstanceString}
              
            LEFT JOIN game_instance gi
            	on hi.host_instance_id = gi.host_instance_id
            LEFT JOIN game_instance_round gir
              on gi.game_instance_id = gir.game_instance_id
            LEFT JOIN game_instance_round_player girp
              on 
                ( 
                  girp.game_instance_round_id = gir.game_instance_round_id
                  AND (
                    ( girp.start_time >= dr.start_time and girp.start_time <= dr.end_time )
                    OR ( girp.end_time >= dr.start_time and girp.end_time <= dr.end_time AND girp.end_time is not null )
                    OR ( girp.start_time <= dr.start_time and
                    (
                      girp.end_time > dr.end_time
                      or girp.end_time is null                
                    ) 
                  )                  
                )
              )            
            LEFT JOIN `session` scloud
              on scloud.session_id = girp.session_id
              AND hi.host_instance_type_id = 2
            LEFT JOIN `session` slocal
              on slocal.session_id = girp.session_id
              AND hi.host_instance_type_id = 1

            WHERE
              dr.start_time >= DATE('{$dt->format(SQL_DATETIME)}')
              AND dr.start_time >= DATE('{$activeOrganization->getCreateTime()}')
              AND dr.start_time <= DATE(CURRENT_TIMESTAMP)            
              AND dr.date_range_type_id = {$conn->quote_value($interval)}
            GROUP BY 1
            ORDER BY 1 ASC;
        ";

        $conn->query_read("SET time_zone = 'America/New_York';");

        $hostMetrics = $this->hostsInstancesManager->query($request->db)->set_connection($conn)->sql($sql);

        return $this->renderJsonResponse($hostMetrics);
    }
}