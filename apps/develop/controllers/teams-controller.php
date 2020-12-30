<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/15/18
 * Time: 10:21 PM
 */

require "../../core/domain/controllers/base.php";

class TeamsController extends BaseContent {

    protected $url_key = 2;

    /** @var OrganizationEntity[] $organizations */
    protected $organizations = [];

    protected $pages = [
        '' => 'index',

        'onboarding' => 'handle_onboarding',
        'upload-profile-image' => 'handle_upload_profile_image',

        // Activations
        'activations' => 'handle_activations',

        // Content
        'manage-games' => 'handle_manage_games',
        'create-game' => 'handle_create_game',
        'manage-mods' => 'handle_manage_mods',
        'create-mod' => 'handle_create_mod',

        // Finance
        'orders' => 'handle_orders',
        'billing' => 'handle_billing',
        'payouts' => 'handle_payouts',

        // Settings
        'profile' => 'handle_profile',
        'hosts' => 'handle_hosts',
        'members' => 'handle_members',
        'add-member' => 'add_member',
        'edit-member' => 'handle_edit_member',
        'delete-member' => 'handle_delete_member',
        'roles' => 'handle_roles',
        'role' => 'handle_role',
        'edit-permissions' => 'handle_edit_permissions',
        'edit-right' => 'handle_edit_right'
    ];

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
        $organizationsManager = $request->managers->organizations();

        if (!$request->user->is_authenticated())
            return $this->redirectToLogin($request);

        if (!$request->user->permissions->has(RightsManager::RIGHT_ORGANIZATIONS, Rights::USE))
            return $this->redirect($request->getWwwUrl());

        $user = $request->user->getEntity();

        $organizationSlug = $request->getSlugForEntity($this->url_key);

        $this->organizations = $organizationsManager->getOrganizationsByUserId($request, $user->getPk(), true, true);

        if ($this->organizations)
            $this->organizations = array_index($this->organizations, $organizationsManager->getPkField());

        $organization = null;

        if ($organizationSlug) {

            if ($request->user->permissions->has(RightsManager::RIGHT_ORGANIZATIONS, Rights::MODERATE))
                $organization = $organizationsManager->getOrganizationBySlug($request, $organizationSlug, true, true);
            else
                $organization = $organizationsManager->getOrganizationBySlugAndUserId($request, $organizationSlug, $user->getPk(), true, true);

            if (!$organization)
                return $this->render_404($request);
            else
                $this->url_key++;

        }

        $func = $this->resolve($request, $url_key, $pages, $render_default, $root);

        if ($func === null)
            throw new Http404();

        return $this->$func($request, $user, $organization);
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse
     */
    protected function index(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        force_slash($request);

        $organizationsActivitiesManager = $request->managers->organizationsActivities();
        $activationGroupsManager = $request->managers->activationsGroups();
        $servicesAccessTokensInstancesManager = $request->managers->servicesAccessTokensInstances();
        $gamesModsManager = $request->managers->gamesMods();
        $hostsInstancesManager = $request->managers->hostsInstances();

        $activationGroups = $activationGroupsManager->getActivationGroupsByOrganizationId($request, $activeOrganization->getPk(), 1, 3, true);

        $gameMods = $gamesModsManager->getRecentEditableGameModsByOrganizationIds($request, $activeOrganization->getPk());

        $usableServiceAccessTokenInstances = $servicesAccessTokensInstancesManager->getAvailableActivationServiceAccessTokenInstancesForOwner(
            $request,
            EntityType::ORGANIZATION,
            $activeOrganization->getPk()
        );

        /** @var MySQLBackend $conn */
        $conn = $request->db->get_connection(SQLN_BI);

        $sql = "
            SELECT
                count(distinct s.guest_id) as count_unique_players,
                IFNULL(TIME(FROM_UNIXTIME(FLOOR(AVG(UNIX_TIMESTAMP(COALESCE(girp.end_time, NOW())) - UNIX_TIMESTAMP(girp.start_time))))), '00:00:00') as avg_time_played,
                count(distinct a.activation_group_id) as count_gamedays
            FROM date_range dr
                
            LEFT JOIN (`host_instance` hi, `host` h)
              on hi.start_time >= dr.start_time 
              and hi.start_time < dr.end_time
              AND h.owner_type_id = 7 
              AND h.owner_id = {$conn->quote_value($activeOrganization->getPk())}
              AND h.host_id = hi.host_id
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
              dr.start_time >= DATE('{$activeOrganization->getCreateTime()}')
              AND dr.end_time <= NOW()
              AND dr.date_range_type_id = {$conn->quote_value(1)};            
        ";

        $queryData = $hostsInstancesManager->query()->sql($sql);

        $organizationActivities = [];

        if ($activeOrganization) {
//            $organizationActivities = $organizationsActivitiesManager->getOrganizationActivitiesByOrganizationId($request, $activeOrganization->getPk());
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Developer Account - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Developer Account - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-dashboard',
            TemplateVars::PAGE_CANONICAL => $request->path,
            TemplateVars::PROFILE_USER => $user,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATION_ACTIVITIES => $organizationActivities,
            TemplateVars::ACTIVATION_GROUPS => $activationGroups,
            TemplateVars::SERVICE_ACCESS_TOKEN_INSTANCES => $usableServiceAccessTokenInstances,
            TemplateVars::GAME_MODS => $gameMods,
            TemplateVars::SUMMARY => $queryData[0],
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/index.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse|HttpResponseRedirect
     */
    protected function handle_onboarding(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_GAMES_PROFILE, Rights::MODERATE))
            return $this->render_404($request, 'Access Denied');

        $organizationsMetaManager = $request->managers->organizationsMeta();

        if ($organizationsMetaManager->checkBoolMetaKey($activeOrganization->getPk(), OrganizationsMetaManager::KEY_ONBOARDED))
            return $this->redirect($activeOrganization->getUrl());

        require "account/onboarding.php";

        $onboardingController = new OrganizationOnboardingController($this->templateFactory, $activeOrganization, $user);

        return $onboardingController->render($request, $this->url_key+1);
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse|HttpResponseRedirect
     */
    protected function handle_activations(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        require "account/manage-activations.php";

        $activationsController = new OrganizationActivationsController($this->templateFactory, $activeOrganization, $user, $this->organizations);
        return $activationsController->render($request, $this->url_key+1);
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @return HttpResponseRedirect|JSONResponse|HtmlResponse
     */
    public function handle_create_game(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $gamesManager = $request->managers->games();
        $activityManager = $request->managers->activity();
        $organizationsActivityManager = $request->managers->organizationsActivities();
        $gamesCategoriesManager = $request->managers->gamesCategories();
        $gamesTypesManager = $request->managers->gamesTypes();

        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_GAMES_PROFILE, Rights::MODERATE))
            return $this->render_404($request, 'Access Denied');

        $gameCategories = $gamesCategoriesManager->getAllActiveGameCategories($request);
        $gameTypes = $gamesTypesManager->getAllActiveGameTypes($request);

        $fields = [
            new CharField(DBField::DISPLAY_NAME, 'Name', 100, true, 'This is the title of the game.'),
            new SelectField(DBField::GAME_CATEGORY_ID, 'Category/Genre', $gameCategories, true, 'Choose the genre that best fits your game.'),
            new SelectField(DBField::GAME_TYPE_ID, 'Type', $gameTypes, true, 'What type of project is this?')
        ];

        $defaults = [];

        $form = new PostForm($fields, $request, $defaults);

        $form->setTemplateFile('account/forms/form-new-game.twig');

        if ($form->is_valid()) {
            $displayName = $form->getCleanedValue(DBField::DISPLAY_NAME);
            $gameCategoryId = $form->getCleanedValue(DBField::GAME_CATEGORY_ID);
            $gameTypeId = $form->getCleanedValue(DBField::GAME_TYPE_ID);
            $canMod = 0;

            $ownerTypeId = EntityType::ORGANIZATION;
            $ownerId = $activeOrganization->getPk();

            $gameEngineId = $gameTypeId == GamesTypesManager::ID_CLOUD_GAME
                ? GamesEnginesManager::ID_ESC_REACT
                : GamesEnginesManager::ID_UNITY;

            $game = $gamesManager->createNewGame(
                $request,
                null,
                $displayName,
                $ownerTypeId,
                $ownerId,
                $gameCategoryId,
                $gameTypeId,
                $gameEngineId,
                $canMod,
                $activeOrganization->getSlug()
            );

            $activity = $activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_CREATE_GAME,
                $user->getPk(),
                $game->getPk(),
                $user->getUiLanguageId(),
                $user
            );

            $organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $activeOrganization,
                $activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            if ($request->is_ajax())
                return $form->handleRenderJsonSuccessResponse($game->getEditUrl());
            else
                return $this->redirect($game->getEditUrl());
        } elseif ($request->is_post()) {
            if ($request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'create-game',
            TemplateVars::FORM => $form,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/create-game.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @return HtmlResponse
     */
    public function handle_manage_games(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $slug = $request->getSlugForEntity($this->url_key+1);

        $gamesManager = $request->managers->games();

        if (!$slug)
            prevent_slash($request);

        if ($activeOrganization && !$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_GAMES_PROFILE, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        // If we have a game slug, let's try to render the manage game controller.
        if ($slug) {

            $isEscAdmin = $request->user->permissions->has(RightsManager::RIGHT_GAMES, Rights::ADMINISTER);

            $game = $gamesManager->getEditableGameBySlug($request, $slug, $isEscAdmin, true);

            if ($game) {
                require "account/manage-game.php";
                $manageGameController = new ManageGameController($this->templateFactory, $game, $this->organizations);

                return $manageGameController->render($request, $this->url_key+2);
            } else {
                return $this->render_404($request, 'Game Not Found');
            }
        }

        $games = $gamesManager->getGamesByOwnerId($request, $activeOrganization->getPk(), true, EntityType::ORGANIZATION);

        $organizationGameLicensesManager = $request->managers->organizationsGamesLicenses();

        $gameLicenses = $organizationGameLicensesManager->getOrganizationGameLicensesByOrganizationId($request, $activeOrganization->getPk(), true);

        $viewData = [
            TemplateVars::PAGE_TITLE => "Manage Games - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Manage Games - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-manage-games',
            TemplateVars::PAGE_CANONICAL => '/account/manage-games',
            TemplateVars::PROFILE_USER => $user,
            TemplateVars::GAMES => $games,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::LICENSES => $gameLicenses,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/manage-games.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_create_mod(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_MODS_PROFILE, Rights::MODERATE))
            return $this->render_404($request, 'Access Denied');

        $gameSlug = $request->getSlugForEntity($this->url_key+1);


        $shouldRedirectCustomize = $request->get->readParam('customizeNext') == 1;

        $gamesManager = $request->managers->games();
        $gamesModsManager = $request->managers->gamesMods();
        $activityManager = $request->managers->activity();
        $organizationsActivityManager = $request->managers->organizationsActivities();
        $organizationsGamesLicensesManager = $request->managers->organizationsGamesLicenses();

        $games = $gamesManager->getGamesByOwnerId($request, $activeOrganization->getPk(), false, EntityType::ORGANIZATION);

        $licencedGames = $organizationsGamesLicensesManager->getOrganizationGameLicensesByOrganizationId($request, $activeOrganization->getPk());

        if ($games)
            $games = array_index($games, $gamesManager->getPkField());

        foreach ($licencedGames as $licencedGame) {
            if (!array_key_exists($licencedGame->getGame()->getPk(), $games))
                $games[$licencedGame->getGame()->getPk()] = $licencedGame->getGame();
        }

        $gameId = null;

        if ($gameSlug) {
            foreach ($games as $game) {
                if ($game->getSlug() == $gameSlug)
                    $gameId = $game->getPk();
            }
        }

        $fields = [
            new CharField(DBField::DISPLAY_NAME, 'Mod Title', 96, true, 'This is the name that identifies the customizations in the mod.'),
        ];

        $defaults = [];

        if ($gameId) {
            $defaults[DBField::GAME_ID] = $gameId;

            $fields[] = new HiddenField(DBField::GAME_ID, 'Game', 0, true, 'Which game is this mod for?');
        } else {
            $fields[] = new SelectField(DBField::GAME_ID, 'Game', $games, true, 'Which game is this mod for?');
        }

        $form = new PostForm($fields, $request, $defaults);

        $form->setTemplateFile('account/forms/form-new-mod.twig');

        if ($form->is_valid()) {

            $gameId = $form->getCleanedValue(DBField::GAME_ID);
            $displayName = $form->getCleanedValue(DBField::DISPLAY_NAME);

            /** @var GameEntity $game */
            $game = $games[$gameId];

            $gameMod = $gamesModsManager->createNewGameMod(
                $request,
                $activeOrganization->getPk(),
                $activeOrganization->getSlug(),
                $game->getPk(),
                $game->getSlug(),
                $displayName
            );

            $gameMod->setGame($game);

            $activity = $activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_CREATE_GAME_MOD,
                $game->getPk(),
                $gameMod->getPk(),
                $user->getUiLanguageId(),
                $user
            );
            $organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $activeOrganization,
                $activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            $request->user->sendFlashMessage('Game Mod Created Successfully', MSG_SUCCESS);

            if ($game->can_customize()) {
                $next = $gameMod->getEditUrl("/customizer");
            } else {
                if ($shouldRedirectCustomize)
                    $next = $gameMod->getEditUrl("/customizer");
                else
                    $next = $gameMod->getEditUrl();
            }

            return $form->handleRenderJsonSuccessResponse($next);

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/create-mod.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse
     */
    public function handle_manage_mods(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $gamesManager = $request->managers->games();
        $gamesModsManager = $request->managers->gamesMods();

        $gameSlug = $request->getSlugForEntity($this->url_key+1);

        if (!$gameSlug)
            prevent_slash($request);

        $game = [];

        $isEscAdmin = $request->user->permissions->has(RightsManager::RIGHT_GAMES, Rights::ADMINISTER);

        // If we have a game slug, we may want to filter our mods, or render mod edit page.
        if ($gameSlug) {
            // If we have a mod ID we want to render the manage mod page.
            if ($gameModId = $request->getIdForEntity($this->url_key+2)) {

                if ($isEscAdmin) {
                    $gameMod = $gamesModsManager->getGameModById($request, $gameModId);

                    if ($gameMod && !array_key_exists($gameMod->getOrganizationId(), $this->organizations)) {
                        $this->organizations[$activeOrganization->getPk()] = $activeOrganization;
                    }

                } else {
                    $gameMod = $gamesModsManager->getGameModByGameSlugAndPk($request, $gameSlug, $gameModId);
                }

                if ($gameMod) {
                    require "account/manage-mod.php";

                    $manageGameController = new ManageGameModController($this->templateFactory, $gameMod, $this->organizations);

                    return $manageGameController->render($request, $this->url_key+3);
                } else {
                    return $this->render_404($request, 'Game Mod Not Found');
                }
            }
        }

        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_MODS_PROFILE, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        if ($gameSlug)
            $game = $gamesManager->getGameBySlug($request, $gameSlug);

        $gameMods = $gamesModsManager->getEditableGameModsByOrganizationIds($request, $activeOrganization->getPk(), $gameSlug);

        $viewData = [
            TemplateVars::PAGE_TITLE => "Manage Mods - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Manage Mods - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-manage-mods',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::GAME_MODS => $gameMods,
            TemplateVars::GAME => $game,
            TemplateVars::ORGANIZATIONS => $this->organizations
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/manage-mods.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse
     */
    public function handle_orders(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $ordersManager = $request->managers->orders();

        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_BILLING_PAYMENTS, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $orders = $ordersManager->getOrdersByOwnerId($request, $activeOrganization->getPk(), EntityType::ORGANIZATION);

        $viewData = [
            TemplateVars::PAGE_TITLE => "Orders - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Orders - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-orders',
            TemplateVars::ORDERS => $orders,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATIONS => $this->organizations,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/orders.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse
     */
    public function handle_billing(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $ownersPaymentsServicesTokensManager = $request->managers->ownersPaymentsServicesTokens();

        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_BILLING_PAYMENTS, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $ownerPaymentServiceTokens = $ownersPaymentsServicesTokensManager->getAllActiveOwnerPaymentServiceTokens(
            $request,
            EntityType::ORGANIZATION,
            $activeOrganization->getPk(),
            true
        );


        $viewData = [
            TemplateVars::PAGE_TITLE => "Billing - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Billing - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-billing',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::OWNER_PAYMENT_SERVICE_TOKENS => $ownerPaymentServiceTokens,
            TemplateVars::ORGANIZATIONS => $this->organizations,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/billing.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse
     */
    public function handle_payouts(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $payoutsManager = $request->managers->payouts();
        $incomeManager = $request->managers->income();

        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_BILLING_PAYOUTS, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $payouts = [];
        $unclaimedPendingIncome = [];
        $unclaimedIncome = [];
        $totalPayablePendingIncomeAmount = 0;
        $totalPendingIncomeAmount = 0;

        if ($activeOrganization) {

            $ownerId = $activeOrganization->getPk();
            $ownerTypeId = EntityType::ORGANIZATION;

            $payouts = $payoutsManager->getPaidIncomePayoutsForOrganization($request, $ownerId);
            $unclaimedPendingIncome = $incomeManager->getAllUnclaimedPendingIncomeRecordsForOwner($request, $ownerTypeId, $ownerId);
            $unclaimedIncome = $incomeManager->getAllUnclaimedPayableIncomeRecordsForOwner($request, $ownerTypeId, $ownerId);

            // Get Total Pending Income Payout Amount
            $totalPendingIncomeAmount = $incomeManager->getTotalUnclaimedPendingIncomeAmountForOwner($request, $ownerTypeId, $ownerId);
            $totalPayablePendingIncomeAmount = $incomeManager->getTotalUnclaimedPayableIncomeAmountForOwner($request, $ownerTypeId, $ownerId);

        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Payouts - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Payouts - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-payouts',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::PAYOUTS => $payouts,
            TemplateVars::REVENUES => $unclaimedPendingIncome,
            TemplateVars::REVENUES_CONFIRMED => $unclaimedIncome,
            TemplateVars::TOTAL_PENDING_INCOME => $totalPendingIncomeAmount,
            TemplateVars::TOTAL_PAYABLE_PENDING_INCOME => $totalPayablePendingIncomeAmount,
            TemplateVars::ORGANIZATIONS => $this->organizations,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/payouts.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_profile(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_PROFILE, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $fields = [
            new CharField(DBField::DISPLAY_NAME, 'Display Name', 0, true, 'This is the name of your organization.')
        ];

        $form = new PostForm($fields, $request, $activeOrganization);

        if ($isValid = $form->is_valid()) {

            if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_PROFILE, Rights::MODERATE)) {
                $isValid = false;
                $form->set_error('Access Denied');
            }

            if ($isValid) {

                $displayName = $form->getCleanedValue(DBField::DISPLAY_NAME);

                $activeOrganization->updateField(DBField::DISPLAY_NAME, $displayName)->saveEntityToDb($request);

                $request->user->sendFlashMessage('Success', MSG_SUCCESS);

                return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());
            } else {
                return $form->handleRenderJsonErrorResponse();
            }
        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Profile - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Profile - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-profile',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATION_USERS => $activeOrganization->getOrganizationUsers(),
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::FORM => $form,
            TemplateVars::USE_CROPPIE => true,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/profile.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse
     */
    public function handle_members(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_MEMBERS, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $organizationsUsersManager = $request->managers->organizationsUsers();

        $organizationUserInvites = $organizationsUsersManager->getInvitedOrganizationUsersByOrganizationId($request, $activeOrganization->getPk());
        foreach ($organizationUserInvites as $organizationUserInvite) {
            $organizationRole = $activeOrganization->getOrganizationRoleById($organizationUserInvite->getOrganizationRoleId());
            $organizationUserInvite->setOrganizationRole($organizationRole);
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Members - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Members - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-members',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::ORGANIZATION_USERS => $activeOrganization->getOrganizationUsers(),
            TemplateVars::ORGANIZATION_USERS_INVITES => $organizationUserInvites,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/members.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $organization
     * @return HtmlResponse|JSONResponse
     */
    public function add_member(Request $request, UserEntity $user, OrganizationEntity $organization = null)
    {
        $usersManager = $request->managers->users();
        $organizationsUsersManager = $request->managers->organizationsUsers();

        if (!$organization)
            return $this->render_404($request);

        if (!$organization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_MEMBERS, Rights::ADMINISTER))
            return $this->render_404($request, 'Access Denied');

        $fields = [
            new EmailField(DBField::EMAIL_ADDRESS, 'User Email Address', true, 'This is the email address the user used to create their ESC account.'),
            new SelectField(DBField::ORGANIZATION_ROLE_ID, 'Role', $organization->getOrganizationRoles()),

        ];

        $defaults = [];

        $form = new PostForm($fields, $request, $defaults);

        $form->setTemplateFile("account/forms/form-new-member.twig");

        $form->assignViewData([
            TemplateVars::ORGANIZATION => $organization
        ]);

        if ($isValid = $form->is_valid()) {

            $emailAddress = strtolower($form->getCleanedValue(DBField::EMAIL_ADDRESS));
            $organizationRoleId = $form->getCleanedValue(DBField::ORGANIZATION_ROLE_ID);

            if ($inviteUser = $usersManager->getUserByEmailAddress($request, $emailAddress)) {
                foreach ($organization->getOrganizationUsers() as $organizationUser) {
                    if ($organizationUser->getUserId() == $inviteUser->getPk()) {
                        $isValid = false;
                        $form->set_error('User is already a member of this organization.', DBField::EMAIL_ADDRESS);
                    }
                }
            }

            if ($isValid) {

                $organizationsUsersManager->inviteUserToTeam($request, $organization, $user, $emailAddress, $organizationRoleId);

                $request->user->sendFlashMessage('Member Invitation Sent', MSG_SUCCESS);

                return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());

            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'admin-add-organization-user',
            TemplateVars::PAGE_TITLE => "Add Org User - {$organization->getDisplayName()}",
            TemplateVars::ORGANIZATION => $organization,
            TemplateVars::FORM => $form,
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/add-member.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $organization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_edit_member(Request $request, UserEntity $user, OrganizationEntity $organization = null)
    {
        $organizationsUsersInvitesManager = $request->managers->organizationsUsersInvites();

        if (!$organization)
            return $this->render_404($request);

        $organizationUserId = $request->getIdForEntity($this->url_key+1);

        if (!$organizationUser = $organization->getOrganizationUser($organizationUserId))
            return $this->render_404($request);

        if (!$organizationUser->isUserId($user->getPk()) && !$organization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_MEMBERS, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $organizationUserInvite = $organizationsUsersInvitesManager->getActiveOrganizationUserInviteByOrganizationUserId($request, $organizationUserId);

        $fields = [
            new CharField(DBField::DISPLAY_NAME, 'Full Name', 0, true, 'This is the name displayed for this organization member privately and publicly.'),
            new SelectField(DBField::ORGANIZATION_ROLE_ID, 'Role', $organization->getOrganizationRoles(), true, 'The role defines which level of access this member will have in the organization.'),
        ];

        $form = new PostForm($fields, $request, $organizationUser);

        $form->assignViewData([
            TemplateVars::ORGANIZATION => $organization,
            TemplateVars::ACTIVE_ORGANIZATION => $organization,
            TemplateVars::ORGANIZATION_USER => $organizationUser,
        ])->setTemplateFile('account/forms/form-edit-member.twig');

        if ($isValid = $form->is_valid()) {

            $displayName = $form->getCleanedValue(DBField::DISPLAY_NAME);
            $organizationRoleId = $form->getCleanedValue(DBField::ORGANIZATION_ROLE_ID);

            if ($isValid) {

                $organizationUser->assign([
                    DBField::DISPLAY_NAME => $displayName,
                    DBField::ORGANIZATION_ROLE_ID => $organizationRoleId
                ]);

                $organizationUser->saveEntityToDb($request);

                $request->user->sendFlashMessage('Member Edited Successfully', MSG_SUCCESS);

                return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());

            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-member',
            TemplateVars::PAGE_TITLE => "Edit Org User - {$organization->getDisplayName()}",
            TemplateVars::ACTIVE_ORGANIZATION => $organization,
            TemplateVars::ORGANIZATION => $organization,
            TemplateVars::FORM => $form,
            TemplateVars::ORGANIZATION_USER => $organizationUser,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::ORGANIZATION_USER_INVITE => $organizationUserInvite
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/edit-member.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_delete_member(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $organizationsUsersInvitesManager = $request->managers->organizationsUsersInvites();

        if (!$organizationUserId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, "Member ID not found");

        if (!$organizationUser = $activeOrganization->getOrganizationUser($organizationUserId))
            return $this->render_404($request, "Member not found");

        $organizationUserInvite = $organizationsUsersInvitesManager->getActiveOrganizationUserInviteByOrganizationUserId($request, $organizationUserId);

        $fields = [];

        $defaults = [];

        $form = new PostForm($fields, $request, $defaults);

        if ($form->is_valid()) {

            $organizationUser->updateField(DBField::ORGANIZATION_USER_STATUS_ID, OrganizationsUsersStatusesManager::ID_REMOVED)->saveEntityToDb($request);

            if ($organizationUserInvite)
                $organizationsUsersInvitesManager->deactivateEntity($request, $organizationUserInvite);

            $request->user->sendFlashMessage("Removed Member");
            return $form->handleRenderJsonSuccessResponse($request->getDevelopUrl("/teams/{$activeOrganization->getSlug()}/members"));

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/delete-member.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse
     */
    public function handle_roles(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_ROLES, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $viewData = [
            TemplateVars::PAGE_TITLE => "Roles - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Roles - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-roles',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATIONS => $this->organizations,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/roles.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_role(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $organizationRoleId = $request->getIdForEntity($this->url_key+1);

        if (!$organizationRole = $activeOrganization->getOrganizationRoleById($organizationRoleId))
            return $this->render_404($request);

        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_ROLES, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $rightsManager = $request->managers->rights();

        $organizationRoleFields = [
            new CharField(DBField::DISPLAY_NAME, 'Role Name', 64, true, 'This is the name of the role displayed to members.'),
            new TextField(DBField::DESCRIPTION, 'Description', 1024, false, 'Describe the function of members in this role.')
        ];

        $formViewData = [
            TemplateVars::ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATION_ROLE => $organizationRole
        ];

        $form = new PostForm($organizationRoleFields, $request, $organizationRole);

        $form->assignViewData($formViewData)->setTemplateFile('account/forms/form-edit-role.twig');

        if ($isValid = $form->is_valid()) {

            if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_PERMISSIONS, Rights::ADMINISTER)) {
                $isValid = false;
                $form->set_error('Access Denied');
            }

            if ($isValid) {
                $organizationRole->assign([
                    DBField::DISPLAY_NAME => $form->getCleanedValue(DBField::DISPLAY_NAME),
                    DBField::DESCRIPTION => $form->getCleanedValue(DBField::DESCRIPTION)
                ]);

                $organizationRole->saveEntityToDb($request);

                $request->user->sendFlashMessage('Saved Successfully');

                return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());
            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $organizationsRightsManager = $request->managers->organizationsRights();

        $organizationRights = $organizationsRightsManager->getAllOrganizationRightsByOrganizationId($request, $activeOrganization->getPk(), true);
        $organizationRights = $organizationsRightsManager->index($organizationRights);

        $fields = [];

        foreach ($activeOrganization->getOrganizationPermissionsByRoleId($organizationRoleId) as $organizationPermission) {
            $organizationRole = $activeOrganization->getOrganizationRoleById($organizationPermission->getOrganizationRoleId());
            if (!$organizationRole->is_admin()) {
                $fields[] = new SelectField($organizationPermission->getDynamicFormField(), $organizationPermission->getPk(), $rightsManager->getRightSelectOptions(), false);
            }
        }

        $defaults = [];

        $organizationPermissionsForm = [
            TemplateVars::ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATION_RIGHTS => $organizationRights,
            TemplateVars::ORGANIZATION_ROLE => $organizationRole
        ];

        $permissionsForm = new PostForm($fields, $request, $defaults);
        $permissionsForm->assignViewData($organizationPermissionsForm)->setTemplateFile('account/forms/form-edit-role-permissions.twig');


        $viewData = [
            TemplateVars::PAGE_TITLE => "Roles - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Roles - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-role',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::ORGANIZATION_ROLE => $organizationRole,
            TemplateVars::PERMISSIONS_FORM => $permissionsForm,
            TemplateVars::FORM => $form,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/edit-role.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_edit_permissions(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$request->is_post())
            return $this->render_404($request);

        if (!$organizationRoleId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request);

        if (!$organizationRole = $activeOrganization->getOrganizationRoleById($organizationRoleId))
            return $this->render_404($request);

        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_PERMISSIONS, Rights::ADMINISTER))
            return $this->render_404($request, 'Access Denied');

        $rightsManager = $request->managers->rights();
        $organizationsRightsManager = $request->managers->organizationsRights();

        $organizationRights = $organizationsRightsManager->getAllOrganizationRightsByOrganizationId($request, $activeOrganization->getPk(), true);
        $organizationRights = $organizationsRightsManager->index($organizationRights);

        $fields = [];

        foreach ($activeOrganization->getOrganizationPermissionsByRoleId($organizationRoleId) as $organizationPermission) {
            $organizationRole = $activeOrganization->getOrganizationRoleById($organizationPermission->getOrganizationRoleId());
            if (!$organizationRole->is_admin()) {
                $fields[] = new SelectField($organizationPermission->getDynamicFormField(), $organizationPermission->getPk(), $rightsManager->getRightSelectOptions(), false);
            }
        }

        $defaults = [];

        $organizationPermissionsForm = [
            TemplateVars::ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATION_RIGHTS => $organizationRights,
            TemplateVars::ORGANIZATION_ROLE => $organizationRole
        ];

        $permissionsForm = new PostForm($fields, $request, $defaults);
        $permissionsForm->assignViewData($organizationPermissionsForm)->setTemplateFile('account/forms/form-edit-role-permissions.twig');

        if ($isValid = $permissionsForm->is_valid()) {

            if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_PERMISSIONS, Rights::ADMINISTER)) {

                $permissionsForm->set_error('Access Denied');
                $isValid = false;
            }

            if ($isValid) {

                $adminAccess = Rights::getAccessLevel('amu');
                $moderateAccess = Rights::getAccessLevel('mu');
                $useAccess = Rights::getAccessLevel('u');

                foreach ($activeOrganization->getOrganizationPermissionsByRoleId($organizationRoleId) as $organizationPermission) {
                    $fieldName = $organizationPermission->getDynamicFormField();

                    if ($permissionsForm->has_field($fieldName)) {
                        $fieldValue = $permissionsForm->getCleanedValue($fieldName);
                        if ($fieldValue) {

                            $organizationPermission->updateField(DBField::IS_ACTIVE, 1);

                            switch ($fieldValue) {

                                case Rights::USE:
                                    $organizationPermission->updateField(DBField::ACCESS_LEVEL, $useAccess);
                                    break;
                                case Rights::MODERATE:
                                    $organizationPermission->updateField(DBField::ACCESS_LEVEL, $moderateAccess);
                                    break;
                                case Rights::ADMINISTER:
                                    $organizationPermission->updateField(DBField::ACCESS_LEVEL, $adminAccess);
                            }

                        } else {
                            $updatedData = [
                                DBField::IS_ACTIVE => 0,
                                DBField::ACCESS_LEVEL => 0
                            ];
                            $organizationPermission->assign($updatedData);
                        }
                    }
                    $organizationPermission->saveEntityToDb($request);
                }
                return $permissionsForm->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());
            }
        }
        return $permissionsForm->handleRenderJsonErrorResponse();
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_edit_right(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $rightsManager = $request->managers->rights();

        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_PERMISSIONS, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        $organizationRightId = $request->getIdForEntity($this->url_key+1);

        if (!$organizationRight = $activeOrganization->getOrganizationRight($organizationRightId))
            return $this->render_404($request);

        $fields = [];

        $defaults = [];

        foreach ($activeOrganization->getOrganizationRoles() as $organizationRole) {
            if (!$organizationRole->is_admin()) {
                $fieldName = $organizationRole->getDynamicFormField();
                $fields[] = new SelectField($fieldName, $organizationRole->getDisplayName(), $rightsManager->getRightSelectOptions(), false);
            }
        }

        $formViewData = [
            TemplateVars::ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATION_RIGHT => $organizationRight
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->assignViewData($formViewData)->setTemplateFile("account/forms/form-edit-right.twig");

        if ($form->is_valid()) {

            $adminAccess = Rights::getAccessLevel('amu');
            $moderateAccess = Rights::getAccessLevel('mu');
            $useAccess = Rights::getAccessLevel('u');


            foreach ($activeOrganization->getOrganizationRoles() as $organizationRole) {

                if (!$organizationRole->is_admin()) {

                    $organizationPermission = $activeOrganization->getOrganizationPermissionByRightAndRole($organizationRightId, $organizationRole->getPk());

                    $fieldName = $organizationRole->getDynamicFormField();

                    if ($form->has_field($fieldName)) {
                        $fieldValue = $form->getCleanedValue($fieldName);

                        if ($fieldValue) {

                            $organizationPermission->updateField(DBField::IS_ACTIVE, 1);

                            switch ($fieldValue) {

                                case Rights::USE:
                                    $organizationPermission->updateField(DBField::ACCESS_LEVEL, $useAccess);
                                    break;
                                case Rights::MODERATE:
                                    $organizationPermission->updateField(DBField::ACCESS_LEVEL, $moderateAccess);
                                    break;
                                case Rights::ADMINISTER:
                                    $organizationPermission->updateField(DBField::ACCESS_LEVEL, $adminAccess);
                            }

                        } else {
                            $updatedData = [
                                DBField::IS_ACTIVE => 0,
                                DBField::ACCESS_LEVEL => 0
                            ];
                            $organizationPermission->assign($updatedData);
                        }

                        $organizationPermission->saveEntityToDb($request);
                    }
                }

            }

            $request->user->session->sendSessionFlashMessage('Permissions Saved Successfully', MSG_SUCCESS);

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Edit Right - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Edit Right - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-edit-right',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::FORM => $form,
            TemplateVars::ORGANIZATION_RIGHT => $organizationRight
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/edit-org-right.twig');

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_hosts(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        if (!$activeOrganization->permissions->has(OrganizationsBaseRightsManager::RIGHT_ORG_HOSTS, Rights::USE))
            return $this->render_404($request, 'Access Denied');

        require "account/manage-hosts.php";

        $hostsController = new OrganizationHostsController($this->templateFactory, $activeOrganization, $user, $this->organizations);
        return $hostsController->render($request, $this->url_key+1);

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param OrganizationEntity|null $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_upload_profile_image(Request $request, UserEntity $user, OrganizationEntity $activeOrganization)
    {
        $imagesTypesManager = $request->managers->imagesTypes();
        $imagesManager = $request->managers->images();
        $activityManager = $request->managers->activity();
        $organizationsActivityManager = $request->managers->organizationsActivities();

        $imageType = $imagesTypesManager->getImageTypeById($request, ImagesTypesManager::ID_ORGANIZATION_AVATAR);

        $form = new ImageUploadForm($request, 'square', 1600);

        if ($form->is_valid()) {

            // Get the file hash from the form object.
            $uploadId = $request->post[$form->getFormFieldUploadId()];

            // Try to get DB record for uploaded file.
            $info = UploadsHelper::get_file_info($uploadId);

            // If we founds a DB record and validated that the file_id exists, we should prepare to process it.
            $sourceFile = UploadsHelper::path_from_file_id($uploadId);
            $fileName = $info[DBField::FILENAME];

            if ($imageAsset = $form->handleImageUpload($request, $uploadId, $sourceFile)) {

                $oldAvatarImage = $imagesManager->getActiveOrganizationAvatarImageByOrganizationId($request, $activeOrganization->getPk());

                if ($oldAvatarImage) {
                    $oldAvatarImage->updateField(DBField::IS_ACTIVE, 0)->saveEntityToDb($request);
                }

                $image = $imagesManager->getImageByAssetAndContext(
                    $request,
                    $imageType->getPk(),
                    $activeOrganization->getPk(),
                    $imageAsset->getPk()
                );

                if (!$image) {
                    $image = $imagesManager->createNewImage(
                        $request,
                        $imageType->getPk(),
                        $activeOrganization->getPk(),
                        $imageAsset->getPk(),
                        $fileName
                    );
                } else {
                    if (!$image->is_active())
                        $image->updateField(DBField::IS_ACTIVE, 1)->saveEntityToDb($request);
                }

                $activity = $activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_TEAM_PROFILE_IMAGE_UPLOAD,
                    $activeOrganization->getPk(),
                    $image->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                // Image was processed successfully, let's propagate the new file to our media servers.
                $request->user->sendFlashMessage("Upload Success", MSG_SUCCESS);

                return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());


            } else {

                return $form->handleRenderJsonErrorResponse();

            }

        } else {
            if ($request->is_post()) {

                return $form->handleRenderJsonErrorResponse();
            }
        }

        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'upload-image',
            TemplateVars::USE_CROPPIE => true,
            TemplateVars::FORM => $form,
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/blocks/upload-team-avatar.twig');
    }

}