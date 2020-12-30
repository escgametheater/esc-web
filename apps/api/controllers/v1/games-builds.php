<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/8/18
 * Time: 10:17 AM
 */


class GamesBuildsApiV1Controller extends BaseApiV1Controller implements BaseApiControllerV1CRUDInterface
{

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;


    /** @var GamesBuildsManager $manager */
    protected $manager;
    /** @var GamesManager $gamesManager */
    protected $gamesManager;
    /** @var GamesBuildsManager $gamesBuildsManager */
    protected $gamesBuildsManager;
    /** @var GamesActiveBuildsManager$gamesActiveBuildsManager*/
    protected $gamesActiveBuildsManager;
    /** @var GamesControllersManager $gamesControllersManager */
    protected $gamesControllersManager;
    /** @var GamesControllersTypesManager $gamesControllersTypesManager */
    protected $gamesControllersTypesManager;
    /** @var GamesAssetsManager $gamesAssetsManager */
    protected $gamesAssetsManager;
    /** @var GamesActiveCustomAssetsManager $gamesActiveCustomAssetsManager */
    protected $gamesActiveCustomAssetsManager;
    /** @var GamesDataManager $gamesDataManager */
    protected $gamesDataManager;
    /** @var OrganizationsManager $organizationsManager*/
    protected $organizationsManager;
    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var OrganizationsActivityManager $organizationsActivityManager */
    protected $organizationsActivityManager;

    protected $pages = [
        // Index Page
        '' => 'handle_index',

        // CRUD Endpoints
        'get' => 'handle_get',
        'create' => 'handle_create',
        'update' => 'handle_update',
        'delete' => 'handle_delete',
        'list' => 'handle_list',
        'download-game-build-asset' => 'handle_download_game_build_asset',
        'upload-build' => 'handle_upload_build',
        'upload-controller' => 'handle_upload_game_controller',
        'upload-custom-game-asset' => 'handle_upload_custom_game_asset',
        'delete-custom-game-asset' => 'handle_delete_custom_game_asset',
    ];

    /**
     * @param Request $request
     */
    protected function pre_handle(Request $request)
    {
        $this->organizationsManager = $request->managers->organizations();
        $this->organizationsActivityManager = $request->managers->organizationsActivities();

        $this->gamesManager = $request->managers->games();
        $this->gamesBuildsManager = $request->managers->gamesBuilds();
        $this->gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();
        $this->gamesControllersManager = $request->managers->gamesControllers();
        $this->gamesControllersTypesManager = $request->managers->gamesControllersTypes();
        $this->gamesAssetsManager = $request->managers->gamesAssets();
        $this->gamesActiveCustomAssetsManager = $request->managers->gamesActiveCustomAssets();
        $this->gamesDataManager = $request->managers->gamesData();
        $this->activityManager = $request->managers->activity();
    }

    /**
     * @param Request $request
     * @return HttpResponse
     */
    public function handle_index(Request $request): HttpResponse
    {
        $request->user->sendFlashMessage('Index Not Implemented Yet');
        return $this->redirect(HOMEPAGE);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_get(Request $request): ApiV1Response
    {
        $this->form = $this->buildGetEntityForm($request);

        if ($this->form->is_valid()) {
            $gameBuild = $this->manager->getGameBuildById($request, $this->form->getPkValue());

            $this->setResults($gameBuild);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_create(Request $request): ApiV1Response
    {
        $games = $this->gamesManager->getGamesByOwnerId($request, $request->user->id);
        $updateChannels = $this->gamesManager->getUpdateChannelOptions();

        $fields = $this->manager->getFormFields($games, $updateChannels, false);
        $defaults = [];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($this->form->is_valid()) {

            $gameId = $this->form->getCleanedValue(DBField::GAME_ID);
            $updateChannel = $this->form->getCleanedValue(DBField::UPDATE_CHANNEL);
            $gameBuildVersion = $this->form->getCleanedValue(DBField::GAME_BUILD_VERSION);

            $result = $this->manager->createNewGameBuild($request, $gameId, $updateChannel, $gameBuildVersion);

            $this->setResults($result);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_update(Request $request): ApiV1Response
    {
        $getEntityForm = $this->buildGetEntityForm($request);

        if ($getEntityForm->is_valid()) {

            $updateChannels = $this->gamesManager->getUpdateChannelOptions();

            $gameBuild = $this->manager->getGameBuildById($request, $getEntityForm->getPkValue());

            $fields = $this->manager->getFormFields([], $updateChannels);
            $this->form = new ApiV1PostForm($fields, $request, $gameBuild);

            if ($this->form->is_valid()) {
                $gameBuild->assignByForm($this->form)->saveEntityToDb($request);
                $this->setResults($gameBuild);
            }

        } else {
            $this->form = $getEntityForm;
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_delete(Request $request): ApiV1Response
    {
        $getEntityForm = $this->buildGetEntityForm($request);

        if ($getEntityForm->is_valid()) {

            $gameBuild = $this->manager->getGameBuildById($request, $getEntityForm->getPkValue());

            $this->manager->deactivateEntity($request, $gameBuild);

            $this->setResults($gameBuild);

        } else {
            $this->form = $getEntityForm;
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_list(Request $request): ApiV1Response
    {
        $games = $this->gamesManager->getGamesByOwnerId($request, $request->user->id);
        $updateChannels = $this->gamesManager->getUpdateChannelOptions();

        $fields = [
            new SelectField(DBField::GAME_ID, 'GameId', $games),
            new SelectField(DBField::UPDATE_CHANNEL, 'Update Channel', $updateChannels)
        ];

        $this->form = new ApiV1PostForm($fields, $request);

        if ($this->form->is_valid()) {

            $gameId = $this->form->getCleanedValue(DBField::GAME_ID);
            $updateChannel = $this->form->getCleanedValue(DBField::UPDATE_CHANNEL);

            $gameBuilds = $this->manager->getGameBuildsByGameId($request, $gameId, true, $updateChannel);

            $this->setResults($gameBuilds);
        }

        return $this->renderApiV1Response($request);
    }

    /**
     *
     * @param Request $request
     * @return HttpResponse
     */
    public function handle_download_game_build_asset(Request $request): HttpResponse
    {
        if (!$updateChannel = $request->getSlugForEntity($this->url_key + 1))
            throw new Http404();
        if (!$gameBuildAssetId = $request->getIdForEntity($this->url_key + 2))
            throw new Http404();
        if (!$this->gamesManager->isValidUpdateChannel($updateChannel))
            throw new Http404();

        $gameAsset = $this->gamesAssetsManager->getSecureGameBuildAssetByGameBuildAssetId(
            $request,
            $gameBuildAssetId,
            $request->user->id,
            $updateChannel
        );

        if (!$gameAsset)
            throw new Http404();

        $user = $request->user->getEntity();
        $gameBuildId = $gameAsset->getGameBuildId();
        $gameId = $gameAsset->getGameId();
        $sessionId = $request->user->session->getSessionId();


        try {

            $cacheKey = $this->gamesAssetsManager->generateUserGameBuildDownloadCacheKey($gameId, $gameBuildId, $user->getPk());

            $trackedGameBuild = $request->cache[$cacheKey];

        } catch (CacheEntryNotFound $c) {

            if (!$this->activityManager->getActivityByContextAndSession($request, $gameId, $gameBuildId, $user->getPk(), $sessionId)) {

                $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_DOWNLOAD_GAME_BUILD,
                    $gameAsset->getGameId(),
                    $gameAsset->getGameBuildId(),
                    $user->getUiLanguageId(),
                    $user
                );
            }

            $trackedGameBuild = 1;

            $c->set($trackedGameBuild, ONE_HOUR);
        }

        $gameBuildAssetFile = $request->s3->readIntoMemory($gameAsset->getBucket(), $gameAsset->getBucketKey());

        return new GameAssetResponse($gameAsset, $gameBuildAssetFile);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     * @throws ESCFrameworkException
     * @throws ObjectNotFound
     */
    public function handle_upload_build(Request $request): ApiV1Response
    {
        $updateChannel = GamesManager::UPDATE_CHANNEL_DEV;

        /** @var GameEntity[] $games */
        $games = [];

        foreach ($this->gamesManager->getDevGamesByUserId($request, $request->user->id) as $game) {
            if (!array_key_exists($game->getPk(), $games))
                $games[$game->getPk()] = $game;
        }

        $slugs = [];
        $gamesBySlug = [];

        foreach ($games as $game) {
            $gamesBySlug[$game->getSlug()] = $game;

            $slugs[] = [
                DBField::ID => $game->getSlug(),
                DBField::DISPLAY_NAME => $game->getDisplayName()
            ];
        }

        $fields = [
            new SelectField(DBField::SLUG, 'Game Slug', $slugs, true),
            new FileField(DBField::FILE, 'Game Build ZIP File', true, ['zip']),
            new CharField('upload_id_file', 'File UUID (generated client-side)', 64),
            new BooleanField(DBField::IS_AGGREGATE_GAME, "Is Aggregate Game, false")
        ];

        $defaults = [ ];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($isValid = $this->form->is_valid()) {

            $newGameBuild = [];
            $gameSlug = $this->form->getCleanedValue(DBField::SLUG);
            $isAggregateGame = $this->form->getCleanedValue(DBField::IS_AGGREGATE_GAME, 0);
            /** @var GameEntity $game */
            $game = $gamesBySlug[$gameSlug];

            $previousGameBuild = $this->gamesBuildsManager->getMostRecentGameBuildByGameId($request, $game->getPk(), $updateChannel);

            if ($previousGameBuild) {
                $versionNumber = Strings::incrementBuildVersion($previousGameBuild->getGameBuildVersion());
                if (!$request->hasPostParam(DBField::IS_AGGREGATE_GAME))
                    $isAggregateGame = $previousGameBuild->getIsAggregateGame();
            } else {
                $versionNumber = '0.0.1';
            }

            $dbConnection = $request->db->get_connection();
            $dbConnection->begin();

            $uploadIdFile = $this->form->getCleanedValue('upload_id_file');
            $sourceFile = UploadsHelper::path_from_file_id($uploadIdFile);

            Modules::load_helper(Helpers::ZIP_UPLOAD);

            $destinationFolder = $this->gamesBuildsManager->generateDestinationFolder($request, $game);

            $zipUploadHelper = new ZipUploadHelper(
                $sourceFile,
                $destinationFolder,
                $game->getGameTypeId() == $request->managers->gamesTypes()::ID_CLOUD_GAME ? null : $this->gamesBuildsManager->getZipRootFileIdentifier()
            );

            if ($isValid && $zipUploadHelper->extract() && $zipUploadHelper->getRootFileFound() && $filePaths = $zipUploadHelper->getFilePaths()) {

                $newGameBuild = $this->gamesBuildsManager->createNewGameBuild(
                    $request,
                    $game->getPk(),
                    $updateChannel,
                    $versionNumber,
                    $zipUploadHelper->getArchiveVersionHash(),
                    0,
                    $isAggregateGame ? 1 : 0
                );

                foreach ($filePaths as $filePath) {

                    try {

                        $gameBuildAsset = $this->gamesAssetsManager->handleGameBuildAssetUpload(
                            $request,
                            $filePath,
                            md5_file($filePath),
                            $zipUploadHelper->getFileName($filePath),
                            $game->getPk(),
                            $newGameBuild->getPk(),
                            $zipUploadHelper->getFolderPath($filePath),
                            $updateChannel
                        );
                        $newGameBuild->setGameAsset($gameBuildAsset);

                    } catch (Exception $e) {
                        $this->form->set_error("An error has occurred, please try again later.", DBField::FILE);
                        $dbConnection->rollback();
                        $isValid = false;
                        break;
                    }
                }

            }

            FilesToolkit::clear_directory($destinationFolder);
            if (is_dir($destinationFolder))
                rmdir($destinationFolder);
            UploadsHelper::delete_upload($uploadIdFile);

            if ($isValid) {
                if (!$zipUploadHelper->getZipOpened()) {
                    $isValid = false;
                    $this->form->set_error('Zip archive failed to open.', DBField::FILE);
                } else {
                    if (!$zipUploadHelper->getProcessedFiles()) {
                        $isValid = false;
                        $this->form->set_error('No files found in zip.', DBField::FILE);
                    } else {
                        if (!$zipUploadHelper->getRootFileFound()) {
                            $isValid = false;
                            $this->form->set_error("{$this->gamesBuildsManager->getZipRootFileIdentifier()} not found in archive.", DBField::FILE);
                        }
                    }
                }
            }

            if ($isValid && $newGameBuild) {

                if ($previousGameBuild) {

                    $this->gamesControllersManager->cloneGameBuildControllers($request, $previousGameBuild, $newGameBuild);
                    $this->gamesActiveCustomAssetsManager->cloneCustomGameBuildAssets($request, $previousGameBuild, $newGameBuild);
                    $this->gamesDataManager->cloneGameBuildData($request, $previousGameBuild, $newGameBuild);
                }

                if ($newGameBuild->getCustomDataDefinition($request)->is_moddable()) {
                    $newGameBuild->updateField(DBField::CAN_MOD, 1)->saveEntityToDb($request);
                }

                $user = $request->user->getEntity();

                $activeOrganization = $this->organizationsManager->getOrganizationById($request, $game->getOwnerId(), true, true);

                $uploadActivity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_UPLOAD_GAME_BUILD,
                    $game->getPk(),
                    $newGameBuild->getPk(),
                    $request->user->getEntity()->getUiLanguageId(),
                    $request->user->getEntity()
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $uploadActivity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                $this->gamesActiveBuildsManager->createUpdateGameActiveBuild($request, $game->getPk(), $newGameBuild->getUpdateChannel(), $newGameBuild->getPk());

                $setActiveActivity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_SET_BUILD_ACTIVE,
                    $game->getPk(),
                    $newGameBuild->getPk(),
                    $request->user->getEntity()->getUiLanguageId(),
                    $request->user->getEntity()
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $setActiveActivity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );


                $dbConnection->commit();

                $this->setResults($newGameBuild);

                if (!$request->settings()->is_dev()) {

                    Modules::load_helper(Helpers::SLACK);

                    $slackMessage = "@channel {$user->getEmailAddress()} uploaded a new game build: {$newGameBuild->getGameBuildVersion()}";
                    $link = $game->getEditUrl("/{$updateChannel}/view-game-build/{$newGameBuild->getPk()}");

                    $slackAttachment = new SlackAttachment(
                        $user,
                        "{$game->getDisplayName()} - v{$newGameBuild->getGameBuildVersion()}",
                        $link,
                        null,
                        new SlackActionButton('View', $link),
                        new SlackField('Environment', $request->host),
                        new SlackField('Game', $game->getDisplayName()),
                        new SlackField('Request ID', $request->requestId)
                    );

                    SlackHelper::sendCard($slackMessage, $slackAttachment);
                }
            }
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     * @throws ESCFrameworkException
     */
    protected function handle_upload_game_controller(Request $request): ApiV1Response
    {
        $UPDATE_CHANNEL_DEV = GamesManager::UPDATE_CHANNEL_DEV;

        $gameControllerTypes = $this->gamesControllersTypesManager->getAllGameControllerTypes($request);

        /** @var GameEntity[] $games */
        $games = [];

        foreach ($this->gamesManager->getDevGamesByUserId($request, $request->user->id) as $game) {
            if (!array_key_exists($game->getPk(), $games))
                $games[$game->getPk()] = $game;
        }

        $slugs = [];
        $gamesBySlug = [];

        foreach ($games as $game) {
            $gamesBySlug[$game->getSlug()] = $game;

            $slugs[] = [
                DBField::ID => $game->getSlug(),
                DBField::DISPLAY_NAME => $game->getDisplayName()
            ];
        }

        $fields = [
            new SelectField(DBField::SLUG, 'Game Slug', $slugs, true),
            new SelectField(DBField::GAME_CONTROLLER_TYPE_ID, 'Game Controller Type', $gameControllerTypes, false),
            new CharField('upload_id_file', 'File UUID (generated client-side)', 64),
            new FileField(DBField::FILE, 'Game Controller ZIP File', true, ['zip']),
        ];

        $defaults = [
            DBField::GAME_CONTROLLER_TYPE_ID => GamesControllersTypesManager::ID_PLAYER
        ];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);


        if ($isValid = $this->form->is_valid()) {
            $gameSlug = $this->form->getCleanedValue(DBField::SLUG);
            /** @var GameEntity $game */
            $game = $gamesBySlug[$gameSlug];
            if($game == null) {
                $this->form->set_error('No game found', DBField::SLUG);
                return $this->renderApiV1Response($request);
            }
            $previousGameBuild = $this->gamesBuildsManager->getMostRecentGameBuildByGameId($request, $game->getPk(), $UPDATE_CHANNEL_DEV);

            if (!$previousGameBuild) {
                $this->form->set_error('No active game build', DBField::SLUG);
                return $this->renderApiV1Response($request);
            }
            $gameControllerTypeId = $this->form->getCleanedValue(DBField::GAME_CONTROLLER_TYPE_ID);
            if (!$gameControllerTypeId) {
                $gameControllerTypeId = GamesControllersTypesManager::ID_PLAYER;
            }

            // If game build is already published, clone it and use the new one
            if($previousGameBuild->is_published()) {

                $versionNumber = Strings::incrementBuildVersion($previousGameBuild->getGameBuildVersion());

                $newGameBuild = $this->gamesBuildsManager->createNewGameBuild(
                    $request,
                    $previousGameBuild->getGameId(),
                    $UPDATE_CHANNEL_DEV,
                    $versionNumber,
                    $previousGameBuild->getVersionHash(),
                    $previousGameBuild->getCanMod(),
                    $previousGameBuild->getIsAggregateGame()
                );

                // clone data
                $this->gamesDataManager->cloneGameBuildData($request, $previousGameBuild, $newGameBuild);
                // clone assets
                $this->gamesAssetsManager->cloneGameBuildAssets($request, $previousGameBuild, $newGameBuild);
                // clone custom assets
                $this->gamesActiveCustomAssetsManager->cloneCustomGameBuildAssets($request, $previousGameBuild, $newGameBuild);
                // clone controllers
                $this->gamesControllersManager->cloneGameBuildControllers($request, $previousGameBuild, $newGameBuild);
                // make the new game build active
                $this->gamesActiveBuildsManager->createUpdateGameActiveBuild($request, $game->getPk(), $newGameBuild->getUpdateChannel(), $newGameBuild->getPk());


                $previousGameBuild = $newGameBuild;
            }

            $activeGameControllers = $previousGameBuild->getGameControllers();
            $previousGameController = null;

            foreach ($activeGameControllers as $activeGameController) {
                if ($activeGameController->getGameControllerTypeId() == $gameControllerTypeId) {
                    $previousGameController = $activeGameController;
                    break;
                }
            }

            if ($previousGameController) {
                $previousVersion = explode('.', $previousGameController->getGameControllerVersion());
                $major = (int)$previousVersion[0];
                $minor = (int)$previousVersion[1];
                $gameBuildVersion = (int)$previousVersion[2];
                $build = (int)$previousVersion[3];
                $incrementedBuild = $build + 1;
                $gameControllerVersion = "{$major}.{$minor}.{$gameBuildVersion}.{$incrementedBuild}";
            } else {
                $gameControllerVersion = "{$previousGameBuild->getGameBuildVersion()}.1";
            }
            $gameBuildControllerArchiveFileId = $this->form->getCleanedValue(VField::GAME_BUILD_CONTROLLER_ARCHIVE)['upload_id_game_build_controller_archive'];

            if ($this->gamesControllersManager->checkGameControllerVersionExists(
                $request,
                $game->getPk(),
                $UPDATE_CHANNEL_DEV,
                $gameControllerTypeId,
                $gameControllerVersion,
                $previousGameBuild->getPk())) {
                $isValid = false;
                $this->form->set_error("Version '{$gameControllerVersion}' already exists on this update channel for build: {$previousGameBuild->getGameBuildVersion()}.", DBField::GAME_CONTROLLER_VERSION);
            }

            $uploadIdFile = $this->form->getCleanedValue('upload_id_file');
            $sourceFile = UploadsHelper::path_from_file_id($uploadIdFile);

            Modules::load_helper(Helpers::ZIP_UPLOAD);

            $destinationFolder = $this->gamesControllersManager->generateDestinationFolder($request, $game);

            $zipUploadHelper = new ZipUploadHelper($sourceFile, $destinationFolder, $this->gamesControllersManager->getZipRootFileIdentifier());

            $versionHash = '';

            if ($isValid && $zipUploadHelper->extract() && $zipUploadHelper->getRootFileFound() && $filePaths = $zipUploadHelper->getFilePaths()) {
                $dbConnection = $request->db->get_connection();
                $dbConnection->begin();

                $gameController = $this->gamesControllersManager->createNewGameController(
                    $request,
                    $game->getPk(),
                    $previousGameBuild->getPk(),
                    $UPDATE_CHANNEL_DEV,
                    $gameControllerTypeId,
                    $gameControllerVersion
                );

                $md5s = [];

                foreach ($filePaths as $filePath) {

                    try {
                        $gameControllerAsset = $this->gamesAssetsManager->handleGameControllerAssetUpload(
                            $request,
                            $filePath,
                            md5_file($filePath),
                            $zipUploadHelper->getFileName($filePath),
                            $game->getPk(),
                            $gameController->getPk(),
                            $zipUploadHelper->getFolderPath($filePath),
                            $UPDATE_CHANNEL_DEV
                        );
                        $gameControllerAssets[] = $gameControllerAsset;
                        $md5s[] = $gameControllerAsset->getMd5();

                    } catch (Exception $e) {
                        $this->form->set_error("An error has occurred, please try again later.", VField::GAME_BUILD_CONTROLLER_ARCHIVE);
                        $dbConnection->rollback();
                        throw $e;
                    }
                }

                if ($md5s) {
                    sort($md5s);
                    foreach ($md5s as $md5) {
                        $versionHash .= $md5;
                    }
                }
            }

            FilesToolkit::clear_directory($destinationFolder);
            if (is_dir($destinationFolder))
                rmdir($destinationFolder);
            UploadsHelper::delete_upload($gameBuildControllerArchiveFileId);

            if ($isValid) {
                if (!$zipUploadHelper->getZipOpened()) {
                    $isValid = false;
                    $this->form->set_error('Zip archive failed to open.', DBField::FILE);
                } else {
                    if (!$zipUploadHelper->getProcessedFiles()) {
                        $isValid = false;
                        $this->form->set_error('No files found in zip.', DBField::FILE);
                    } else {
                        $mainJsFile = GamesControllersManager::MAIN_JS_FILE_NAME;
                        $mainCssFile = GamesControllersManager::MAIN_CSS_FILE_NAME;

                        if (!$zipUploadHelper->hasFileName($mainJsFile)) {
                            $isValid = false;
                            $this->form->set_error("Primary JS file ({$mainJsFile}) not found in archive.", DBField::FILE);
                        }

                        if ($isValid && !$zipUploadHelper->hasFileName($mainCssFile)) {
                            $isValid = false;
                            $this->form->set_error("Primary CSS file ({$mainCssFile}) not found in archive.", DBField::FILE);
                        }

                        if ($isValid && !$zipUploadHelper->getRootFileFound()) {
                            $isValid = false;
                            $this->form->set_error("{$this->gamesBuildsManager->getZipRootFileIdentifier()} not found in archive.", DBField::FILE);
                        }
                    }
                }
            }

            if ($isValid) {

                $this->gamesControllersManager->deactivateOldGameControllerTypeForBuild(
                    $request,
                    $previousGameBuild->getPk(),
                    $gameController->getGameControllerTypeId(),
                    $gameController->getPk()
                );
                $user = $request->user->getEntity();

                $activeOrganization = $this->organizationsManager->getOrganizationById($request, $game->getOwnerId(), true, true);

                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_UPLOAD_GAME_CONTROLLER,
                    $previousGameBuild->getPk(),
                    $gameController->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );


                $dbConnection->commit();

                if ($versionHash)
                    $gameController->updateField(DBField::VERSION_HASH, sha1($versionHash))->saveEntityToDb($request);


                if (!$request->settings()->is_dev()) {

                    $link = $game->getEditUrl("/{$UPDATE_CHANNEL_DEV}/view-game-build/{$previousGameBuild->getPk()}?active_build_tab=controllers");

                    Modules::load_helper(Helpers::SLACK);

                    $slackMessage = "@channel {$user->getEmailAddress()} uploaded a new controller build ({$gameController->getGameControllerVersion()}) for build: {$previousGameBuild->getGameBuildVersion()}";

                    $slackAttachment = new SlackAttachment(
                        $user,
                        "{$gameController->getDisplayName()} - v{$gameController->getGameControllerVersion()}",
                        $link,
                        null,
                        new SlackActionButton('View', $link),
                        new SlackField('Environment', $request->host),
                        new SlackField('Game', $game->getDisplayName()),
                        new SlackField('Request ID', $request->requestId)
                    );

                    SlackHelper::sendCard($slackMessage, $slackAttachment);
                }
                $this->setResults($gameController);
            }
        }

        return $this->renderApiV1Response($request);
    }


    /**
     *
     * @param Request $request
     * @return ApiV1Response
     * @throws ESCFrameworkException
     * @throws Exception
     */
    protected function handle_upload_custom_game_asset(Request $request) : ApiV1Response
    {
        $UPDATE_CHANNEL_DEV = GamesManager::UPDATE_CHANNEL_DEV;

        $defaults = [
        ];

        /** @var GameEntity[] $games */
        $games = [];

        foreach ($this->gamesManager->getDevGamesByUserId($request, $request->user->id) as $game) {
            if (!array_key_exists($game->getPk(), $games))
                $games[$game->getPk()] = $game;
        }

        $slugs = [];
        $gamesBySlug = [];

        foreach ($games as $game) {
            $gamesBySlug[$game->getSlug()] = $game;

            $slugs[] = [
                DBField::ID => $game->getSlug(),
                DBField::DISPLAY_NAME => $game->getDisplayName()
            ];
        }


        $fields = [
            new SelectField(VField::GAME_SLUG, 'Game Slug', $slugs, true),
            new ExtendedSlugField(DBField::SLUG, 'Slug', 64, true, 'This is the pre-ordained identifier used to access this asset.'),
            new BooleanField(VField::REPLACE, 'Replace existing slug file', false, 'When checked, replaces any existing slug with this file.'),
            new BooleanField(DBField::IS_PUBLIC, 'Shared with game controller', false, 'If checked, this asset is accessible via non-secure public URL by game controllers.'),
            new CharField('upload_id_file', 'File UUID (generated client-side)', 64),
            new FileField(DBField::FILE, 'Custom Asset File', true),
        ];


        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($isValid = $this->form->is_valid()) {

            $slug = strtolower($this->form->getCleanedValue(DBField::SLUG));
            $replaceSlug = $this->form->getCleanedValue(VField::REPLACE, true);
            $isPublic = $this->form->getCleanedValue(DBField::IS_PUBLIC, 0);

            $gameSlug = $this->form->getCleanedValue(VField::GAME_SLUG);
            /** @var GameEntity $game */
            $game = $gamesBySlug[$gameSlug];
            if($game == null) {
                $this->form->set_error('No game found', DBField::SLUG);
                return $this->renderApiV1Response($request);
            }
            $gameBuild = $this->gamesBuildsManager->getMostRecentGameBuildByGameId($request, $game->getPk(), $UPDATE_CHANNEL_DEV);
            if ($gameBuild == null) {
                $this->form->set_error('No active game build', DBField::SLUG);
                return $this->renderApiV1Response($request);
            }

            // If game build is already published, clone it and use the new one
            if($gameBuild->is_published()) {

                $versionNumber = Strings::incrementBuildVersion($gameBuild->getGameBuildVersion());

                $newGameBuild = $this->gamesBuildsManager->createNewGameBuild(
                    $request,
                    $gameBuild->getGameId(),
                    $UPDATE_CHANNEL_DEV,
                    $versionNumber,
                    $gameBuild->getVersionHash(),
                    $gameBuild->getCanMod(),
                    $gameBuild->getIsAggregateGame()
                );

                // clone data
                $this->gamesDataManager->cloneGameBuildData($request, $gameBuild, $newGameBuild);
                // clone assets
                $this->gamesAssetsManager->cloneGameBuildAssets($request, $gameBuild, $newGameBuild);
                // clone custom assets
                $this->gamesActiveCustomAssetsManager->cloneCustomGameBuildAssets($request, $gameBuild, $newGameBuild);
                // clone controllers
                $this->gamesControllersManager->cloneGameBuildControllers($request, $gameBuild, $newGameBuild);
                // make the new game build active
                $this->gamesActiveBuildsManager->createUpdateGameActiveBuild($request, $game->getPk(), $newGameBuild->getUpdateChannel(), $newGameBuild->getPk());


                $gameBuild = $newGameBuild;
            }

            $customGameAssetFileId = $this->form->getCleanedValue('upload_id_file');
            $customGameAssetFile = UploadsHelper::path_from_file_id($customGameAssetFileId);
            $customGameAssetFileInfo = UploadsHelper::get_file_info($customGameAssetFileId);
            $customGameAssetFileName = $customGameAssetFileInfo[DBField::FILENAME];

            if (!$slug) {
                $slug = strtolower(FilesToolkit::get_base_filename($customGameAssetFileName));
            }

            $gameActiveCustomAsset = $this->gamesActiveCustomAssetsManager->getGameActiveCustomAssetLinkByGameIdAndSlug(
                $request,
                $game->getPk(),
                $UPDATE_CHANNEL_DEV,
                $slug,
                $gameBuild->getPk()
            );

            // Check if
            if (!$replaceSlug && $gameActiveCustomAsset) {
                if ($gameActiveCustomAsset->is_active()) {
                    $isValid = false;
                    $this->form->set_error("Slug '{$slug}' is already in use, choose another slug or opt to replace the active file.", DBField::SLUG);
                }
            }

            if ($isValid) {
                $md5 = md5_file($customGameAssetFile);
                $fileSize = filesize($customGameAssetFile);

                $customGameAsset = $this->gamesAssetsManager->handleCustomGameAssetUpload(
                    $request,
                    $customGameAssetFile,
                    $md5,
                    $customGameAssetFileName,
                    $game->getPk(),
                    null,
                    $UPDATE_CHANNEL_DEV,
                    $slug,
                    $customGameAssetFileId
                );

                $user = $request->user->getEntity();

                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_UPLOAD_CUSTOM_GAME_ASSET_FILE,
                    $game->getPk(),
                    $customGameAsset->getCustomGameAssetId(),
                    $user->getUiLanguageId(),
                    $user
                );

                $activeOrganization = $this->organizationsManager->getOrganizationById($request, $game->getOwnerId(), true, true);
                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );


                $gameActiveCustomAsset = $this->gamesActiveCustomAssetsManager->createUpdateGameActiveCustomAsset(
                    $request,
                    $game->getPk(),
                    $UPDATE_CHANNEL_DEV,
                    $gameBuild->getPk(),
                    $slug,
                    $customGameAsset->getCustomGameAssetId(),
                    $isPublic
                );

                if (!$request->settings()->is_dev()) {

                    $link = "{$game->getEditUrl()}/{$UPDATE_CHANNEL_DEV}/view-custom-game-asset/{$gameActiveCustomAsset->getSlug()}?game_build_id={$gameBuild->getPk()}";

                    Modules::load_helper(Helpers::SLACK);

                    $slackMessage = "@channel {$user->getEmailAddress()} uploaded a custom game asset file: {$slug}";

                    $slackAttachment = new SlackAttachment(
                        $user,
                        $gameActiveCustomAsset->getSlug(),
                        $link,
                        null,
                        new SlackActionButton('View', $link),
                        new SlackField('Environment', $request->host),
                        new SlackField('Game', $game->getDisplayName()),
                        new SlackField('Request ID', $request->requestId)
                    );

                    SlackHelper::sendCard($slackMessage, $slackAttachment);
                }

                // Don't forget to set the $gameActiveCustomAsset VField
                $customGameAsset->setGameActiveCustomAsset($gameActiveCustomAsset);
                $this->setResults($customGameAsset);
            }
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return HtmlResponse|JSONResponse
     * @throws ObjectNotFound
     */
    protected function handle_delete_custom_game_asset(Request $request)
    {
        $UPDATE_CHANNEL_DEV = GamesManager::UPDATE_CHANNEL_DEV;

        $defaults = [
        ];

        /** @var GameEntity[] $games */
        $games = [];

        foreach ($this->gamesManager->getDevGamesByUserId($request, $request->user->id) as $game) {
            if (!array_key_exists($game->getPk(), $games))
                $games[$game->getPk()] = $game;
        }

        $slugs = [];
        $gamesBySlug = [];

        foreach ($games as $game) {
            $gamesBySlug[$game->getSlug()] = $game;

            $slugs[] = [
                DBField::ID => $game->getSlug(),
                DBField::DISPLAY_NAME => $game->getDisplayName()
            ];
        }

        $fields = [
            new SelectField(VField::GAME_SLUG, 'Game Slug', $slugs, true),
            new ExtendedSlugField(DBField::SLUG, 'Slug', 64, true, 'This is the pre-ordained identifier used to access this asset.'),
        ];


        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($isValid = $this->form->is_valid()) {
            $customGameAssetSlug = $this->form->getCleanedValue(DBField::SLUG);

            $gameSlug = $this->form->getCleanedValue(VField::GAME_SLUG);
            /** @var GameEntity $game */
            $game = $gamesBySlug[$gameSlug];
            if($game == null) {
                $this->form->set_error('No game found', DBField::SLUG);
                return $this->renderApiV1Response($request);
            }
            $gameBuild = $this->gamesBuildsManager->getMostRecentGameBuildByGameId($request, $game->getPk(), $UPDATE_CHANNEL_DEV);
            if ($gameBuild == null) {
                $this->form->set_error('No active game build', DBField::SLUG);
                return $this->renderApiV1Response($request);
            }


            $activeCustomGameAsset = $this->gamesActiveCustomAssetsManager->getActiveGameActiveCustomAssetLinkByGameIdAndSlug(
                $request,
                $game->getPk(),
                $UPDATE_CHANNEL_DEV,
                $customGameAssetSlug,
                $gameBuild->getPk()
            );


            $this->gamesActiveCustomAssetsManager->deleteGameActiveCustomAssetLink(
                $request,
                $game->getPk(),
                $UPDATE_CHANNEL_DEV,
                $gameBuild->getPk(),
                $customGameAssetSlug
            );

            $user = $request->user->getEntity();

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_DELETE_CUSTOM_GAME_ASSET,
                $game->getPk(),
                $activeCustomGameAsset->getPk(),
                $user->getUiLanguageId(),
                $user
            );
            $activeOrganization = $this->organizationsManager->getOrganizationById($request, $game->getOwnerId(), true, true);
            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $activeOrganization,
                $activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $activeOrganization,
                $activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

        }

        return $this->renderApiV1Response($request);

    }

}