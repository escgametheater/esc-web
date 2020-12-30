<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 8/1/18
 * Time: 6:27 PM
 */

class ManageGameController extends BaseContent
{
    protected $activeUpdateChannel = GamesManager::UPDATE_CHANNEL_LIVE;

    /** @var GameEntity $game */
    protected $game;
    /** @var OrganizationEntity[] $organizations */
    protected $organizations = [];

    /** @var array|OrganizationEntity  */
    protected $activeOrganization = [];

    /** @var GamesManager $gamesManager */
    protected $gamesManager;
    /** @var GamesBuildsManager $gamesBuildsManager */
    protected $gamesBuildsManager;
    /** @var GamesActiveBuildsManager $gamesActiveBuildsManager */
    protected $gamesActiveBuildsManager;
    /** @var GamesAssetsManager */
    protected $gamesAssetsManager;
    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var OrganizationsActivityManager $organizationsActivityManager */
    protected $organizationsActivityManager;
    /** @var GamesEnginesManager $gamesEnginesManager */
    protected $gamesEnginesManager;
    /** @var GamesTypesManager $gamesTypesManager */
    protected $gamesTypesManager;
    /** @var GamesCategoriesManager $gamesCategoriesManager */
    protected $gamesCategoriesManager;

    /** @var ImagesManager $imagesManager */
    protected $imagesManager;
    /** @var ImagesTypesManager $imagesTypesManager */
    protected $imagesTypesManager;

    /**
     * @var array $pages
     */
    protected $pages = [

        // Index
        '' => 'handle_manage_game',

        // Update Modes
        GamesManager::UPDATE_CHANNEL_LIVE => 'handle_update_channel',
        GamesManager::UPDATE_CHANNEL_DEV => 'handle_update_channel',

        // Images
        'upload-profile-image' => 'handle_upload_profile_image',

    ];


    /**
     * ManageGameController constructor.
     * @param null $template_factory
     * @param GameEntity $game
     * @param array $organizations
     */
    public function __construct($template_factory = null, GameEntity $game, $organizations = [])
    {
        parent::__construct($template_factory);

        $this->game = $game;
        $this->organizations = $organizations;

        if ($this->game->getOwnerTypeId() == EntityType::ORGANIZATION) {
            if (!array_key_exists($this->game->getOwnerId(), $this->organizations)) {
                $this->organizations[$this->game->getOwnerId()] = $this->game->getOrganization();
            }
        }

    }

    /**
     * @return string
     */
    protected function generateGameActiveUpdateChannelSessionKey()
    {
        return "{$this->game->getSlug()}_game-update-channel";
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
        if (!$request->user->is_authenticated()) {
            return $this->redirectToLogin($request);
        } else {
            $user = $request->user->getEntity();
        }

        $this->url_key = $url_key;

        $this->activeUpdateChannel = $request->user->session->safeGet($this->generateGameActiveUpdateChannelSessionKey(), $this->activeUpdateChannel);

        $func = $this->resolve($request, $url_key, $pages, $render_default, $root);

        if ($func === null)
            throw new Http404();

        $this->gamesManager = $request->managers->games();
        $this->gamesBuildsManager = $request->managers->gamesBuilds();
        $this->gamesAssetsManager = $request->managers->gamesAssets();
        $this->gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();
        $this->activityManager = $request->managers->activity();
        $this->organizationsActivityManager = $request->managers->organizationsActivities();
        $this->gamesEnginesManager = $request->managers->gamesEngines();
        $this->gamesCategoriesManager = $request->managers->gamesCategories();
        $this->gamesTypesManager = $request->managers->gamesTypes();

        $this->imagesManager = $request->managers->images();
        $this->imagesTypesManager = $request->managers->imagesTypes();

        $activeOrganization = [];

        if ($this->game->getOwnerTypeId() == EntityType::ORGANIZATION) {
            if (array_key_exists($this->game->getOwnerId(), $this->organizations)) {
                $activeOrganization = $this->organizations[$this->game->getOwnerId()];
            }
        }

        $this->activeOrganization = $activeOrganization;

        return $this->$func($request, $user, $this->game, $request->getSlugForEntity($this->url_key));
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @param $updateChannel
     * @return HttpResponse
     */
    protected function handle_update_channel(Request $request, UserEntity $user, GameEntity $game, $updateChannel)
    {
        require "manage-game-update-channel.php";

        $gameActiveBuildVersionSummary = $this->gamesActiveBuildsManager->getGameActiveBuildVersionSummaryByUpdateChannel(
            $request,
            $game->getPk(),
            $updateChannel
        );

        $session = $request->user->session;

        $session->setIfNew($this->generateGameActiveUpdateChannelSessionKey(), $updateChannel);

        if ($session->is_modified())
            $session->save_session();

        $controller = new ManageGameUpdateChannelController(
            $this->templateFactory,
            $this->activeOrganization,
            $this->organizations,
            $game,
            $user,
            $updateChannel,
            $gameActiveBuildVersionSummary
        );

        return $controller->render($request, $this->url_key+1);
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_manage_game(Request $request, UserEntity $user, GameEntity $game)
    {
        $gameCategories = $this->gamesCategoriesManager->getAllActiveGameCategories($request);
        $gameEngines = $this->gamesEnginesManager->getAllActiveGameEngines($request);
        $gameTypes = $this->gamesTypesManager->getAllActiveGameTypes($request);

        $fields = [
            new CharField(DBField::DISPLAY_NAME, 'Game Title', 100, true, 'This is the display title of the game that appears in the host application.', 'Game Title'),
            new TextField(DBField::DESCRIPTION, 'Description', 1000, false, 'Describe this game (max 1000 characters).'),
            new SlugField(DBField::SLUG, 'Slug', 50, true, 'The slug is used as part of the public url for the game, e.g. https://www.esc.games/g/<game-slug>'),
            new SelectField(DBField::GAME_TYPE_ID, 'Type', $gameTypes, true, 'What type of project is this?'),
            new SelectField(DBField::GAME_ENGINE_ID, 'Game Engine', $gameEngines, true, 'Which game engine / SDK was used to develop this game?'),
            new SelectField(DBField::GAME_CATEGORY_ID, 'Category/Genre', $gameCategories, true, 'Choose the genre that best describes your game.'),
            new BooleanField(DBField::IS_DOWNLOADABLE, 'Is Downloadable', false, 'Un-checking this setting hides the game and prevents downloads from all users in the host application.'),
            new BooleanField(DBField::IS_WAN_ENABLED, 'Is WAN Enabled', false, 'Checking this setting makes it possible for players to connect via non WIFI connections.'),
//            new BooleanField(DBField::IS_AGGREGATE_GAME, 'Is Aggregate Game', false, 'This setting is required for games that support more than 1000 player connections.'),
        ];

        if ($request->user->permissions->has(RightsManager::RIGHT_GAMES, Rights::MODERATE)) {
            $fields[] = new BooleanField(DBField::CAN_MOD, 'Free to Mod', false, 'If checked, any team can create mods for this game at their own leisure.');
        }

        $form = new PostForm($fields, $request, $game);

        $this->gamesAssetsManager->getActivePlayableCustomGameAssetsByGameIds($request, [1,2,3,4,5], 1);

        $formViewData = [
            TemplateVars::GAME => $game
        ];

        $form->assignViewData($formViewData)->setTemplateFile('account/games/forms/edit-game.twig');

        if ($isValid = $form->is_valid()) {
            $displayName = $form->getCleanedValue(DBField::DISPLAY_NAME);
            $slug = $form->getCleanedValue(DBField::SLUG);
            $description = $form->getCleanedValue(DBField::DESCRIPTION);
            $isDownloadable = $form->getCleanedValue(DBField::IS_DOWNLOADABLE, 0);
            $isWanEnabled = $form->getCleanedValue(DBField::IS_WAN_ENABLED, 0);
            $gameCategoryId = $form->getCleanedValue(DBField::GAME_CATEGORY_ID, 1);
            $gameEngineId = $form->getCleanedValue(DBField::GAME_ENGINE_ID, GamesEnginesManager::ID_UNITY);
            $gameTypeId = $form->getCleanedValue(DBField::GAME_TYPE_ID);

            if ($request->user->permissions->has(RightsManager::RIGHT_GAMES, Rights::MODERATE)) {
                $canMod = $form->getCleanedValue(DBField::CAN_MOD, 0);
            } else {
                $canMod = $game->getCanMod();
            }

            if ($isDownloadable)
                $isDownloadable = 1;
            else
                $isDownloadable = 0;

            if ($isWanEnabled)
                $isWanEnabled = 1;
            else
                $isWanEnabled = 0;

            if ($slug != $game->getSlug()) {

                if ($this->gamesManager->checkSlugExists($slug)) {
                    $form->set_error('Slug Already Exists, Choose Another', DBField::SLUG);
                    $isValid = false;
                }
            }

            if ($isValid) {
                $updatedData = [
                    DBField::DISPLAY_NAME => $displayName,
                    DBField::SLUG => $slug,
                    DBField::DESCRIPTION => $description,
                    DBField::IS_WAN_ENABLED => $isWanEnabled,
                    DBField::IS_DOWNLOADABLE => $isDownloadable,
                    DBField::GAME_CATEGORY_ID => $gameCategoryId,
                    DBField::GAME_ENGINE_ID => $gameEngineId,
                    DBField::GAME_TYPE_ID => $gameTypeId,
                    DBField::CAN_MOD => $canMod,
                ];

                $game->assign($updatedData)->saveEntityToDb($request);

                $this->gamesManager->bustCache($game->getPk());

                if ($game->needs_save())
                    $request->user->sendFlashMessage('Game Data Saved', MSG_SUCCESS);
                else
                    $request->user->sendFlashMessage('Nothing to save', MSG_INFO);

                return $form->handleRenderJsonSuccessResponse($game->getEditUrl());
            } else {
                return $form->handleRenderJsonErrorResponse();
            }
        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }


        $viewData = [
            TemplateVars::PAGE_TITLE => "Manage {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Manage {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $game->getEditUrl(),
            TemplateVars::PAGE_IDENTIFIER => 'game-settings',
            TemplateVars::PROFILE_USER => $user,
            TemplateVars::GAME => $game,
            TemplateVars::FORM => $form,
            TemplateVars::USE_CROPPIE => true,
            TemplateVars::UPDATE_CHANNEL => $this->activeUpdateChannel,
            TemplateVars::UPDATE_CHANNELS => $this->gamesManager->getUpdateChannelOptions(),
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::ACTIVE_ORGANIZATION => $this->activeOrganization,
        ];

        return $this->renderPageResponse($request, $viewData, 'account/games/manage-game.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_upload_profile_image(Request $request, UserEntity $user, GameEntity $game)
    {
        $imageType = $this->imagesTypesManager->getImageTypeById($request, ImagesTypesManager::ID_GAME_AVATAR);

        $form = new ImageUploadForm($request, 'fourThree', 1600);

        if ($form->is_valid()) {

            // Get the file hash from the form object.
            $uploadId = $request->post[$form->getFormFieldUploadId()];

            // Try to get DB record for uploaded file.
            $info = UploadsHelper::get_file_info($uploadId);

            // If we founds a DB record and validated that the file_id exists, we should prepare to process it.
            $sourceFile = UploadsHelper::path_from_file_id($uploadId);
            $fileName = $info[DBField::FILENAME];


            if ($imageAsset = $form->handleImageUpload($request, $uploadId, $sourceFile)) {

                $oldAvatarImage = $this->imagesManager->getActiveGameAvatarImageByGameId($request, $game->getPk());

                if ($oldAvatarImage) {
                    $oldAvatarImage->updateField(DBField::IS_ACTIVE, 0)->saveEntityToDb($request);
                }

                $image = $this->imagesManager->getImageByAssetAndContext(
                    $request,
                    $imageType->getPk(),
                    $game->getPk(),
                    $imageAsset->getPk()
                );

                if (!$image) {
                    $image = $this->imagesManager->createNewImage(
                        $request,
                        $imageType->getPk(),
                        $game->getPk(),
                        $imageAsset->getPk(),
                        $fileName
                    );
                } else {
                    if (!$image->is_active())
                        $image->updateField(DBField::IS_ACTIVE, 1)->saveEntityToDb($request);
                }

                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_GAME_PROFILE_IMAGE_UPLOAD,
                    $game->getPk(),
                    $image->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $this->activeOrganization,
                    $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
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
            TemplateVars::GAME => $game,
            TemplateVars::UPDATE_CHANNEL => $this->activeUpdateChannel
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/games/manage-game/upload-profile-image.twig');
    }


}