<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/26/19
 * Time: 11:39 AM
 */

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class ManageGameModUpdateChannelController extends BaseContent
{
    protected $url_key = 6;

    /** @var GameModEntity $gameMod */
    protected $gameMod;
    /** @var OrganizationEntity $activeOrganization */
    protected $activeOrganization;
    /** @var OrganizationEntity[] $organizations */
    protected $organizations = [];
    /** @var UserEntity $user */
    protected $user;
    /** @var array $updateChannels */
    protected $updateChannels = [];
    /** @var string $updateChannel */
    protected $updateChannel;
    /** @var GameModActiveBuildEntity */
    protected $activeGameModBuildSummary;
    /** @var array $activeGameBuildSummaries */
    protected $activeGameModBuildSummaries = null;


    /** @var GamesManager $gamesManager */
    protected $gamesManager;
    /** @var GamesModsManager $gamesModsManager */
    protected $gamesModsManager;
    /** @var GamesModsLicensesManager $gamesModsLicensesManager */
    protected $gamesModsLicensesManager;
    /** @var GamesModsBuildsManager $gamesModsBuildsManager */
    protected $gamesModsBuildsManager;
    /** @var GamesModsActiveBuildsManager $gamesModsActiveBuildsManager */
    protected $gamesModsActiveBuildsManager;
    /** @var GamesModsActiveCustomAssetsManager $gamesModsActiveCustomAssetsManager */
    protected $gamesModsActiveCustomAssetsManager;
    /** @var GamesModsDataManager $gamesModsDataManager */
    protected $gamesModsDataManager;
    /** @var GamesModsDataSheetsManager $gamesModsDataSheetsManager */
    protected $gamesModsDataSheetsManager;
    /** @var GamesModsDataSheetsRowsManager $gamesModsDataSheetsRowsManager */
    protected $gamesModsDataSheetsRowsManager;


    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var OrganizationsActivityManager $organizationsActivityManager */
    protected $organizationsActivityManager;
    /** @var GamesAssetsManager $gamesAssetsManager */
    protected $gamesAssetsManager;
    /** @var UsersManager $usersManager */
    protected $usersManager;
    /** @var ImagesManager $imagesManager */
    protected $imagesManager;
    /** @var ImagesTypesManager $imagesTypesManager */
    protected $imagesTypesManager;
    /** @var ContextXGamesAssetsManager $contextXGamesAssetsManager */
    protected $contextXGamesAssetsManager;

    /** @var array  */
    protected $channelGameModBuildOptions = [];

    /** @var int $selectedGameModBuildId */
    protected $selectedGameModBuildId = 0;



    protected $pages = [
        'builds' => 'handle_manage_mod_builds',
        'create-mod-build' => 'handle_create_mod_build',
        'set-active-build' => 'handle_set_build_active',
        'view-mod-build' => 'handle_view_mod_build',
        'publish-mod-to-live' => 'handle_publish_mod_build_to_live',
        'access-control' => 'handle_access_control',
        'add-user-access' => 'handle_add_user_access',
        'remove-user-access' => 'handle_remove_user_access',
    ];

    protected $gameModBuildPages = [

        // Custom Assets
        'batch-upload-custom-game-mod-assets' => 'handle_batch_upload_custom_game_mod_assets',
        'upload-custom-game-mod-asset' => 'handle_upload_custom_game_mod_asset',
        'view-custom-game-mod-asset' => 'handle_view_custom_game_mod_asset',
        'download-custom-game-mod-asset-file' => 'handle_download_custom_game_mod_asset_file',
        'set-custom-game-mod-asset-active' => 'handle_set_custom_game_mod_asset_active',
        'delete-custom-game-mod-asset' => 'handle_delete_custom_game_mod_asset',
        'batch-download-custom-game-mod-asset-files' => 'handle_batch_download_custom_game_mod_asset_files',

        // Custom Data
        'upload-custom-game-mod-data' => 'handle_upload_custom_game_mod_data',
        'view-custom-game-mod-data' => 'handle_view_custom_game_mod_data',
        'update-custom-game-mod-data-sheet' => 'handle_update_custom_game_mod_data_sheet',
        'download-custom-game-mod-data-xls' => 'handle_download_custom_game_mod_data_xls',

        // Todo
        'delete-custom-game-mod-asset-file' => 'handle_delete_custom_game_mod_asset_file',

    ];

    /**
     * ManageModUpdateChannelController constructor.
     * @param null $template_factory
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param array $organizations
     */
    public function __construct($template_factory = null, UserEntity $user, GameModEntity $gameMod,
                                OrganizationEntity $activeOrganization, $organizations = [], $updateChannel,
                                $updateChannels = [])
    {
        parent::__construct($template_factory);

        $this->user = $user;
        $this->gameMod = $gameMod;
        $this->activeOrganization = $activeOrganization;
        $this->organizations = $organizations;
        $this->updateChannel = $updateChannel;

        $this->pages = array_merge($this->pages, $this->gameModBuildPages);
    }


    /**
     * @param Request $request
     * @param null $url_key
     * @param null $pages
     * @param null $render_default
     * @param null $root
     * @return HttpResponse
     */
    public function render(Request $request, $url_key = null, $pages = null, $render_default = null, $root = null)
    {
        if (!$request->user->is_authenticated()) {
            return $this->redirectToLogin($request);
        }
        $this->url_key = $url_key;

        $func = $this->resolve($request, $url_key, $pages, $render_default, $root);

        if ($func === null)
            throw new Http404();

        if ($request->hasPostParam('change-channel') && $request->readPostParam('change-channel') == $this->updateChannel) {
            $newChannel = $request->readPostParam('update-channel', $this->updateChannel);

            $url = $request->url;
            $url[$this->url_key-1] = $newChannel;

            $newPath = join('/', $url);

            return $this->redirect($newPath);
        }

        $this->gamesManager = $request->managers->games();
        $this->gamesModsBuildsManager = $request->managers->gamesModsBuilds();
        $this->activityManager = $request->managers->activity();
        $this->organizationsActivityManager = $request->managers->organizationsActivities();
        $this->gamesDataManager = $request->managers->gamesData();
        $this->gamesAssetsManager = $request->managers->gamesAssets();
        $this->contextXGamesAssetsManager = $request->managers->contextXGamesAssets();
        $this->gamesModsLicensesManager = $request->managers->gamesModsLicenses();
        $this->usersManager = $request->managers->users();
        $this->imagesManager = $request->managers->images();
        $this->imagesTypesManager = $request->managers->imagesTypes();
        $this->gamesModsActiveBuildsManager = $request->managers->gamesModsActiveBuilds();
        $this->gamesModsActiveCustomAssetsManager = $request->managers->gamesModsActiveCustomAssets();
        $this->gamesModsDataManager = $request->managers->gamesModsData();
        $this->gamesModsDataSheetsManager = $request->managers->gamesModsDataSheets();
        $this->gamesModsDataSheetsRowsManager = $request->managers->gamesModsDataSheetsRows();

        $this->channelGameModBuildOptions = $this->gamesModsBuildsManager->getGameModBuildOptionsByGameModAndUpdateChannel($request, $this->gameMod->getPk(), $this->updateChannel);

        $cachedSelectedGameBuildId = $request->user->session->safeGet($this->generateSelectedGameBuildIdSessionKey());
        $selectedGameModBuildId = $request->get->readParam(DBField::GAME_MOD_BUILD_ID, $cachedSelectedGameBuildId);
        $this->selectedGameModBuildId = $selectedGameModBuildId;

        if ($this->activeGameModBuildSummaries === null) {
            $this->activeGameModBuildSummaries = $this->gamesModsActiveBuildsManager->getGameModActiveBuildVersionSummaries($request, $this->gameMod->getPk());
        }

        if (in_array($func, array_values($this->gameModBuildPages)) && !in_array($selectedGameModBuildId, array_keys($this->channelGameModBuildOptions))) {
            throw new Http404('Game Mod Build ID Not Found');
        }


        return $this->$func($request, $this->user, $this->gameMod, $this->activeOrganization, $selectedGameModBuildId);
    }

    /**
     * @return string
     */
    protected function generateSelectedGameBuildIdSessionKey()
    {
        return "{$this->gameMod->getGameSlug()}_game-mod_{$this->updateChannel}_selected-build-id";
    }

    /**
     * @param Request $request
     * @param array $viewData
     * @return array
     */
    protected function mergeSharedTemplateVars(Request $request, $viewData = [])
    {

        if ($this->activeGameModBuildSummary === null) {
            $this->activeGameModBuildSummary = $this->gamesModsActiveBuildsManager->getGameActiveBuildVersionSummaryByUpdateChannel(
                $request,
                $this->gameMod->getPk(),
                $this->updateChannel
            );
        }

        return array_merge(
            [
                TemplateVars::PROFILE_USER => $this->user,
                TemplateVars::GAME => $this->gameMod->getGame(),
                TemplateVars::GAME_MOD => $this->gameMod,
                TemplateVars::UPDATE_CHANNEL => $this->updateChannel,
                TemplateVars::SELECTED_GAME_MOD_BUILD_ID => $this->selectedGameModBuildId,
                TemplateVars::UPDATE_CHANNELS => $this->gamesManager->getUpdateChannelOptions(),
                TemplateVars::ACTIVE_UPDATE_CHANNEL => $this->gamesManager->getUpdateChannelOption($this->updateChannel),
                TemplateVars::ORGANIZATIONS => $this->organizations,
                TemplateVars::ACTIVE_ORGANIZATION => $this->activeOrganization,
                TemplateVars::ACTIVE_BUILD_VERSION_SUMMARY => $this->activeGameModBuildSummary
            ],
            $viewData
        );
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param null $selectedGameModBuildId
     * @return HtmlResponse
     */
    protected function handle_manage_mod_builds(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId = null)
    {
        $gameModBuilds = $this->gamesModsBuildsManager->getGameModBuildsByGameModId($request, $gameMod->getPk(), true, $this->updateChannel, true, true);

        $viewData = [
            TemplateVars::PAGE_TITLE => "Manage Builds for {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Manage Builds for {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $gameMod->getEditUrl("/{$this->updateChannel}/builds"),
            TemplateVars::PAGE_IDENTIFIER => 'game-mod-builds',
            TemplateVars::GAME_MOD_BUILDS => $gameModBuilds,
            TemplateVars::USE_CROPPIE => true,
        ];

        return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/manage-builds.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_create_mod_build(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {
        $fields = [
            new BuildVersionField(DBField::BUILD_VERSION, 'Mod Build Version', 32, true, 'Format: x.y.z'),
        ];


        $form = new PostForm($fields, $request);

        $form->setTemplateFile('account/mods/manage-mod/forms/form-create-mod-build.twig');

        $formViewData = [
            TemplateVars::GAME => $gameMod->getGame(),
            TemplateVars::GAME_MOD => $gameMod
        ];

        $form->assignViewData($formViewData);

        if ($isValid = $form->is_valid()) {

            $buildVersion = $form->getCleanedValue(DBField::BUILD_VERSION);

            $gameModBuild = $this->gamesModsBuildsManager->createNewGameModBuild(
                $request,
                $gameMod->getPk(),
                $this->updateChannel,
                $buildVersion
            );

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_CREATE_GAME_MOD_BUILD,
                $gameMod->getPk(),
                $gameModBuild->getPk(),
                $user->getUiLanguageId(),
                $user
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $activeOrganization,
                $activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            $request->user->sendFlashMessage('Mod Build Created Successfully');

            $next = $request->getRedirectBackUrl();

            return $form->handleRenderJsonSuccessResponse($next);
        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::GAME_MOD => $gameMod,
            TemplateVars::FORM => $form
        ];

        return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/create-mod-build.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|HttpResponseRedirect
     */
    public function handle_set_build_active(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {
        if (!$gameModBuildId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Mod Build Id Not Found');

        if (!$gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $gameModBuildId, $gameMod->getPk()))
            return $this->render_404($request, 'Mod Build Not Found');

        $this->gamesModsActiveBuildsManager->createUpdateGameModActiveBuild($request, $gameMod->getPk(), $this->updateChannel, $gameModBuild->getPk());

        $activity = $this->activityManager->trackActivity(
            $request,
            ActivityTypesManager::ACTIVITY_TYPE_USER_SET_MOD_BUILD_ACTIVE,
            $gameMod->getPk(),
            $gameModBuild->getPk(),
            $user->getUiLanguageId(),
            $user
        );

        $this->organizationsActivityManager->trackOrganizationActivity(
            $request,
            $activity,
            $activeOrganization,
            $activeOrganization->getOrganizationUserByUserId($request->user->getId())
        );


        $request->user->sendFlashMessage('Game Mod Build Set Active', MSG_SUCCESS);

        return $this->redirect($request->getRedirectBackUrl());
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse
     */
    public function handle_view_mod_build(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {
        if (!$gameModBuildId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Mod Build Id Not Found');

        if (!$gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $gameModBuildId, $gameMod->getPk()))
            return $this->render_404($request, 'Mod Build Not Found');

        $gameModData = $this->gamesModsDataManager->getGameModDataByGameModBuildId($request, $gameModBuild->getPk());

        $userIds = [];
        $userIds[] = $gameModBuild->getCreatorId();

        // Get custom game assets and creator user context for this game mod build
        $gamesModsActiveCustomAssets = $this->gamesModsActiveCustomAssetsManager->getGameModActiveCustomAssetLinksByGameModBuildIds(
            $request,
            $gameModBuild->getPk()
        );

        $customGameAssetIds = unique_array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $gamesModsActiveCustomAssets);

        // Get Custom Game Asset Creator User Ids
        foreach ($gameModBuild->getCustomGameModBuildAssets() as $customGameAsset) {
            if (in_array($customGameAsset->getCustomGameAssetId(), $customGameAssetIds) && !in_array($customGameAsset->getUserId(), $userIds))
                $userIds[] = $customGameAsset->getUserId();
        }

        // Get all users for UserIds we have
        if ($userIds) {
            $users = $this->usersManager->getUsersByIds($request, $userIds);
            /** @var UserEntity[] $users */
            $users = array_index($users, $this->usersManager->getPkField());
        } else {
            $users = [];
        }

        // Set Creator Users for game build and published game build
        $gameModBuild->setUser($users[$gameModBuild->getCreatorId()]);

        // Set Creator Users for all custom game assets
        foreach ($gamesModsActiveCustomAssets as $gameActiveCustomAsset) {
            if ($customGameAssetId = $gameActiveCustomAsset->getContextXGameAssetId()) {
                $customGameAsset = $gameModBuild->getCustomGameModBuildAssetById($customGameAssetId);
                if ($customGameAsset && array_key_exists($customGameAsset->getUserId(), $users))
                    $customGameAsset->updateField(VField::USER, $users[$customGameAsset->getUserId()]);

                $gameActiveCustomAsset->updateField(VField::CUSTOM_GAME_ASSET, $customGameAsset);
            }
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "View Build {$gameModBuild->getBuildVersion()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "View Build {$gameModBuild->getBuildVersion()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'view-game-mod-build',
            TemplateVars::GAME_MOD_BUILD => $gameModBuild,
            TemplateVars::CUSTOM_GAME_MOD_ASSETS => $gamesModsActiveCustomAssets,
            TemplateVars::GAME_MOD_DATA => $gameModData,
        ];

        return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/view-game-mod-build.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_publish_mod_build_to_live(Request $request,  UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {
        if (!$gameModBuildId = $request->getIdForEntity($this->url_key+1))
            throw new Http404();

        if (!in_array($gameModBuildId, array_keys($this->channelGameModBuildOptions)))
            return $this->render_404($request);

        if (!$gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $gameModBuildId, $gameMod->getPk()))
            throw new Http404();

        $changeSummary = [
            'custom_assets' => [
            ],
            'custom_data' => [
            ]
        ];

        $gameModBuildCustomAssets = $this->gamesModsActiveCustomAssetsManager->getActiveGameModActiveCustomAssetLinksByGameModBuildId(
            $request,
            $gameModBuildId
        );

        if ($gameModBuildCustomAssets)
            $gameModBuildCustomAssets = array_index($gameModBuildCustomAssets, DBField::SLUG);

        $gameModBuild->updateField(VField::CUSTOM_GAME_ASSETS, $gameModBuildCustomAssets);

        $customData = $this->gamesModsDataManager->getGameModDataByGameModBuildId($request, $gameModBuildId, true);

        if ($customData)
            $customData = array_index($customData, DBField::KEY);

        $gameModBuild->updateField(VField::CUSTOM_DATA, $customData);

        $fields = [
            new HiddenField(DBField::GAME_MOD_BUILD_ID, 'Game Mod Build', 0, true),
            new BooleanField(DBField::IS_ACTIVE, 'Set Active', false, "If checked, sets the published build as active on live channel."),
            new HiddenField(VField::NEXT, "Next", 0, false)
        ];

        if (array_key_exists(GamesManager::UPDATE_CHANNEL_LIVE, $this->activeGameModBuildSummaries)) {
            $liveGameModBuildId = $this->activeGameModBuildSummaries[GamesManager::UPDATE_CHANNEL_LIVE][DBField::GAME_MOD_BUILD_ID];

            $liveGameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $liveGameModBuildId, $gameMod->getPk());

            $liveGameModBuildCustomAssets = $this->gamesModsActiveCustomAssetsManager->getActiveGameModActiveCustomAssetLinksByGameModBuildId(
                $request,
                $liveGameModBuildId
            );
            if ($liveGameModBuildCustomAssets)
                $liveGameModBuildCustomAssets = array_index($liveGameModBuildCustomAssets, DBField::SLUG);

            $liveGameModBuild->updateField(VField::CUSTOM_GAME_ASSETS, $liveGameModBuildCustomAssets);

            $newCustomAssets = 0;
            $changedCustomAssets = 0;
            $removedCustomAssets = 0;
            foreach ($gameModBuildCustomAssets as $slug => $gameModBuildCustomAsset) {
                if (!array_key_exists($slug, $liveGameModBuildCustomAssets)) {
                    $newCustomAssets++;
                } else {
                    if ($gameModBuildCustomAsset->getContextXGameAssetId() != $liveGameModBuildCustomAssets[$slug]->getContextXGameAssetId())
                        $changedCustomAssets++;
                }

            }
            foreach ($liveGameModBuildCustomAssets as $slug => $liveGameBuildCustomAsset) {
                if (!array_key_exists($slug, $gameModBuildCustomAssets))
                    $removedCustomAssets++;
            }

            if ($newCustomAssets || $changedCustomAssets || $removedCustomAssets) {
                $changes = [];
                if ($newCustomAssets)
                    $changes[] = "{$newCustomAssets} new";

                if ($changedCustomAssets)
                    $changes[] = "{$changedCustomAssets} updated";

                if ($removedCustomAssets)
                    $changes[] = "{$removedCustomAssets} removed";

                if ($changes)
                    $changeSummary['custom_assets'] = join(', ', $changes);
            }

            $newCustomData = 0;
            $changedCustomData = 0;
            $removedCustomData = 0;
            $liveCustomData = $this->gamesModsDataManager->getGameModDataByGameModBuildId($request, $liveGameModBuildId);
            if ($liveCustomData)
                $liveCustomData = array_index($customData, DBField::KEY);

            $liveGameModBuild->updateField(VField::CUSTOM_DATA, $liveCustomData);

            foreach ($customData as $key => $customDatum) {
                if (!array_key_exists($key, $liveCustomData))
                    $newCustomData++;
                else {
                    if (md5(json_encode($customDatum->getSheetDataArrays())) != md5(json_encode($liveCustomData[$key]->getSheetDataArrays())))
                        $changedCustomData++;
                }
            }
            foreach ($liveCustomData as $key => $liveCustomDatum) {
                if (!array_key_exists($key, $customData))
                    $removedCustomData++;
            }

            if ($newCustomData || $changedCustomData || $removedCustomData) {
                $changes = [];

                if ($newCustomData)
                    $changes[] = "{$newCustomData} new";

                if ($changedCustomData)
                    $changes[] = "{$changedCustomData} updated";

                if ($removedCustomData)
                    $changes[] = "{$removedCustomData} removed";

                if ($changes)
                    $changeSummary['custom_data'] = join(', ', $changes);
            }


        } else {
            $liveGameModBuild = [];
        }

        $defaults = [
            DBField::GAME_MOD_BUILD_ID => $gameModBuildId,
            DBField::IS_ACTIVE => 1
        ];

        $form = new PostForm($fields, $request, $defaults);

        $formViewData = [
            TemplateVars::GAME_MOD => $gameMod,
            TemplateVars::GAME_BUILD => $gameModBuild
        ];

        $form->assignViewData($formViewData)->setTemplateFile('account/mods/forms/publish-game-mod-build.twig');

        if ($isValid = $form->is_valid()) {

            $devGameModBuildId = $form->getCleanedValue(DBField::GAME_MOD_BUILD_ID);

            if ($devGameModBuildId != $gameModBuildId) {
                $isValid = false;
                $form->set_error('Game Mod Build Id Mismatch.');
            }

            if ($isValid) {
                // Create New Game Build
                $newGameModBuild = $this->gamesModsBuildsManager->createNewGameModBuild(
                    $request,
                    $gameModBuild->getGameModId(),
                    GamesManager::UPDATE_CHANNEL_LIVE,
                    $gameModBuild->getBuildVersion()
                );

                $setActive = $form->getCleanedValue(DBField::IS_ACTIVE, 0);

                // Clone Custom Game Assets
                $this->gamesModsActiveCustomAssetsManager->cloneCustomGameModBuildAssets($request, $gameModBuild, $newGameModBuild);
                // Clone Custom Game Data
                $this->gamesModsDataManager->cloneGameModBuildData($request, $gameModBuild, $newGameModBuild);
                // Update Game Build to published
                $updatedModBuildData = [
                    DBField::PUBLISHED_GAME_MOD_BUILD_ID => $newGameModBuild->getPk(),
                    DBField::PUBLISHED_TIME => $request->getCurrentSqlTime()
                ];

                $gameModBuild->assign($updatedModBuildData)->saveEntityToDb($request);

                // Track Activity
                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_PUBLISH_GAME_MOD,
                    $gameModBuild->getPk(),
                    $newGameModBuild->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );


                if ($request->settings()->is_prod()) {

                    $link = $gameMod->getEditUrl("/live/view-mod-build/{$newGameModBuild->getPk()}");

                    Modules::load_helper(Helpers::SLACK);

                    $slackMessage = "@channel {$user->getEmailAddress()} published a new mod build to live: {$newGameModBuild->getBuildVersion()}";

                    $slackAttachment = new SlackAttachment(
                        $user,
                        "{$gameMod->getDisplayName()} - v{$newGameModBuild->getBuildVersion()}",
                        $link,
                        null,
                        new SlackActionButton('View', $link),
                        new SlackField('Environment', $request->host),
                        new SlackField('Game', $gameMod->getGame()->getDisplayName()),
                        new SlackField('Mod', $gameMod->getDisplayName()),
                        new SlackField('Request ID', $request->requestId)
                    );

                    SlackHelper::sendCard($slackMessage, $slackAttachment);
                }

                $request->user->sendFlashMessage('Mod Build Published Successfully', MSG_SUCCESS);



                if ($setActive) {
                    $this->gamesModsActiveBuildsManager->createUpdateGameModActiveBuild($request, $gameMod->getPk(), $newGameModBuild->getUpdateChannel(), $newGameModBuild->getPk());

                    // Track Activity
                    $activity = $this->activityManager->trackActivity(
                        $request,
                        ActivityTypesManager::ACTIVITY_TYPE_USER_SET_BUILD_ACTIVE,
                        $gameModBuild->getPk(),
                        $newGameModBuild->getPk(),
                        $user->getUiLanguageId(),
                        $user
                    );

                    $this->organizationsActivityManager->trackOrganizationActivity(
                        $request,
                        $activity,
                        $activeOrganization,
                        $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                    );
                }

                $next = $gameMod->getEditUrl("/{$newGameModBuild->getUpdateChannel()}/builds");

                $formNext = $form->getCleanedValue(VField::NEXT);

                if ($formNext) {
                    $next = base64_decode($formNext);
                }

                // Create New Game Build
                $newDevGameModBuild = $this->gamesModsBuildsManager->createNewGameModBuild(
                    $request,
                    $gameModBuild->getGameModId(),
                    GamesManager::UPDATE_CHANNEL_DEV,
                    Strings::incrementBuildVersion($gameModBuild->getBuildVersion())
                );

                // Clone Custom Game Assets
                $this->gamesModsActiveCustomAssetsManager->cloneCustomGameModBuildAssets($request, $gameModBuild, $newDevGameModBuild);
                // Clone Custom Game Data
                $this->gamesModsDataManager->cloneGameModBuildData($request, $gameModBuild, $newDevGameModBuild);

                $this->gamesModsActiveBuildsManager->createUpdateGameModActiveBuild($request, $gameMod->getPk(), $newDevGameModBuild->getUpdateChannel(), $newDevGameModBuild->getPk());


                return $form->handleRenderJsonSuccessResponse($next);

            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post()) {
                return $form->handleRenderJsonErrorResponse();
            }
        }

        $viewData = [
            TemplateVars::GAME_MOD_BUILD => $gameModBuild,
            TemplateVars::FORM => $form,
            TemplateVars::LIVE_GAME_MOD_BUILD => $liveGameModBuild,
            TemplateVars::CHANGE_SUMMARY => $changeSummary
        ];

        return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/manage-build/publish-mod-build-live.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse
     */
    protected function handle_access_control(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {
        $gameModLicenses = $this->gamesModsLicensesManager->getActiveGameModLicenseUsersByGameModId(
            $request,
            $gameMod->getPk(),
            $this->updateChannel
        );

        $viewData = [
            TemplateVars::PAGE_TITLE => "Access Control - {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Access Control - {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'game-mod-access-control',
            TemplateVars::LICENSE_USERS => $gameModLicenses,
        ];

        return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/access-control.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_add_user_access(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {
        force_slash($request);

        $usersManager = $request->managers->users();

        $translations = $request->translations;

        $fields = [
            new EmailField(DBField::EMAIL_ADDRESS, $translations['Email Address'], true, 'Email address of the user on the platform.'),
            new DateTimeField(DBField::START_TIME, $translations['License Start Time'], true, 'License Start Time (EST)'),
            new DateTimeField(DBField::END_TIME, $translations['Expiration Time'], false, 'License End Time (EST)'),
        ];


        $estTimeZone = new DateTimeZone('America/New_York');
        $utcTimeZone = new DateTimeZone('UTC');

        $dt = new DateTime('now', $estTimeZone);
        //$dt->modify("-1 hour");

        $defaults = [
            DBField::START_TIME => $dt->format(SQL_DATETIME),
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->setTemplateFile('account/mods/forms/new-user-license.twig');

        if ($isValid = $form->is_valid()) {

            $emailAddress = strtolower($form->getCleanedValue(DBField::EMAIL_ADDRESS));
            $startTime = $form->getCleanedValue(DBField::START_TIME);
            $endTime = $form->getCleanedValue(DBField::END_TIME);

            $gameModUser = $usersManager->getUserByEmailAddress($request, $emailAddress);

            $startDt = new DateTime($startTime, $estTimeZone);
            $startDt->setTimezone($utcTimeZone);

            $endDt = new DateTime($endTime, $estTimeZone);
            $endDt->setTimezone($utcTimeZone);

            $startTime = $startDt->format(SQL_DATETIME);
            if ($endTime)
                $endTime = $endDt->format(SQL_DATETIME);

            if ($endTime && time_lte($endTime, $startTime)) {
                $form->set_error('Expiration time can not be before or equal to start time.', DBField::END_TIME);
                $isValid = false;
            }

            if (!$gameModUser) {
                $form->set_error('Email address does not exist.', DBField::EMAIL_ADDRESS);
                $isValid = false;
            } elseif ($isValid) {

                $gameModUserLicenses = $this->gamesModsLicensesManager->getActiveGameModLicensesByUserId(
                    $request,
                    $gameModUser->getPk(),
                    $gameMod->getPk(),
                    $this->updateChannel
                );

                foreach ($gameModUserLicenses as $gameModUserLicense) {
                    if (!$gameModUserLicense->getEndTime()) {
                        $request->user->sendFlashMessage('Found Expiring License');
                        if (time_gte($request->getCurrentSqlTime(), $gameModUserLicense->getStartTime())) {
                            $form->set_error("Overlapping license with no expiration already exists: ({$gameModUserLicense->getStartTime()} -> No Expiration Time)", DBField::EMAIL_ADDRESS);
                            $isValid = false;
                            break;
                        }
                    }
                }
            }

            if ($isValid) {
                $gameModUserLicense = $this->gamesModsLicensesManager->createNewGameModLicense(
                    $request,
                    $gameMod->getPk(),
                    $gameModUser->getPk(),
                    $this->updateChannel,
                    $startTime,
                    $endTime
                );

                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_ADD_GAME_MOD_LICENSE,
                    $gameMod->getPk(),
                    $gameModUserLicense->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                $request->user->sendFlashMessage('Added License');
                return $form->handleRenderJsonSuccessResponse($gameMod->getEditUrl("/{$this->updateChannel}/access-control"));
            } else {
                return $form->handleRenderJsonErrorResponse();
            }
        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Add User Access - {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Add User Access - {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $gameMod->getEditUrl(),
            TemplateVars::PAGE_IDENTIFIER => 'game-access-add-user',
            TemplateVars::FORM => $form,
        ];

        if ($request->is_ajax())
            return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/add-user-access.twig');
        else
            return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/add-user-access.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_remove_user_access(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {
        $usersManager = $request->managers->users();

        $gameModLicenseId = $request->getIdForEntity($this->url_key+1);
        if (!$gameModLicenseId)
            return $this->render_404($request, 'ID Not Found', $request->is_ajax());

        $gameModLicense = $this->gamesModsLicensesManager->getGameModLicenseById($request, $gameModLicenseId, $gameMod->getPk());

        if (!$gameModLicense)
            return $this->render_404($request, 'License Not Found', $request->is_ajax());

        $fields = [
            new HiddenField(DBField::GAME_MOD_LICENSE_ID, 'GameModLicense'),
        ];

        $defaults = [
            DBField::GAME_MOD_LICENSE_ID => $gameModLicenseId
        ];

        $form = new PostForm($fields, $request, $defaults);

        $gameUser = $usersManager->getUserById($request, $gameModLicense->getUserId());

        if ($form->is_valid()) {
            $gameModLicense->updateField(DBField::IS_ACTIVE, 0)->saveEntityToDb($request);

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_DELETE_GAME_MOD_LICENSE,
                $gameMod->getPk(),
                $gameModLicense->getPk(),
                $user->getUiLanguageId(),
                $user
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $activeOrganization,
                $activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            $request->user->sendFlashMessage('Removed User License');
            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());
        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Remove User Access - {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Remove User Access - {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $gameMod->getEditUrl(),
            TemplateVars::PAGE_IDENTIFIER => 'game-access-remove-user',
            TemplateVars::FORM => $form,
            TemplateVars::LICENSE => $gameModLicense,
            TemplateVars::EMAIL => $gameUser->getEmailAddress()
        ];

        if ($request->is_ajax())
            return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/remove-user-access.twig');
        else
            return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/remove-user-access.twig');
    }


    /*
     * * * * * * * * * *
     *
     *    CUSTOM ASSETS
     *
     * * * * * * * * * *
     */

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param $selectedGameModBuildId
     * @return HtmlResponse
     */
    public function handle_batch_upload_custom_game_mod_assets(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        $gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $selectedGameModBuildId, $gameMod->getPk());

        // Get custom game assets and creator user context for this game build
        $gameModActiveCustomAssets = $this->gamesModsActiveCustomAssetsManager->getGameModActiveCustomAssetLinksByGameModBuildIds(
            $request,
            $gameModBuild->getPk()
        );

        $customGameModAssetIds = unique_array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $gameModActiveCustomAssets);

        $customGameModAssets = $this->gamesAssetsManager->getCustomGameModBuildAssetsByCustomGameAssetIds($request, $gameMod->getPk(), $customGameModAssetIds);

        if ($customGameModAssets)
            $customGameModAssets = array_index($customGameModAssets, VField::CUSTOM_GAME_ASSET_ID);

        foreach ($gameModActiveCustomAssets as $gameModActiveCustomAsset) {
            if (array_key_exists($gameModActiveCustomAsset->getContextXGameAssetId(), $customGameModAssets))
                $gameModActiveCustomAsset->setGameAsset($customGameModAssets[$gameModActiveCustomAsset->getContextXGameAssetId()]);
            else
                $gameModActiveCustomAsset->updateField(VField::GAME_ASSET, []);
        }

        $fields = [];
        $form = new PostForm($fields, $request);

        $customGameModAssetsJsObjects = $gameModActiveCustomAssets ? DBManagerEntity::extractJsonDataArrays(array_index($gameModActiveCustomAssets, DBField::SLUG)) : [];

        $pageJsViewData = [
            TemplateVars::CUSTOM_GAME_ASSETS => $customGameModAssetsJsObjects,
            TemplateVars::UPLOAD_URL => $gameMod->getEditUrl("/{$gameModBuild->getUpdateChannel()}/upload-custom-game-mod-asset/"),
            TemplateVars::GAME_MOD_BUILD => $gameModBuild->getJSONData(true),
            TemplateVars::REDIRECT_URL => $gameMod->getEditUrl("/{$gameModBuild->getUpdateChannel()}/view-mod-build/{$gameModBuild->getPk()}?active_build_tab=custom-assets")
        ];

        $this->page_js_data->assign($pageJsViewData);

        $viewData = [
            TemplateVars::PAGE_TITLE => "Batch Upload Mod Assets - {$gameMod->getDisplayName()}",
            TemplateVars::PAGE_DESCRIPTION => "Batch Upload Mod Assets - {$gameMod->getDisplayName()}",
            TemplateVars::PAGE_IDENTIFIER => 'batch-upload-custom-mod-assets',
            TemplateVars::GAME_BUILD => $gameModBuild,
            TemplateVars::GAME_MOD_BUILD => $gameModBuild,
            TemplateVars::FORM => $form,
        ];

        return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/manage-build/batch-upload-custom-mod-assets.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param $selectedGameModBuildId
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_upload_custom_game_mod_asset(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        force_slash($request);

        $customGameAssetSlug = $request->getSlugForEntity($this->url_key+1);

        $gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $selectedGameModBuildId, $gameMod->getPk());

        $fields = [
            new FileField(VField::GAME_ASSET_FILE, 'Asset File', true),
        ];

        $defaults = [

        ];

        if ($customGameAssetSlug) {

            $fields[] = new HiddenField(DBField::SLUG, 'slug');
            $fields[] = new HiddenField(VField::REPLACE, 'replace');
            $fields[] = new HiddenField(DBField::IS_PUBLIC, 'is-public', 0, false);

            $defaults[DBField::SLUG] = $customGameAssetSlug;
            $defaults[VField::REPLACE] = 1;
        } else {

            $fields[] = new ExtendedSlugField(DBField::SLUG, 'Slug', 64, false, 'This is the pre-ordained identifier used to access this asset.');
            $fields[] = new BooleanField(VField::REPLACE, 'Replace existing slug file', false, 'When checked, replaces any existing slug with this file.');
            $fields[] = new BooleanField(DBField::IS_PUBLIC, 'Shared with game controller', false, 'If checked, this asset is accessible via non-secure public URL by game controllers.');

        }

        $form = new PostForm($fields, $request, $defaults);

        $form->assignViewData([
            TemplateVars::GAME_MOD => $gameMod
        ]);

        $form->setTemplateFile("account/mods/forms/new-custom-game-mod-asset.twig");

        if ($isValid = $form->is_valid()) {

            $slug = strtolower($form->getCleanedValue(DBField::SLUG));
            $replaceSlug = $form->getCleanedValue(VField::REPLACE, false);
            $isPublic = $form->getCleanedValue(DBField::IS_PUBLIC, 0);

            $customGameAssetFileId = $form->getCleanedValue(VField::GAME_ASSET_FILE)['upload_id_game_asset_file'];
            $customGameAssetFile = UploadsHelper::path_from_file_id($customGameAssetFileId);
            $customGameAssetFileInfo = UploadsHelper::get_file_info($customGameAssetFileId);
            $customGameAssetFileName = $customGameAssetFileInfo[DBField::FILENAME];

            if (!$slug) {
                $slug = strtolower(FilesToolkit::get_base_filename($customGameAssetFileName));
            }

            $gameModActiveCustomAsset = $this->gamesModsActiveCustomAssetsManager->getGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(
                $request,
                $gameModBuild->getPk(),
                $slug
            );

            // Check if
            if (!$replaceSlug && $gameModActiveCustomAsset) {
                if ($gameModActiveCustomAsset->is_active()) {
                    $isValid = false;
                    $form->set_error("Slug '{$slug}' is already in use, choose another slug or opt to replace the active file.", DBField::SLUG);
                }
            }

            if ($isValid) {
                $md5 = md5_file($customGameAssetFile);
                $fileSize = filesize($customGameAssetFile);

                $customGameModAsset = $this->gamesAssetsManager->handleCustomGameModBuildAssetUpload(
                    $request,
                    $customGameAssetFile,
                    $md5,
                    $customGameAssetFileName,
                    $gameMod->getGameId(),
                    $gameModBuild->getPk(),
                    null,
                    $this->updateChannel,
                    $slug,
                    $customGameAssetFileId
                );

                $gameModActiveCustomAsset = $this->gamesModsActiveCustomAssetsManager->createUpdateGameModActiveCustomAsset(
                    $request,
                    $gameMod->getPk(),
                    $this->updateChannel,
                    $selectedGameModBuildId,
                    $slug,
                    $customGameModAsset->getCustomGameAssetId(),
                    $isPublic
                );

                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_UPLOAD_CUSTOM_GAME_MOD_ASSET_FILE,
                    $gameModBuild->getPk(),
                    $customGameModAsset->getCustomGameAssetId(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                $link = "{$gameMod->getEditUrl()}/{$this->updateChannel}/view-custom-game-mod-asset/{$gameModActiveCustomAsset->getSlug()}?game_mod_build_id={$gameModBuild->getPk()}";
                $downloadLink = $gameMod->getEditUrl("/{$this->updateChannel}/download-custom-game-mod-asset-file/{$gameModActiveCustomAsset->getSlug()}/{$customGameModAsset->getCustomGameAssetId()}?game_mod_build_id={$gameModBuild->getPk()}");

                if ($request->settings()->is_prod()) {

                    Modules::load_helper(Helpers::SLACK);

                    $slackMessage = "@channel {$user->getEmailAddress()} uploaded a custom game mod asset file: {$slug}";

                    $slackAttachment = new SlackAttachment(
                        $user,
                        $gameModActiveCustomAsset->getSlug(),
                        $link,
                        "File: {$customGameModAsset->getFileName()}, Size: {$customGameModAsset->getFileSize()}",
                        new SlackActionButton('View', $link),
                        new SlackActionButton('Download', $downloadLink),
                        new SlackField('Environment', $request->host),
                        new SlackField('Game', $gameMod->getGame()->getDisplayName()),
                        new SlackField('Mod', $gameMod->getDisplayName()),
                        new SlackField('Request ID', $request->requestId)
                    );

                    SlackHelper::sendCard($slackMessage, $slackAttachment);
                }

                if ($customGameAssetSlug || (!$customGameAssetSlug && $replaceSlug))
                    $next = $link;
                else
                    $next = "{$gameMod->getEditUrl()}/{$this->updateChannel}/view-mod-build/{$gameModBuild->getPk()}?active_build_tab=custom-assets";

                $request->user->sendFlashMessage('Upload Success', MSG_SUCCESS);

                return $form->handleRenderJsonSuccessResponse($next);
            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form,
            TemplateVars::SLUG => $customGameAssetSlug,
            TemplateVars::GAME_MOD_BUILD => $gameModBuild,
        ];

        return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/manage-build/upload-custom-game-mod-asset.twig');

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param $selectedGameModBuildId
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_view_custom_game_mod_asset(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        if (!$customGameAssetSlug = $request->getSlugForEntity($this->url_key+1))
            return $this->render_404($request);

        if (!$gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $selectedGameModBuildId, $gameMod->getPk()))
            return $this->render_404($request, 'Game Mod Build Not Found');

        $gameModActiveCustomAsset = $this->gamesModsActiveCustomAssetsManager->getActiveGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(
            $request,
            $gameModBuild->getPk(),
            $customGameAssetSlug
        );

        if (!$gameModActiveCustomAsset)
            return $this->render_404($request);

        $gameAsset = [];

        if ($gameModActiveCustomAsset->getContextXGameAssetId()) {
            foreach ($gameModBuild->getCustomGameModBuildAssets() as $customGameAsset) {
                if ($customGameAsset->getCustomGameAssetId() == $gameModActiveCustomAsset->getContextXGameAssetId()) {
                    $gameAsset = $customGameAsset;
                    break;
                }
            }
        }

        $customGameAssetsHistory = $this->gamesAssetsManager->getCustomGameModBuildAssetsHistoryBySlug(
            $request,
            $gameModBuild->getPk(),
            $customGameAssetSlug
        );

        $fields = [
            new ExtendedSlugField(DBField::SLUG, 'Custom Game Asset Slug', 0, true, 'Note: changing this slug does not update dependencies in game. Use with caution!'),
            new BooleanField(DBField::IS_PUBLIC, 'Shared with game controller', false, 'If checked, this asset is accessible via non-secure public URL by game controllers.')
        ];

        $defaults = [
            DBField::SLUG => $gameModActiveCustomAsset->getSlug(),
            DBField::IS_PUBLIC => $gameModActiveCustomAsset->getIsPublic()
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->setTemplateFile("account/mods/forms/edit-custom-mod-asset.twig");
        $form->assignViewData([
            TemplateVars::GAME_MOD => $gameMod,
            TemplateVars::CUSTOM_GAME_ASSET_LINK => $gameModActiveCustomAsset,
            TemplateVars::GAME_MOD_BUILD => $gameModBuild,
            TemplateVars::UPDATE_CHANNEL => $this->updateChannel,
        ]);

        if ($isValid = $form->is_valid()) {

            $newSlug = strtolower($form->getCleanedValue(DBField::SLUG));
            $isPublic = $form->getCleanedValue(DBField::IS_PUBLIC, 0);

            if ($newSlug != $customGameAssetSlug) {
                $newGameActiveCustomAsset = $this->gamesModsActiveCustomAssetsManager->getActiveGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(
                    $request,
                    $gameModBuild->getPk(),
                    $newSlug
                );

                if ($newGameActiveCustomAsset) {
                    $form->set_error("Slug already in use.", DBField::SLUG);
                    $isValid = false;
                }

            }

            if ($isValid) {

                $link = $gameMod->getEditUrl("/{$this->updateChannel}/view-custom-game-mod-asset/{$newSlug}?game_mod_build_id={$gameModBuild->getPk()}");

                if ($isPublic != $gameModActiveCustomAsset->getIsPublic())
                    $gameModActiveCustomAsset->updateField(DBField::IS_PUBLIC, $isPublic)->saveEntityToDb($request);

                if ($newSlug != $customGameAssetSlug) {
                    $this->gamesModsActiveCustomAssetsManager->renameCustomGameModAssetSlug($request, $gameModActiveCustomAsset, $newSlug);
                    $request->user->sendFlashMessage('Updated Custom Game Asset Slug Successfully');

                    if (!$request->settings()->is_prod()) {

                        Modules::load_helper(Helpers::SLACK);

                        $slackMessage = "@channel {$user->getEmailAddress()} renamed a custom mod asset: {$customGameAssetSlug} -> {$newSlug}";

                        $slackAttachment = new SlackAttachment(
                            $user,
                            $newSlug,
                            $link,
                            null,
                            new SlackActionButton('View', $link),
                            new SlackField('Environment', $request->host),
                            new SlackField('Game', $gameMod->getGame()->getDisplayName()),
                            new SlackField('Mod', $gameMod->getDisplayName()),
                            new SlackField('Request ID', $request->requestId)
                        );

                        SlackHelper::sendCard($slackMessage, $slackAttachment);
                    }
                }

                return $form->handleRenderJsonSuccessResponse($link);

            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }


        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'view-custom-game-mod-asset',
            TemplateVars::GAME_ASSET => $gameAsset,
            TemplateVars::GAME_ASSETS => $customGameAssetsHistory,
            TemplateVars::CUSTOM_GAME_ASSET_LINK => $gameModActiveCustomAsset,
            TemplateVars::FORM => $form,
            TemplateVars::GAME_MOD_BUILD => $gameModBuild,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/manage-build/view-custom-game-mod-asset.twig');

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param $selectedGameModBuildId
     * @return GameAssetResponse|HtmlResponse
     */
    protected function handle_download_custom_game_mod_asset_file(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        $customGameAssetSlug = $request->getSlugForEntity($this->url_key + 1);

        if (!$customGameAssetSlug)
            return $this->render_404($request, 'Slug Not Found');

        if (!$gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $selectedGameModBuildId, $gameMod->getPk()))
            return $this->render_404($request, 'Mod Build Not Found');

        $activeCustomGameAsset = $this->gamesModsActiveCustomAssetsManager->getActiveGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(
            $request,
            $gameModBuild->getPk(),
            $customGameAssetSlug
        );

        if (!$activeCustomGameAsset)
            return $this->render_404($request, 'Custom Asset Not Found');

        $customGameAsset = $this->gamesAssetsManager->getCustomGameModBuildAssetByCustomGameAssetId(
            $request,
            $gameModBuild->getPk(),
            $activeCustomGameAsset->getContextXGameAssetId()
        );

        if (!$customGameAsset)
            return $this->render_404($request);

        try {
            $customGameAssetFile = $request->s3->readIntoMemory($customGameAsset->getBucket(), $customGameAsset->getBucketKey());

            return new GameAssetResponse($customGameAsset, $customGameAssetFile);
        } catch (Exception $e) {
            return $this->render_404($request);
        }
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param $selectedGameModBuildId
     * @return HtmlResponse|HttpResponseRedirect
     */
    protected function handle_set_custom_game_mod_asset_active(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        $customGameAssetSlug = $request->getSlugForEntity($this->url_key+1);
        $customGameAssetId = $request->getIdForEntity($this->url_key+2);

        if (!$customGameAssetSlug || !$customGameAssetId)
            return $this->render_404($request);

        if (!$gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $selectedGameModBuildId, $gameMod->getPk()))
            return $this->render_404($request, 'Mod Build Not Found');

        $activeCustomGameAsset = $this->gamesModsActiveCustomAssetsManager->getActiveGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(
            $request,
            $gameModBuild->getPk(),
            $customGameAssetSlug
        );

        if (!$activeCustomGameAsset)
            return $this->render_404($request);

        $customGameAsset = $this->gamesAssetsManager->getCustomGameModBuildAssetByCustomGameAssetId(
            $request,
            $gameModBuild->getPk(),
            $customGameAssetId
        );

        if (!$customGameAsset)
            return $this->render_404($request);

        if ($customGameAsset->getCustomGameAssetId() == $activeCustomGameAsset->getContextXGameAssetId())
            return $this->render_404($request);

        $activeCustomGameAsset->updateField(DBField::CONTEXT_X_GAME_ASSET_ID, $customGameAsset->getCustomGameAssetId())->saveEntityToDb($request);

        $activity = $this->activityManager->trackActivity(
            $request,
            ActivityTypesManager::ACTIVITY_TYPE_USER_SET_CUSTOM_GAME_MOD_ASSET_ACTIVE,
            $activeCustomGameAsset->getPk(),
            $customGameAsset->getCustomGameAssetId(),
            $user->getUiLanguageId(),
            $user
        );

        $this->organizationsActivityManager->trackOrganizationActivity(
            $request,
            $activity,
            $activeOrganization,
            $activeOrganization->getOrganizationUserByUserId($request->user->getId())
        );

        return $this->redirect($request->getRedirectBackUrl());
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param $selectedGameModBuildId
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_delete_custom_game_mod_asset(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        $customGameAssetSlug = $request->getSlugForEntity($this->url_key+1);

        if (!$customGameAssetSlug)
            return $this->render_404($request, 'Slug Not Found');

        if (!$gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $selectedGameModBuildId, $gameMod->getPk()))
            return $this->render_404($request, 'Mod Build Not Found');

        $activeCustomGameAsset = $this->gamesModsActiveCustomAssetsManager->getActiveGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(
            $request,
            $gameModBuild->getPk(),
            $customGameAssetSlug
        );

        if (!$activeCustomGameAsset)
            return $this->render_404($request, 'Custom Asset Not Found');

        $fields = [
            new HiddenField(DBField::SLUG, 'Slug')
        ];

        $defaults = [
            DBField::SLUG => $customGameAssetSlug
        ];

        $form = new PostForm($fields, $request, $defaults);

        if ($form->is_valid()) {

            $this->gamesModsActiveCustomAssetsManager->deleteGameModBuildActiveCustomAssetLink(
                $request,
                $gameModBuild->getPk(),
                $customGameAssetSlug
            );

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_DELETE_CUSTOM_GAME_MOD_ASSET,
                $gameModBuild->getPk(),
                $activeCustomGameAsset->getPk(),
                $user->getUiLanguageId(),
                $user
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $activeOrganization,
                $activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            $request->user->sendFlashMessage('Custom Game Mod Asset Deleted');

            return $form->handleRenderJsonSuccessResponse($gameMod->getEditUrl("/{$this->updateChannel}/view-mod-build/{$gameModBuild->getPk()}?active_build_tab=custom-assets"));

        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'delete-custom-game-mod-asset',
            TemplateVars::FORM => $form,
            TemplateVars::SLUG => $customGameAssetSlug,
            TemplateVars::GAME_MOD_BUILD => $gameModBuild,
        ];

        return $this->renderAjaxResponse($request, $viewData, "account/mods/manage-mod/manage-build/delete-custom-game-mod-asset.twig");
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param $selectedGameModBuildId
     * @return DownloadZipResponse|HtmlResponse
     */
    protected function handle_batch_download_custom_game_mod_asset_files(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        $gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $selectedGameModBuildId, $gameMod->getPk());

        // Get custom game assets and creator user context for this game build
        $gamesActiveCustomAssets = $this->gamesModsActiveCustomAssetsManager->getGameModActiveCustomAssetLinksByGameModBuildIds(
            $request,
            $gameModBuild->getPk()
        );

        if ($gamesActiveCustomAssets)
            $gamesActiveCustomAssets = array_index($gamesActiveCustomAssets, DBField::SLUG);

        $customGameAssetIds = unique_array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $gamesActiveCustomAssets);

        $customGameAssets = $this->gamesAssetsManager->getCustomGameModBuildAssetsByCustomGameAssetIds($request, $gameModBuild->getPk(), $customGameAssetIds);

        $buildVersion = str_replace('.', '-', $gameModBuild->getBuildVersion());

        $modSlug = slugify($gameMod->getDisplayName());

        $fileName = "{$modSlug}_{$this->updateChannel}-build-{$gameModBuild->getPk()}-v-{$buildVersion}_custom-mod-assets_{$request->getCurrentSqlTime('Y-m-d-H-i-s')}.zip";

        $zipArchive = new ZipArchive();
        $parentFolder = "{$request->settings()->getUploadDir()}/{$request->requestId}";
        $filePath = "{$parentFolder}/{$fileName}";

        if (!is_dir($parentFolder))
            mkdir($parentFolder, 0700, true);

        $zipArchive->open($filePath, ZipArchive::CREATE);

        $hasFileData = false;

        foreach ($customGameAssets as $customGameAsset) {
            try {

                $customGameAssetFileData = $request->s3->readIntoMemory($customGameAsset->getBucket(), $customGameAsset->getBucketKey());

                $sharedString = $gamesActiveCustomAssets[$customGameAsset->getSlug()]->is_public() ? '@shared' : '';

                $assetFileName = "{$customGameAsset->getSlug()}{$sharedString}.{$customGameAsset->getExtension()}";

                $zipArchive->addFromString($assetFileName, $customGameAssetFileData);

                $hasFileData = true;

            } catch (\Aws\Exception\AwsException $e) {
                continue;
            }
        }

        if ($hasFileData) {
            $zipArchive->close();

            if (is_file($filePath)) {
                $content = file_get_contents($filePath);

                FilesToolkit::clear_directory($parentFolder);
                if (is_dir($parentFolder))
                    rmdir($parentFolder);

                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_DOWNLOAD_GAME_MOD_BUILD_CUSTOM_ASSETS_ZIP,
                    $gameMod->getPk(),
                    $gameModBuild->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                return new DownloadZipResponse($content, $fileName);
            }

        }

        return $this->render_404($request, 'No Files Found');
    }



    /*
     * * * * * * * * * *
     *
     *    CUSTOM DATA
     *
     * * * * * * * * * *
     */

    protected function handle_upload_custom_game_mod_data(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        $customDataKey = $request->getSlugForEntity($this->url_key+1);

        $gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $selectedGameModBuildId, $gameMod->getPk());

        $fields = [
            new FileField(DBField::FILE, 'Spreadsheet File', true, ['xls', 'xlsx'])
        ];

        if ($customDataKey) {
            $fields[] = new HiddenField(DBField::KEY, 'Key', 32, true, 'This is the key used to programmatically access the data via API.');
        } else {
            $fields[] = new SlugField(DBField::KEY, 'Key', 32, true, 'This is the key used to programmatically access the data via API.');
        }

        $defaults = [
            DBField::KEY => $customDataKey
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->setTemplateFile('account/mods/forms/new-game-mod-data.twig');

        if ($isValid = $form->is_valid()) {

            $key = $form->getCleanedValue(DBField::KEY);
            $uploadId = $form->getCleanedValue(DBField::FILE)['upload_id_file'];

            $sourceFile = UploadsHelper::path_from_file_id($uploadId);

            if (!$customDataKey) {
                $gameModData = $this->gamesModsDataManager->getGameModDataByGameModBuildIdAndKey(
                    $request,
                    $gameModBuild->getPk(),
                    $key
                );

                if ($gameModData) {
                    $isValid = false;
                    $form->set_error('Key already exists.', DBField::KEY);
                }

            }


            if ($isValid) {
                $reader = new Xlsx();

                try {
                    $spreadSheet = $reader->load($sourceFile);
                    $gameModData = $this->gamesModsDataManager->processSpreadsheet(
                        $request,
                        $gameModBuild->getPk(),
                        $key,
                        $spreadSheet
                    );


                } catch (Exception $e) {
                    dump($e->getMessage());
                    dump($e->getLine());
                    dump($e->getTraceAsString());
                    $gameModData = [];
                }

                UploadsHelper::delete_upload($uploadId);

                if ($gameModData) {

                    $activity = $this->activityManager->trackActivity(
                        $request,
                        ActivityTypesManager::ACTIVITY_TYPE_USER_UPLOAD_CUSTOM_GAME_MOD_DATA_SPREADSHEET,
                        $gameMod->getPk(),
                        $gameModData->getPk(),
                        $user->getUiLanguageId(),
                        $user
                    );

                    $this->organizationsActivityManager->trackOrganizationActivity(
                        $request,
                        $activity,
                        $activeOrganization,
                        $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                    );

                    $request->user->sendFlashMessage('Spreadsheet uploaded and processed', MSG_SUCCESS);
                    $responseData = [
                        TemplateVars::GAME_MOD_DATA => $gameModData->getJSONData()
                    ];
                    return $form->handleRenderJsonSuccessResponse($gameMod->getEditUrl("/{$this->updateChannel}/view-custom-game-mod-data/{$key}?game_mod_build_id={$gameModBuild->getPk()}"), $responseData);
                } else {
                    $form->set_error('Spreadsheet failed to be processed.', DBField::FILE);
                    return $form->handleRenderJsonErrorResponse();
                }
            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Upload Custom Mod Data - {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Upload Custom Mod Data - {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $gameMod->getEditUrl("/{$this->updateChannel}/upload-custom-game-mod-data"),
            TemplateVars::PAGE_IDENTIFIER => 'game-mod-upload-custom-data',
            TemplateVars::FORM => $form,
            TemplateVars::SLUG => $customDataKey,
            TemplateVars::GAME_MOD_BUILD => $gameModBuild,
        ];

        return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/manage-build/upload-custom-game-mod-data.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param $selectedGameModBuildId
     * @return HtmlResponse
     */
    protected function handle_view_custom_game_mod_data(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        if (!$key = $request->getSlugForEntity($this->url_key+1))
            return $this->render_404($request);

        if (!$gameModBuild = $this->gamesModsBuildsManager->getGameModBuildById($request, $selectedGameModBuildId, $gameMod->getPk()))
            return $this->render_404($request);

        $gameModData = $this->gamesModsDataManager->getGameModDataByGameModBuildIdAndKey($request, $gameModBuild->getPk(), $key);

        if (!$gameModData)
            return $this->render_404($request);

        $forms = [];

        foreach ($gameModData->getGameModDataSheets() as $gameModDataSheet) {
            $sheetName = $gameModDataSheet->getName();

            $fields = [
                new CharField(DBField::NAME, 'Title', 32, true, 'This is the name of the sheet and is used by code to reference columns and rows.'),
                new SelectField(DBField::GAME_MOD_DATA_SHEET_COLUMN_ID, 'ID/Key Column', $gameModDataSheet->getGameModDataSheetColumns(), false, 'Unique row identifier column.'),
            ];

            $form = new PostForm($fields, $request, $gameModDataSheet);
            $form->setTemplateFile('account/mods/forms/form-edit-sheet.twig');

            $forms[$sheetName] = $form;
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Custom Mod Data - {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Custom Mod Data - {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $gameMod->getEditUrl(),
            TemplateVars::PAGE_IDENTIFIER => 'game-mod-view-custom-data',
            TemplateVars::GAME_MOD_DATA => $gameModData,
            TemplateVars::GAME_MOD_BUILD => $gameModBuild,
            TemplateVars::FORMS => $forms
        ];

        return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/mods/manage-mod/manage-build/view-custom-game-mod-data.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param $selectedGameModBuildId
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_update_custom_game_mod_data_sheet(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        $gamesModsDataManager = $request->managers->gamesModsData();
        $gamesModsDataSheetsManager = $request->managers->gamesModsDataSheets();

        if (!$gameDataId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Game Mod Data Id Not Found');

        if (!$gameModData = $gamesModsDataManager->getGameModDataById($request, $gameDataId, $selectedGameModBuildId))
            return $this->render_404($request, 'Game Mod Data Not Found');

        if (!$gameDataSheetId = $request->getIdForEntity($this->url_key+2))
            return $this->render_404($request, 'Game Mod Data Sheet Id Not Found');

        if (!$gameModDataSheet = $gameModData->getGameModDataSheetById($gameDataSheetId))
            return $this->render_404($request, 'Game Mod Data Sheet Not Found');

        $fields = [
            new CharField(DBField::NAME, 'Sheet Title', 32, true, 'This is the name of the sheet and is used by code to reference columns and rows.'),
            new SelectField(DBField::GAME_MOD_DATA_SHEET_COLUMN_ID, 'ID/Key Column', $gameModDataSheet->getGameModDataSheetColumns(), false, 'Unique row identifier column.'),
        ];

        $form = new PostForm($fields, $request, $gameModDataSheet);
        $form->setTemplateFile('account/games/forms/form-edit-sheet.twig');

        if ($form->is_valid()) {

            $gameModDataSheetColumnId = $gameModDataSheet->getGameModDataSheetColumnId();

            $gameModDataSheet->assignByForm($form)->saveEntityToDb($request);

            if ($gameModDataSheetColumnId != $form->getCleanedValue(DBField::GAME_MOD_DATA_SHEET_COLUMN_ID)) {
                $gamesModsDataSheetsManager->updateGameModDataSheetRowsIndexKeys($request, $gameModDataSheet);
            }

            $request->user->sendFlashMessage('Changes Saved', MSG_SUCCESS);

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());
        } else {
            return $form->handleRenderJsonErrorResponse();
        }
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @param $selectedGameModBuildId
     * @return DownloadXlsxResponse|HtmlResponse
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function handle_download_custom_game_mod_data_xls(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization, $selectedGameModBuildId)
    {
        $gamesModsDataManager = $request->managers->gamesModsData();

        if (!$gameModDataId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Game Data Id Not Found');

        if (!$gameModData = $gamesModsDataManager->getGameModDataById($request, $gameModDataId, $selectedGameModBuildId))
            return $this->render_404($request, 'Game Data Not Found');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("Custom Mod Data ({$gameModData->getKey()}) for {$gameMod->getDisplayName()}")
            ->setLastModifiedBy($gameModData->getModifiedBy())
            ->setCreator($user->getDisplayName());

        $spreadsheet->removeSheetByIndex(0);

        foreach ($gameModData->getGameModDataSheets() as $gameModDataSheet) {
            try {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($gameModDataSheet->getName());

                $data = [];

                $data[] = $gameModDataSheet->getGameModDataSheetColumnValues();

                foreach ($gameModDataSheet->getGameModDataSheetRows() as $gameDataSheetRow) {
                    $row = [];

                    foreach ($gameModDataSheet->getGameModDataSheetColumns() as $gameDataSheetColumn) {
                        $row[] = $gameDataSheetRow->getProcessedValueByKey($gameDataSheetColumn->getName());
                    }
                    $data[] = $row;
                }

                $sheet->fromArray($data, null, 'A1', true);


            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                return $this->render_404($request);
                //dump($e->getMessage());
                //dump($e->getTraceAsString());
            }
        }

        $writer = new XlsxWriter($spreadsheet);

        $gameModSlug = slugify($gameMod->getDisplayName());

        return new DownloadXlsxResponse($writer, "{$gameModSlug}_custom-data_{$gameModData->getKey()}-{$this->updateChannel}-{$gameModData->getPk()}_{$request->getCurrentSqlTime('Y-m-d-H-i-s')}.xlsx");

    }

}
