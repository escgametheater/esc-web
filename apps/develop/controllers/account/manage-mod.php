<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/25/19
 * Time: 2:15 PM
 */

class ManageGameModController extends BaseContent
{
    /** @var GameModEntity */
    protected $gameMod;
    /** @var GameEntity */
    protected $game;
    /** @var OrganizationEntity $activeOrganization */
    protected $activeOrganization = [];

    /** @var OrganizationEntity[] */
    protected $organizations = [];

    protected $updateChannels = [];

    /** @var GamesManager */
    protected $gamesManager;
    /** @var GamesDataManager */
    protected $gamesDataManager;
    /** @var GamesModsManager */
    protected $gamesModsManager;
    /** @var GamesModsBuildsManager */
    protected $gamesModsBuildsManager;
    /** @var GamesModsActiveBuildsManager */
    protected $gamesModsActiveBuildsManager;
    /** @var GamesBuildsManager */
    protected $gamesBuildsManager;
    /** @var ActivityManager */
    protected $activityManager;
    /** @var OrganizationsActivityManager */
    protected $organizationsActivityManager;
    /** @var GamesModsActiveCustomAssetsManager */
    protected $gamesModsActiveCustomAssetsManager;
    /** @var GamesAssetsManager */
    protected $gamesAssetsManager;
    /** @var GamesModsDataManager */
    protected $gamesModsDataManager;
    /** @var GamesModsDataSheetsManager */
    protected $gamesModsDataSheetsManager;
    /** @var GamesModsDataSheetsRowsManager */
    protected $gamesModsDataSheetsRowsManager;
    /** @var GamesModsDataSheetsColumnsManager */
    protected $gamesModsDataSheetsColumnsManager;


    protected $pages = [

        // Index
        '' => 'handle_manage_game_mod',
        'customizer' => 'handle_customizer',

        // Update Modes
        GamesManager::UPDATE_CHANNEL_LIVE => 'handle_update_channel',
        GamesManager::UPDATE_CHANNEL_DEV => 'handle_update_channel',
        // Images
        'upload-profile-image' => 'handle_upload_profile_image',

        'delete' => 'handle_delete',
        'clone' => 'handle_clone'

    ];


    /**
     * ManageGameModController constructor.
     * @param null $template_factory
     * @param GameModEntity $gameMod
     * @param array $organizations
     */
    public function __construct($template_factory = null, GameModEntity $gameMod, $organizations = [])
    {
        parent::__construct($template_factory);

        $this->gameMod = $gameMod;
        $this->game = $gameMod->getGame();
        $this->organizations = $organizations;

        $activeOrganization = null;

        foreach ($this->organizations as $organization) {
            if ($organization->getPk() == $gameMod->getOrganizationId())
                $activeOrganization = $organization;
        }

        $this->activeOrganization = $activeOrganization;
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

        // Get/set game thumbnail images
        $avatarImages = $request->managers->images()->getActiveGameAvatarImagesByGameIds($request, $this->game->getPk());
        foreach ($avatarImages as $avatarImage) {
            $this->game->setAvatarImageUrls($avatarImage);
        }


        if (!$this->activeOrganization)
            return $this->render_404($request, 'Organization Not Found');

        $this->url_key = $url_key;

        $func = $this->resolve($request, $url_key, $pages, $render_default, $root);

        if ($func === null)
            throw new Http404();

        $this->gamesManager = $request->managers->games();
        $this->gamesDataManager = $request->managers->gamesData();
        $this->gamesBuildsManager = $request->managers->gamesBuilds();
        $this->gamesModsDataManager = $request->managers->gamesModsData();
        $this->gamesAssetsManager = $request->managers->gamesAssets();
        $this->gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();
        $this->activityManager = $request->managers->activity();
        $this->gamesEnginesManager = $request->managers->gamesEngines();
        $this->gamesCategoriesManager = $request->managers->gamesCategories();
        $this->gamesTypesManager = $request->managers->gamesTypes();
        $this->gamesModsManager = $request->managers->gamesMods();
        $this->gamesModsBuildsManager = $request->managers->gamesModsBuilds();
        $this->gamesModsActiveBuildsManager = $request->managers->gamesModsActiveBuilds();
        $this->activityManager = $request->managers->activity();
        $this->organizationsActivityManager = $request->managers->organizationsActivities();
        $this->gamesModsActiveCustomAssetsManager = $request->managers->gamesModsActiveCustomAssets();
        $this->gamesModsDataSheetsManager = $request->managers->gamesModsDataSheets();
        $this->gamesModsDataSheetsRowsManager = $request->managers->gamesModsDataSheetsRows();
        $this->gamesModsDataSheetsColumnsManager = $request->managers->gamesModsDataSheetsColumns();

        $this->imagesManager = $request->managers->images();
        $this->imagesTypesManager = $request->managers->imagesTypes();

        $this->activityManager = $request->managers->activity();
        $this->organizationsActivityManager = $request->managers->organizationsActivities();

        $this->gamesAssetsManager = $request->managers->gamesAssets();

        $this->updateChannels = $this->gamesManager->getUpdateChannelOptions();

        return $this->$func($request, $user, $this->gameMod, $this->activeOrganization);
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_manage_game_mod(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {

        $fields = [
            new CharField(DBField::DISPLAY_NAME, 'Title', 96, true, 'What is the title of your mod?'),
            new TextField(DBField::DESCRIPTION, 'Description', TEXT, false, 'Give a brief description of what your mod does.')
        ];

        $form = new PostForm($fields, $request, $gameMod);

        $formViewData = [
            TemplateVars::GAME_MOD => $gameMod
        ];

        $form->assignViewData($formViewData)->setTemplateFile('account/mods/forms/form-edit-mod.twig');

        if ($form->is_valid()) {

            $updatedModData = [
                DBField::DISPLAY_NAME => $form->getCleanedValue(DBField::DISPLAY_NAME),
                DBField::DESCRIPTION => $form->getCleanedValue(DBField::DESCRIPTION),
            ];

            $gameMod->assign($updatedModData)->saveEntityToDb($request);

            $request->user->sendFlashMessage('Settings Saved', MSG_SUCCESS);

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Manage Mod {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Manage Mod {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-manage-mod',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::FORM => $form,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::GAME_MOD => $gameMod,
            TemplateVars::GAME => $gameMod->getGame(),
            TemplateVars::UPDATE_CHANNELS => $this->updateChannels
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $viewData, 'account/mods/manage-mod.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return mixed
     */
    public function handle_update_channel(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {
        $updateChannel = $request->getSlugForEntity($this->url_key);

        require "manage-mod-update-channel.php";
        $updateChannelController = new ManageGameModUpdateChannelController(
            $this->templateFactory,
            $user,
            $gameMod,
            $activeOrganization,
            $this->organizations,
            $updateChannel,
            $this->updateChannels
        );

        return $updateChannelController->render($request, $this->url_key+1);
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_delete(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {

        $fields = [];
        $form = new PostForm($fields, $request, []);

        if ($form->is_valid()) {

            $this->gamesModsManager->deactivateEntity($request, $gameMod);

            $request->user->sendFlashMessage('Game Mod Deleted Successfully');

            return $form->handleRenderJsonSuccessResponse($request->getDevelopUrl("/teams/{$activeOrganization->getSlug()}/manage-mods"));

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form,
            TemplateVars::GAME_MOD => $gameMod
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/mods/delete-mod.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_clone(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {
        if (!$liveGameModBuild = $gameMod->getGameModBuildByUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE))
            return $this->render_404($request, 'Live Build Not Found', $request->is_ajax());

        $fields = [
            new CharField(DBField::DISPLAY_NAME, 'Title', 0, true, "What is the title of your new (cloned) game mod?")
        ];

        $form = new PostForm($fields, $request, []);

        $formViewData = [
            TemplateVars::GAME_MOD => $gameMod
        ];

        $form->assignViewData($formViewData)->setTemplateFile("account/mods/forms/form-clone-mod.twig");

        if ($form->is_valid()) {

            $displayName = $form->getCleanedValue(DBField::DISPLAY_NAME);

            $gameModBuildCustomAssets = $this->gamesModsActiveCustomAssetsManager->getActiveGameModActiveCustomAssetLinksByGameModBuildId(
                $request,
                $liveGameModBuild->getPk()
            );

            if ($gameModBuildCustomAssets)
                $gameModBuildCustomAssets = array_index($gameModBuildCustomAssets, DBField::SLUG);

            $liveGameModBuild->updateField(VField::CUSTOM_GAME_ASSETS, $gameModBuildCustomAssets);

            $liveCustomData = $this->gamesModsDataManager->getGameModDataByGameModBuildId($request, $liveGameModBuild->getPk(), true);

            $liveGameModBuild->updateField(VField::CUSTOM_DATA, $liveCustomData);

            $dbConnection = $request->db->get_connection();

            $dbConnection->begin();

            try {
                $newGameMod = $this->gamesModsManager->createNewGameMod(
                    $request,
                    $gameMod->getOrganizationId(),
                    $gameMod->getOrganizationSlug(),
                    $gameMod->getGameId(),
                    $gameMod->getGameSlug(),
                    $displayName,
                    $gameMod->getDescription()
                );

                $newDevGameModBuild = $this->gamesModsBuildsManager->createNewGameModBuild(
                    $request,
                    $newGameMod->getPk(),
                    GamesManager::UPDATE_CHANNEL_DEV,
                    "0.0.1"
                );

                // Clone Custom Game Assets
                $this->gamesModsActiveCustomAssetsManager->cloneCustomGameModBuildAssets($request, $liveGameModBuild, $newDevGameModBuild);
                // Clone Custom Game Data
                $this->gamesModsDataManager->cloneGameModBuildData($request, $liveGameModBuild, $newDevGameModBuild);

                $this->gamesModsActiveBuildsManager->createUpdateGameModActiveBuild($request, $newGameMod->getPk(), $newDevGameModBuild->getUpdateChannel(), $newDevGameModBuild->getPk());

                $dbConnection->commit();

            } catch (DBException $e) {
                $dbConnection->rollback();
                throw $e;
            }

            $request->user->sendFlashMessage('Game Mod Cloned Successfully');

            if ($gameMod->getGame()->can_customize())
                $next = $gameMod->getEditUrl("/customizer");
            else
                $next = $newGameMod->getEditUrl("/dev/builds");

            return $form->handleRenderJsonSuccessResponse($next);

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form,
            TemplateVars::GAME_MOD => $gameMod
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/mods/clone-mod.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameModEntity $gameMod
     * @param OrganizationEntity $activeOrganization
     * @return HtmlResponse|JSONResponse
     */
    public function handle_customizer(Request $request, UserEntity $user, GameModEntity $gameMod, OrganizationEntity $activeOrganization)
    {
        $gameBuild = $this->gamesBuildsManager->getActiveGameBuildByGameAndUpdateChannel(
            $request,
            $gameMod->getGameId(),
            GamesManager::UPDATE_CHANNEL_LIVE,
            true
        );

        $gameDataDefinition = $gameBuild->getCustomDataDefinition($request);

        $gameData = $this->gamesDataManager->getGameDataByGameBuildId($request, $gameBuild->getPk(), true);
        /** @var GameDataEntity[] $gameData */
        $gameData = $this->gamesDataManager->index($gameData, DBField::KEY);

        $gameModBuild = $this->gamesModsBuildsManager->getActiveGameModBuildByGameModIdAndUpdateChannel(
            $request,
            $gameMod->getPk(),
            GamesManager::UPDATE_CHANNEL_DEV
        );

        $newBuild = false;

        if (!$gameModBuild) {

            $gameModBuild = $this->gamesModsBuildsManager->createNewGameModBuild(
                $request,
                $gameMod->getPk(),
                GamesManager::UPDATE_CHANNEL_DEV,
                "0.0.1"
            );

            $newBuild = true;

        } elseif ($gameModBuild->is_published()) {

            $newGameModBuild = $this->gamesModsBuildsManager->createNewGameModBuild(
                $request,
                $gameMod->getPk(),
                GamesManager::UPDATE_CHANNEL_DEV,
                Strings::incrementBuildVersion($gameModBuild->getBuildVersion())
            );

            $this->gamesModsDataManager->cloneGameModBuildData($request, $gameModBuild, $newGameModBuild);
            $this->gamesModsActiveCustomAssetsManager->cloneCustomGameModBuildAssets($request, $gameModBuild, $newGameModBuild);

            //$oldGameModBuild = $gameModBuild;
            $gameModBuild = $newGameModBuild;

            $newBuild = true;
        }

        if ($newBuild) {
            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_CREATE_GAME_MOD_BUILD,
                $gameMod->getGameId(),
                $gameMod->getPk(),
                $user->getUiLanguageId(),
                $user
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $activeOrganization,
                $activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            $this->gamesModsActiveBuildsManager->createUpdateGameModActiveBuild(
                $request,
                $gameMod->getPk(),
                $gameModBuild->getUpdateChannel(),
                $gameModBuild->getPk()
            );

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
        }

        $gameModData = $this->gamesModsDataManager->getGameModDataByGameModBuildId($request, $gameModBuild->getPk());
        /** @var GameModDataEntity[] $gameModData */
        $gameModData = $this->gamesModsDataManager->index($gameModData, DBField::KEY);

        foreach ($gameData as $gameDatum) {

            if (!array_key_exists($gameDatum->getKey(), $gameModData)) {
                $gameModDatum = $this->gamesModsDataManager->createNewGameModData(
                    $request,
                    $gameModBuild->getPk(),
                    $gameDatum->getKey()
                );
                $gameModData[$gameDatum->getKey()] = $gameModDatum;
            } else {
                $gameModDatum = $gameModData[$gameDatum->getKey()];
            }

            foreach ($gameDatum->getGameDataSheets() as $gameDataSheet) {
                if ($gameDataSheet->can_mod()) {

                    $emptyGameModDataSheetRow = [];

                    foreach ($gameDataSheet->getGameDataSheetColumns() as $gameDataSheetColumn) {
                        $emptyGameModDataSheetRow[$gameDataSheetColumn->getName()] = null;
                    }

                    $newModSheet = false;

                    if (!$gameModDataSheet = $gameModDatum->getGameModDataSheetByName($gameDataSheet->getName())) {
                        $gameModDataSheet = $this->gamesModsDataSheetsManager->createNewGameModDataSheet(
                            $request,
                            $gameModDatum->getPk(),
                            $gameDataSheet->getName()
                        );

                        $indexColumn = $gameDataSheet->getIndexColumn();

                        foreach ($gameDataSheet->getGameDataSheetColumns() as $gameDataSheetColumn) {

                            $gameModDataSheetColumn = $this->gamesModsDataSheetsColumnsManager->createNewGameModDataSheetColumn(
                                $request,
                                $gameModDataSheet->getPk(),
                                $gameDataSheetColumn->getName(),
                                $gameDataSheetColumn->getDisplayOrder()
                            );

                            if ($gameDataSheetColumn->getPk() == $indexColumn->getPk()) {
                                $gameModDataSheet->updateField(DBField::GAME_MOD_DATA_SHEET_COLUMN_ID, $gameModDataSheetColumn->getPk())->saveEntityToDb($request);
                            }

                            $gameModDataSheet->setGameModDataSheetColumn($gameModDataSheetColumn);
                        }

                        $newModSheet = true;

                        $gameModDatum->setGameModDataSheet($gameModDataSheet);
                    }

                    if (($gameDataSheet->is_mod_replace() || $gameDataSheet->is_mod_replace_cascade())) {

                        if ($newModSheet || !$gameModDataSheet->getGameModDataSheetRows()) {
                            foreach ($gameDataSheet->getGameDataSheetRows() as $gameDataSheetRow) {
                                if (!$gameModDataSheetRow = $gameModDataSheet->getGameModDataSheetRowByOrder($gameDataSheetRow->getDisplayOrder())) {


                                    $gameModDataSheetRow = $this->gamesModsDataSheetsRowsManager->createNewGameModDataSheetRow(
                                        $request,
                                        $gameModDataSheet->getPk(),
                                        $gameDataSheet->is_mod_replace() ? $gameDataSheetRow->getProcessedValues() : $emptyGameModDataSheetRow,
                                        $gameDataSheetRow->getDisplayOrder()
                                    );

                                    $gameModDataSheet->setGameModDataSheetRow($gameModDataSheetRow);

                                }
                            }
                        }



                    } elseif ($gameDataSheet->is_mod_append()) {
                        // No idea how to handle atm
                    }
                }
            }

            $gameModData[$gameModDatum->getKey()] = $gameModDatum;
        }

        //$gameModData = $this->gamesModsDataManager->getGameModDataByGameModBuildId($request, $gameModBuild->getPk());

        $fields = [];
        $defaults = [];

        foreach ($gameModData as $gameModDatum) {
            foreach ($gameModDatum->getGameModDataSheets() as $gameModDataSheet) {

                foreach ($gameModDataSheet->getGameModDataSheetRows() as $gameModDataSheetRow) {
                    foreach ($gameModDataSheet->getGameModDataSheetColumns() as $gameModDataSheetColumn) {

                        $columnDefinition = $gameDataDefinition->getSheetColumnDefinitionByKeyAndSheetNameAndColumn(
                            $gameModDatum->getKey(),
                            $gameModDataSheet->getName(),
                            $gameModDataSheetColumn->getName()
                        );

                        if ($columnDefinition) {
                            $computedFieldName =  $gameModDataSheetRow->getDynamicFormField($gameModDataSheetColumn->getName());

                            $value = $gameModDataSheetRow->getProcessedValueByKey($gameModDataSheetColumn->getName());
                            $modGroup = $columnDefinition["modGroup"];

                            if ($gameDataDefinition->validateGroupIsModable($modGroup)) {

                                $label = $columnDefinition['typeDetails']['label'];
                                $description = $columnDefinition['typeDetails']['description'];

                                switch ($columnDefinition["type"]) {

                                    /*
                                            public ESCEnumColumnType(string[] options) : base("esc-enum")
                                            TODO public ESCCustomImageAsset(float aspectX, float aspectY, int minWidth) : base("esc-custom-image-asset")
                                            TODO public ESCCustomAudioAsset() : base("esc-custom-audio-asset")
                                            TODO public ESCCustomVideoAsset() : base("esc-custom-video-asset")
                                            TODO public ESCCustomVTTAsset() : base("esc-custom-vtt-asset")
                                            TODO public ESCColorString() : base("esc-color-string")
                                            TODO public ESCColorString(string defaultColor) : base("esc-color-string")
                                     */

                                    case 'esc-custom-image-asset': {

                                        $properties = [

                                        ];

                                        if (!$value) {
                                            $value = $gameData[$gameModDatum->getKey()]
                                                ->getGameDataSheetByName($gameModDataSheet->getName())
                                                ->getGameDataSheetRowByOrder($gameModDataSheetRow->getDisplayOrder())
                                                ->getProcessedValueByKey($gameModDataSheetColumn->getName());
                                        }

                                        $gameModActiveCustomAsset = $this->gamesModsActiveCustomAssetsManager->getGameModActiveCustomAssetLinkByGameModBuildIdAndSlug(
                                            $request,
                                            $gameModBuild->getPk(),
                                            $value
                                        );

                                        if (!$gameModActiveCustomAsset) {

                                            $gameModActiveCustomAsset = $this->gamesModsActiveCustomAssetsManager->createNewGameModActiveCustomAsset(
                                                $request,
                                                $gameMod->getPk(),
                                                $gameModBuild->getUpdateChannel(),
                                                $gameModBuild->getPk(),
                                                $value,
                                                null,
                                                1
                                            );
                                        } else {
                                            if ($gameModActiveCustomAsset->getContextXGameAssetId()) {

                                                $gameAsset = $this->gamesAssetsManager->getCustomGameModBuildAssetByCustomGameAssetId(
                                                    $request,
                                                    $gameModBuild->getPk(),
                                                    $gameModActiveCustomAsset->getContextXGameAssetId()
                                                );
                                                $gameModActiveCustomAsset->updateField(VField::GAME_ASSET, $gameAsset);
                                            }
                                        }

                                        $properties['aspectX'] = $columnDefinition['typeDetails']['aspectX'] ?? null;
                                        $properties['aspectY'] = $columnDefinition['typeDetails']['aspectY'] ?? null;
                                        $properties['minWidth'] = $columnDefinition['typeDetails']['minWidth'] ?? null;

                                        $field = new ESCCustomImageAssetField(
                                            $gameModActiveCustomAsset,
                                            $properties,
                                            $computedFieldName,
                                            $label ? $label : $gameModDataSheetColumn->getName(),
                                            false,
                                            $description
                                        );

                                        break;
                                    }
                                    case "esc-color-string" : {
                                        $field = new HexaDecimalColorField(
                                            $computedFieldName,
                                            $label ? $label : $gameModDataSheetColumn->getName(),
                                            $modGroup == CustomDataDefinitionsEntity::MOD_GROUP_SPONSOR ? false : true,
                                            $description
                                        );
                                        break;
                                    }
                                    case "esc-enum" : {

                                        $selectOptions = [];

                                        foreach($columnDefinition["typeDetails"]["options"] as $option) {
                                            $selectOptions[] = [ slugify($option), $option ];
                                        }

                                        $field = new SelectField(
                                            $computedFieldName,
                                            $label ? $label : $gameModDataSheetColumn->getName(),
                                            $selectOptions,
                                            $modGroup == CustomDataDefinitionsEntity::MOD_GROUP_SPONSOR ? false : true,
                                            $description
                                        );
                                        break;
                                    }

                                    case "esc-url" : {
                                        $field = new URLField(
                                            $computedFieldName,
                                            $label ? $label : $gameModDataSheetColumn->getName(),
                                            255,
                                            $modGroup == CustomDataDefinitionsEntity::MOD_GROUP_SPONSOR ? false : true,
                                            $description,
                                            ['http', 'https']
                                        );
                                        break;
                                    }

                                    default : {
                                        $field = new CharField(
                                            $computedFieldName,
                                            $label ? $label : $gameModDataSheetColumn->getName(),
                                            0,
                                            true,
                                            $description
                                        );
                                    }
                                }

                                $field->setFieldGroup($modGroup);
                                $fields[] = $field;
                                $defaults[$computedFieldName] = $value;
                            }
                        }
                    }
                }
            }
        }

        $form = new PostForm($fields, $request, $defaults);

        if ($form->is_valid()) {

            foreach ($gameModData as $gameModDatum) {
                foreach ($gameModDatum->getGameModDataSheets() as $gameModDataSheet) {

                    foreach ($gameModDataSheet->getGameModDataSheetRows() as $gameModDataSheetRow) {
                        $rowData = $gameModDataSheetRow->getProcessedValues();
                        $changed = false;

                        foreach ($gameModDataSheet->getGameModDataSheetColumns() as $gameModDataSheetColumn) {

                            $columnDefinition = $gameDataDefinition->getSheetColumnDefinitionByKeyAndSheetNameAndColumn(
                                $gameModDatum->getKey(),
                                $gameModDataSheet->getName(),
                                $gameModDataSheetColumn->getName()
                            );

                            if ($columnDefinition) {
                                $computedFieldName =  $gameModDataSheetRow->getDynamicFormField($gameModDataSheetColumn->getName());
                                $modGroup = $columnDefinition["modGroup"];

                                if ($gameDataDefinition->validateGroupIsModable($modGroup)) {
                                    if ($form->has_field($computedFieldName)) {
                                        $rowData[$gameModDataSheetColumn->getName()] = $form->getCleanedValue($computedFieldName);
                                        $changed = true;
                                    }
                                }
                            }
                        }

                        if ($changed) {
                            $gameModDataSheetRow->updateField(DBField::VALUE, serialize($rowData));
                            $gameModDataSheetRow->updateField(DBField::INDEX_KEY, $rowData[$gameModDataSheet->getIndexColumn()->getName()]);

                            $gameModDataSheetRow->saveEntityToDb($request);
                        }
                    }
                }
            }
        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $gameModDataJson = DBManagerEntity::extractJsonDataArrays(array_values($gameModData));

        $next = $request->getRedirectBackUrl();
        if ($next == $request->getWwwUrl() || stripos($next, $gameMod->getEditUrl("/customizer") !== false))
            $next = $request->getDevelopUrl("/teams/{$activeOrganization->getSlug()}/manage-mods/");

        if (strpos($next, "/{$gameMod->getPk()}/customizer") !== false)
            $next = $request->getDevelopUrl("/teams/{$activeOrganization->getSlug()}/manage-mods/");

        $pageJsData = [
            TemplateVars::GAME_MOD => $gameMod->getJSONData(),
            TemplateVars::GAME_MOD_BUILD => $gameModBuild->getJSONData(),
            TemplateVars::GAME_MOD_DATA => $gameModDataJson,
            TemplateVars::BRANDING_FORM => $form->renderFieldsAsJson(),
            TemplateVars::FORM => $form->renderFieldsAsJson(),
            TemplateVars::GAME_BUILD => $gameBuild->getJSONData(),
            TemplateVars::GAME_PHASES => $gameDataDefinition->getPhases(),
            TemplateVars::NEXT => base64_encode($next)
        ];

        if ($request->is_post())
            return $this->renderJsonResponse($pageJsData);

        $this->assignPageJsViewData($pageJsData);

        $md5Js = null;
        $md5Css = null;

        $jsFile = $request->settings()->getProjectDir("apps/www/webroot/static/pwa/customizer/static/js/main.chunk.js");
        $cssFile = $request->settings()->getProjectDir("apps/www/webroot/static/pwa/customizer/static/css/main.chunk.css");

        if (is_file($jsFile)) {
            $md5Js = md5_file($jsFile);
        }

        if (is_file($cssFile)) {
            $md5Css = md5_file($cssFile);
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Customize Mod {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Customize Mod {$gameMod->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_IDENTIFIER => 'dev-org-customize-mod',
            TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization,
            TemplateVars::ORGANIZATIONS => $this->organizations,
            TemplateVars::GAME_MOD => $gameMod,
            TemplateVars::GAME => $gameMod->getGame(),
            TemplateVars::UPDATE_CHANNELS => $this->updateChannels,
            TemplateVars::FORM => $form,
            TemplateVars::MD5_JS => $md5Js,
            TemplateVars::MD5_CSS => $md5Css,
        ];

        return $this->renderPageResponse($request, $viewData, 'account/mods/manage-mod/customizer.twig', true);
    }
}
