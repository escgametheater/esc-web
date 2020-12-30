<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 1/18/19
 * Time: 1:48 PM
 */

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class ManageGameUpdateChannelController extends BaseContent
{
    protected $updateChannel = GamesManager::UPDATE_CHANNEL_DEV;

    /** @var array $activeGameBuildSummary */
    protected $activeGameBuildSummary = null;

    /** @var array $activeGameBuildSummaries */
    protected $activeGameBuildSummaries = null;

    /** @var array  */
    protected $channelGameBuildOptions = [];

    /** @var null|int $selectedGameBuildId */
    protected $selectedGameBuildId = null;

    /** @var OrganizationEntity[] $organizations */
    protected $organizations = [];

    /** @var GameEntity $game */
    protected $game;
    /** @var UserEntity $user */
    protected $user;

    /** @var int $url_key */
    protected $url_key = 5;

    /** @var OrganizationEntity */
    protected $activeOrganization;

    /** @var GamesManager $gamesManager */
    protected $gamesManager;
    /** @var ActivityManager $activityManager */
    protected $activityManager;
    /** @var GamesDataManager $gamesDataManager */
    protected $gamesDataManager;
    /** @var GamesBuildsManager $gamesBuildsManager */
    protected $gamesBuildsManager;
    /** @var GamesAssetsManager $gamesAssetsManager */
    protected $gamesAssetsManager;
    /** @var HostsInstancesManager $hostInstancesManager */
    protected $hostInstancesManager;
    /** @var GamesInstancesManager $gamesInstancesManager */
    protected $gamesInstancesManager;
    /** @var GamesControllersManager $gamesControllersManager */
    protected $gamesControllersManager;
    /** @var GamesActiveBuildsManager $gamesActiveBuildsManager */
    protected $gamesActiveBuildsManager;
    /** @var ContextXGamesAssetsManager $contextXGamesAssetsManager */
    protected $contextXGamesAssetsManager;
    /** @var GamesControllersTypesManager $gamesControllersTypesManager */
    protected $gamesControllersTypesManager;
    /** @var OrganizationsActivityManager $organizationsActivityManager */
    protected $organizationsActivityManager;
    /** @var GamesActiveCustomAssetsManager $gamesActiveCustomAssetsManager */
    protected $gamesActiveCustomAssetsManager;
    /** @var GamesDataSheetsModsTypesManager $gamesDataSheetsModTypesManager */
    protected $gamesDataSheetsModTypesManager;

    /** @var UsersManager $usersManager */
    protected $usersManager;

    /**
     * @var array $pages
     */
    protected $pages = [

        // Analytics & Stats
        'analytics' => 'handle_game_analytics',
        'stats' => 'handle_stats',

        // Game Licenses / Access
        'access-control' => 'handle_access_control',
        'add-user-access' => 'handle_add_user_access',
        'remove-user-access' => 'handle_remove_user_access',

        // Game Builds
        'builds' => 'handle_manage_builds',
        'view-game-build' => 'handle_view_game_build',
        'set-active-build' => 'handle_set_active_build',
        'delete-game-build' => 'handle_delete_game_build',
        'upload-game-build' => 'handle_upload_game_build',
        'publish-to-live' => 'handle_publish_build_to_live',

        // Game Controllers
        'view-game-controller' => 'handle_view_game_controller',
        'upload-game-controller' => 'handle_upload_game_controller',
    ];

    protected $gameBuildPages = [

        // Custom Data
        'view-custom-data' => 'handle_view_custom_data',
        'upload-custom-data' => 'handle_upload_custom_data',
        'update-custom-data-sheet' => 'handle_update_custom_data_sheet',
        'download-custom-data-xls' => 'handle_download_custom_data_xls',

        // Custom Game Assets
        'batch-upload-custom-game-assets' => 'handle_batch_upload_custom_game_assets',

        'view-custom-game-asset' => 'handle_view_custom_game_asset',
        'upload-custom-game-asset' => 'handle_upload_custom_game_asset',
        'set-custom-asset-active' => 'handle_set_custom_asset_active',
        'delete-custom-game-asset' => 'handle_delete_custom_asset',
        'delete-custom-game-asset-file' => 'handle_delete_custom_asset_file',

        'download-custom-game-asset-file' => 'handle_download_custom_game_asset_file',

        'batch-download-custom-game-asset-files' => 'handle_batch_download_custom_game_asset_files',
        'batch-download-game-asset-files' => 'handle_batch_download_game_build_asset_files',
        'batch-download-controller-asset-files' => 'handle_batch_download_game_controller_asset_files',
    ];


    /**
     * ManageGameUpdateChannelController constructor.
     * @param null $template_factory
     * @param OrganizationEntity[] $organizations
     * @param GameEntity $game
     * @param UserEntity $user
     * @param $updateChannel
     * @param array $activeGameBuildSummary
     */
    public function __construct($template_factory = null, OrganizationEntity $activeOrganization, $organizations = [], GameEntity $game, UserEntity $user, $updateChannel, $activeGameBuildSummary = [])
    {
        parent::__construct($template_factory);

        $this->activeOrganization = $activeOrganization;
        $this->organizations = $organizations;
        $this->game = $game;
        $this->user = $user;
        $this->updateChannel = $updateChannel;

        $this->pages = array_merge($this->pages, $this->gameBuildPages);
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
        $this->activityManager = $request->managers->activity();
        $this->gamesDataManager = $request->managers->gamesData();
        $this->gamesAssetsManager = $request->managers->gamesAssets();
        $this->gamesBuildsManager = $request->managers->gamesBuilds();
        $this->hostInstancesManager = $request->managers->hostsInstances();
        $this->gamesInstancesManager = $request->managers->gamesInstances();
        $this->gamesControllersManager = $request->managers->gamesControllers();
        $this->gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();
        $this->contextXGamesAssetsManager = $request->managers->contextXGamesAssets();
        $this->gamesControllersTypesManager = $request->managers->gamesControllersTypes();
        $this->organizationsActivityManager = $request->managers->organizationsActivities();
        $this->gamesActiveCustomAssetsManager = $request->managers->gamesActiveCustomAssets();
        $this->gamesDataSheetsModTypesManager = $request->managers->gamesDataSheetsModTypes();

        $this->usersManager = $request->managers->users();
        $this->imagesManager = $request->managers->images();
        $this->imagesTypesManager = $request->managers->imagesTypes();

        $this->channelGameBuildOptions = $this->gamesBuildsManager->getGameBuildOptionsByGameAndUpdateChannel($request, $this->game->getPk(), $this->updateChannel);

        $cachedSelectedGameBuildId = $request->user->session->safeGet($this->generateSelectedGameBuildIdSessionKey());

        $selectedGameBuildId = $request->get->readParam(DBField::GAME_BUILD_ID, $cachedSelectedGameBuildId);
        $this->selectedGameBuildId = $selectedGameBuildId;

        if ($this->activeGameBuildSummaries === null) {
            $this->activeGameBuildSummaries = $this->gamesActiveBuildsManager->getGameActiveBuildVersionSummaries($request, $this->game->getPk());
        }

        if (in_array($func, array_values($this->gameBuildPages)) && !in_array($selectedGameBuildId, array_keys($this->channelGameBuildOptions))) {
            throw new Http404('Game Build ID Not Found');
        }


        return $this->$func($request, $user, $this->game, $selectedGameBuildId);
    }

    /**
     * @return string
     */
    protected function generateSelectedGameBuildIdSessionKey()
    {
        return "{$this->game->getSlug()}_game_{$this->updateChannel}_selected-build-id";
    }


    /**
     * @param array $viewData
     * @return array
     */
    protected function mergeSharedTemplateVars(Request $request, $viewData = [])
    {
        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();

        if ($this->activeGameBuildSummary === null) {
            $this->activeGameBuildSummary = $gamesActiveBuildsManager->getGameActiveBuildVersionSummaryByUpdateChannel(
                $request,
                $this->game->getPk(),
                $this->updateChannel
            );
        }

        $activeOrganization = [];

        if ($this->game->getOwnerTypeId() == EntityType::ORGANIZATION) {
            if (array_key_exists($this->game->getOwnerId(), $this->organizations)) {
                $activeOrganization = $this->organizations[$this->game->getOwnerId()];
            }
        }


        return array_merge(
            [
                TemplateVars::PROFILE_USER => $this->user,
                TemplateVars::GAME => $this->game,
                TemplateVars::UPDATE_CHANNEL => $this->updateChannel,
                TemplateVars::ACTIVE_BUILD_VERSION_SUMMARY => $this->activeGameBuildSummary,
                TemplateVars::ACTIVE_BUILD_SUMMARIES => $this->activeGameBuildSummaries,
                TemplateVars::GAME_BUILD_OPTIONS => $this->channelGameBuildOptions,
                TemplateVars::SELECTED_GAME_BUILD_ID => $this->selectedGameBuildId,
                TemplateVars::UPDATE_CHANNELS => $this->gamesManager->getUpdateChannelOptions(),
                TemplateVars::ACTIVE_UPDATE_CHANNEL => $this->gamesManager->getUpdateChannelOption($this->updateChannel),
                TemplateVars::ORGANIZATIONS => $this->organizations,
                TemplateVars::ACTIVE_ORGANIZATION => $activeOrganization
            ],
            $viewData
        );
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse
     */
    protected function handle_manage_builds(Request $request, UserEntity $user, GameEntity $game)
    {
        $gameBuilds = $this->gamesBuildsManager->getGameBuildsByGameId($request, $game->getPk(), true, $this->updateChannel, true, true);

        $viewData = [
            TemplateVars::PAGE_TITLE => "Manage Builds for {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Manage Builds for {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $game->getEditUrl("/{$this->updateChannel}/builds"),
            TemplateVars::PAGE_IDENTIFIER => 'game-builds',
            TemplateVars::GAME_BUILDS => $gameBuilds,
            TemplateVars::USE_CROPPIE => true,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/manage-builds.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse
     */
    protected function handle_access_control(Request $request, UserEntity $user, GameEntity $game)
    {
        $gameLicensesManager = $request->managers->gameLicenses();

        $usersGames = $gameLicensesManager->getUsersGameLicensesByGameId($request, $game->getPk(), $this->updateChannel);

        $viewData = [
            TemplateVars::PAGE_TITLE => "Manage Access - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Manage Access - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $game->getEditUrl(),
            TemplateVars::PAGE_IDENTIFIER => 'game-access-control',
            TemplateVars::USERS => $usersGames,
        ];

        return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/access.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse
     */
    protected function handle_view_custom_data(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        if (!$gameDataKey = $request->getSlugForEntity($this->url_key+1))
            return $this->render_404($request);

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $selectedGameBuildId, $game->getPk());
        if (!$gameBuild)
            return $this->render_404($request);

        $gameData = $this->gamesDataManager->getGameDataByGameBuildIdAndKey($request, $gameBuild->getPk(), $gameDataKey);

        if (!$gameData)
            return $this->render_404($request);

        $gameDataSheetModTypes = $this->gamesDataSheetsModTypesManager->getAllGameDataSheetModTypes($request);

        $forms = [];

        foreach ($gameData->getGameDataSheets() as $gameDataSheet) {
            $sheetName = $gameDataSheet->getName();

            $fields = [
                new CharField(DBField::NAME, 'Title', 32, true, 'This is the name of the sheet and is used by code to reference columns and rows.'),
                new SelectField(DBField::GAME_DATA_SHEET_COLUMN_ID, 'ID/Key Column', $gameDataSheet->getGameDataSheetColumns(), false, 'Unique row identifier column.'),
                new BooleanField(DBField::CAN_MOD, 'Is Moddable', false, 'If checked, enables others to mod the content of this sheet.'),
                new SelectField(DBField::GAME_DATA_SHEET_MOD_TYPE_ID, 'Mod Type', $gameDataSheetModTypes, true, 'When selected as moddable, this is the behavior of the modded content.'),
            ];

            $form = new PostForm($fields, $request, $gameDataSheet);
            $form->setTemplateFile('account/games/forms/form-edit-sheet.twig');

            $forms[$sheetName] = $form;
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Custom Data - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Custom Data - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $game->getEditUrl(),
            TemplateVars::PAGE_IDENTIFIER => 'game-view-custom-data',
            TemplateVars::GAME_DATA => $gameData,
            TemplateVars::GAME_BUILD => $gameBuild,
            TemplateVars::FORMS => $forms
        ];

        return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/manage-build/view-custom-data.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @param $selectedGameBuildId
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_update_custom_data_sheet(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $gamesDataManager = $request->managers->gamesData();
        $gamesDataSheetsManager = $request->managers->gamesDataSheets();
        $gameDataSheetModTypesManager = $request->managers->gamesDataSheetsModTypes();

        $gameDataSheetModTypes = $gameDataSheetModTypesManager->getAllGameDataSheetModTypes($request);

        if (!$gameDataId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Game Data Id Not Found');

        if (!$gameData = $gamesDataManager->getGameDataById($request, $gameDataId, $selectedGameBuildId))
            return $this->render_404($request, 'Game Data Not Found');

        if (!$gameDataSheetId = $request->getIdForEntity($this->url_key+2))
            return $this->render_404($request, 'Game Data Sheet Id Not Found');

        if (!$gameDataSheet = $gameData->getGameDataSheetById($gameDataSheetId))
            return $this->render_404($request, 'Game Data Sheet Not Found');

        $fields = [
            new CharField(DBField::NAME, 'Sheet Title', 32, true, 'This is the name of the sheet and is used by code to reference columns and rows.'),
            new SelectField(DBField::GAME_DATA_SHEET_COLUMN_ID, 'ID/Key Column', $gameDataSheet->getGameDataSheetColumns(), false, 'Unique row identifier column.'),
            new BooleanField(DBField::CAN_MOD, 'Is Moddable', false, 'If checked, enables others to mod the content of this sheet.'),
            new SelectField(DBField::GAME_DATA_SHEET_MOD_TYPE_ID, 'Mod Type', $gameDataSheetModTypes, true, 'When selected as moddable, this is the behavior of the modded content.'),
        ];

        $form = new PostForm($fields, $request, $gameDataSheet);
        $form->setTemplateFile('account/games/forms/form-edit-sheet.twig');

        if ($form->is_valid()) {

            $gameDataSheetColumnId = $gameDataSheet->getGameDataSheetColumnId();

            $gameDataSheet->assignByForm($form)->saveEntityToDb($request);

            if ($gameDataSheetColumnId != $form->getCleanedValue(DBField::GAME_DATA_SHEET_COLUMN_ID)) {
                $gamesDataSheetsManager->updateGameDataSheetRowsIndexKeys($request, $gameDataSheet);
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
     * @param GameEntity $game
     * @param $selectedGameBuildId
     * @return DownloadXlsxResponse|HtmlResponse
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function handle_download_custom_data_xls(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $gamesDataManager = $request->managers->gamesData();

        if (!$gameDataId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request, 'Game Data Id Not Found');

        if (!$gameData = $gamesDataManager->getGameDataById($request, $gameDataId, $selectedGameBuildId))
            return $this->render_404($request, 'Game Data Not Found');

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle("Custom Data ({$gameData->getKey()}) for {$game->getDisplayName()}")
            ->setLastModifiedBy($gameData->getModifiedBy())
            ->setCreator($user->getDisplayName());

        $spreadsheet->removeSheetByIndex(0);

        foreach ($gameData->getGameDataSheets() as $gameDataSheet) {
            try {
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle($gameDataSheet->getName());

                $data = [];

                $data[] = $gameDataSheet->getGameDataSheetColumnValues();

                foreach ($gameDataSheet->getGameDataSheetRows() as $gameDataSheetRow) {
                    $row = [];

                    foreach ($gameDataSheet->getGameDataSheetColumns() as $gameDataSheetColumn) {
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

        return new DownloadXlsxResponse($writer, "{$game->getSlug()}_custom-data_{$gameData->getKey()}-{$this->updateChannel}-{$gameData->getPk()}_{$request->getCurrentSqlTime('Y-m-d-H-i-s')}.xlsx");

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_upload_custom_data(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $customDataKey = $request->getSlugForEntity($this->url_key+1);

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $selectedGameBuildId, $game->getPk());

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

        $form->setTemplateFile('account/games/forms/new-game-data.twig');

        if ($isValid = $form->is_valid()) {

            $key = $form->getCleanedValue(DBField::KEY);
            $uploadId = $form->getCleanedValue(DBField::FILE)['upload_id_file'];

            $sourceFile = UploadsHelper::path_from_file_id($uploadId);

            if (!$customDataKey) {
                $gameData = $this->gamesDataManager->getGameDataByChannelAndKey(
                    $request,
                    $game->getPk(),
                    $this->updateChannel,
                    $key,
                    $selectedGameBuildId
                );
                if ($gameData) {
                    $isValid = false;
                    $form->set_error('Key already exists.', DBField::KEY);
                }

            }


            if ($isValid) {
                $reader = new Xlsx();

                try {
                    $spreadSheet = $reader->load($sourceFile);
                    $gameData = $this->gamesDataManager->processSpreadsheet(
                        $request,
                        $game->getPk(),
                        $this->updateChannel,
                        $selectedGameBuildId,
                        $key,
                        $spreadSheet
                    );


                } catch (Exception $e) {
                    $gameData = [];
                }

                UploadsHelper::delete_upload($uploadId);

                if ($gameData) {

                    $activity = $this->activityManager->trackActivity(
                        $request,
                        ActivityTypesManager::ACTIVITY_TYPE_USER_UPLOAD_GAME_SPREADSHEET,
                        $game->getPk(),
                        $gameData->getPk(),
                        $user->getUiLanguageId(),
                        $user
                    );

                    $this->organizationsActivityManager->trackOrganizationActivity(
                        $request,
                        $activity,
                        $this->activeOrganization,
                        $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
                    );

                    $request->user->sendFlashMessage('Spreadsheet uploaded and processed', MSG_SUCCESS);
                    return $form->handleRenderJsonSuccessResponse($game->getEditUrl("/{$this->updateChannel}/view-custom-data/{$key}?game_build_id={$gameBuild->getPk()}"));
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

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $selectedGameBuildId, $game->getPk());

        $viewData = [
            TemplateVars::PAGE_TITLE => "Upload Custom Data - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Upload Custom Data - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $game->getEditUrl("/{$this->updateChannel}/upload-custom-data"),
            TemplateVars::PAGE_IDENTIFIER => 'game-upload-custom-data',
            TemplateVars::FORM => $form,
            TemplateVars::SLUG => $customDataKey,
            TemplateVars::GAME_BUILD => $gameBuild,
        ];

        return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/manage-build/upload-custom-data.twig');
    }




    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse
     */
    protected function handle_game_analytics(Request $request, UserEntity $user, GameEntity $game)
    {
        $viewData = [
            TemplateVars::PAGE_TITLE => "Analytics - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Analytics - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $game->getEditUrl(),
            TemplateVars::PAGE_IDENTIFIER => 'game-analytics',
        ];

        return $this->setUseCharts()->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/analytics.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return JSONResponse
     */
    protected function handle_stats(Request $request, UserEntity $user, GameEntity $game)
    {
        $dt = new DateTime();
        $dt->modify("-3 month");

        $conn = $request->db->get_connection(SQLN_BI);

        $sql = "
            SELECT
                date_format(hi.start_time, '%Y-%m-%d') as instance_date,
                count(distinct hi.host_id) as unique_hosts,
                count(distinct hi.host_instance_id) as host_instances,
                count(distinct gi.game_instance_id) as game_instances,
                count(distinct gir.game_instance_round_id) as game_rounds,
                count(distinct s.guest_id) as unique_game_players,
                count(distinct girp.game_instance_round_player_id) as game_player_sessions
            FROM game_instance gi
            JOIN game_build gb 
              on gi.game_build_id = gb.game_build_id
            JOIN host_instance hi
            	on hi.host_instance_id = gi.host_instance_id
            LEFT JOIN game_instance_round gir
              on gi.game_instance_id = gir.game_instance_id
            LEFT JOIN game_instance_round_player girp
              on girp.game_instance_round_id = gir.game_instance_round_id
            LEFT JOIN session s
              on s.session_id = girp.session_id
            WHERE
              DATE(gi.start_time) >= DATE('{$dt->format(SQL_DATETIME)}')
              and gi.game_id = {$conn->quote_value($game->getPk())}
              and gb.update_channel = {$conn->quote_value($this->updateChannel)}
            GROUP BY 1
            ORDER BY 1 ASC;
        ";

        $stats = $this->gamesInstancesManager->query($request->db)
            ->set_connection($conn)
            ->sql($sql);

        return $this->renderJsonResponse($stats);

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_add_user_access(Request $request, UserEntity $user, GameEntity $game)
    {
        force_slash($request);

        $usersManager = $request->managers->users();
        $gameLicensesManager = $request->managers->gameLicenses();

        $translations = $request->translations;

        $fields = [
            new EmailField(DBField::EMAIL_ADDRESS, $translations['Email Address'], true, 'Email address of the user on the platform.'),
            new DateTimeField(DBField::START_TIME, $translations['License Start Time'], true, 'License Start Time (EST)'),
            new DateTimeField(DBField::END_TIME, $translations['Expiration Time'], false, 'License End Time (EST)'),
        ];

        $estTimeZone = new DateTimeZone('America/New_York');
        $utcTimeZone = new DateTimeZone('UTC');

        $dt = new DateTime();
        $dt->modify("-1 day");

        $defaults = [
            DBField::START_TIME => $dt->format(SQL_DATETIME),
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->setTemplateFile('account/games/forms/new-user-license.twig');

        if ($isValid = $form->is_valid()) {

            $emailAddress = strtolower($form->getCleanedValue(DBField::EMAIL_ADDRESS));
            $startTime = $form->getCleanedValue(DBField::START_TIME);
            $endTime = $form->getCleanedValue(DBField::END_TIME);

            $gameUser = $usersManager->getUserByEmailAddress($request, $emailAddress);

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

            if (!$gameUser) {
                $form->set_error('Email address does not exist.', DBField::EMAIL_ADDRESS);
                $isValid = false;
            } elseif ($isValid) {

                $gameUserLicenses = $gameLicensesManager->getAllActiveGameUserLicensesByUserId(
                    $request,
                    $game->getPk(),
                    $gameUser->getPk(),
                    $this->updateChannel
                );

                foreach ($gameUserLicenses as $gameUserLicense) {
                    if (!$gameUserLicense->getEndTime()) {
                        $request->user->sendFlashMessage('Found Expiring License');
                        if (time_gte($request->getCurrentSqlTime(), $gameUserLicense->getStartTime())) {
                            $form->set_error("Overlapping license with no expiration already exists: ({$gameUserLicense->getStartTime()} -> No Expiration Time)", DBField::EMAIL_ADDRESS);
                            $isValid = false;
                            break;
                        }
                    }
                }
            }

            if ($isValid) {
                $gameUserLicense = $gameLicensesManager->createNewGameUserLicense(
                    $request,
                    $game->getPk(),
                    $gameUser->getPk(),
                    $this->updateChannel,
                    $startTime,
                    $endTime
                );

                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_ADD_GAME_LICENSE,
                    $game->getPk(),
                    $gameUserLicense->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $this->activeOrganization,
                    $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                $request->user->sendFlashMessage('Added License');
                return $form->handleRenderJsonSuccessResponse($game->getEditUrl("/{$this->updateChannel}/access-control"));
            } else {
                return $form->handleRenderJsonErrorResponse();
            }
        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Add User Access - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Add User Access - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $game->getEditUrl(),
            TemplateVars::PAGE_IDENTIFIER => 'game-access-add-user',
            TemplateVars::FORM => $form,
        ];

        if ($request->is_ajax())
            return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/add-user-access.twig');
        else
            return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/add-user-access.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_remove_user_access(Request $request, UserEntity $user, GameEntity $game)
    {
        $gameLicensesManager = $request->managers->gameLicenses();
        $usersManager = $request->managers->users();

        $GameLicenseId = $request->getIdForEntity($this->url_key+1);
        if (!$GameLicenseId)
            return $this->render_404($request, 'ID Not Found', $request->is_ajax());

        $GameLicense = $gameLicensesManager->getUserGameLicenseById($request, $GameLicenseId, $game->getPk());
        if (!$GameLicense)
            return $this->render_404($request, 'License Not Found', $request->is_ajax());

        $fields = [
            new HiddenField(DBField::GAME_LICENSE_ID, 'GameLicenseId'),
        ];
        $defaults = [
            DBField::GAME_LICENSE_ID => $GameLicenseId
        ];

        $form = new PostForm($fields, $request, $defaults);

        $gameUser = $usersManager->getUserById($request, $GameLicense->getUserId());

        if ($form->is_valid()) {
            $GameLicense->updateField(DBField::IS_ACTIVE, 0)->saveEntityToDb($request);

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_DELETE_GAME_LICENSE,
                $game->getPk(),
                $GameLicense->getPk(),
                $user->getUiLanguageId(),
                $user
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $this->activeOrganization,
                $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            $request->user->sendFlashMessage('Removed User License');
            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());
        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::PAGE_TITLE => "Remove User Access - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_DESCRIPTION => "Remove User Access - {$game->getDisplayName()} - {$request->settings()->getWebsiteName()}",
            TemplateVars::PAGE_CANONICAL => $game->getEditUrl(),
            TemplateVars::PAGE_IDENTIFIER => 'game-access-remove-user',
            TemplateVars::FORM => $form,
            TemplateVars::LICENSE => $GameLicense,
            TemplateVars::EMAIL => $gameUser->getEmailAddress()
        ];

        if ($request->is_ajax())
            return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/remove-user-access.twig');
        else
            return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/remove-user-access.twig');
    }


    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|HttpResponseRedirect
     */
    protected function handle_set_active_build(Request $request, UserEntity $user, GameEntity $game)
    {
        if (!$gameBuildId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request);

        if (!in_array($gameBuildId, array_keys($this->channelGameBuildOptions)))
            return $this->render_404($request);

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameBuildId, $game->getPk());

        if (!$gameBuild)
            return $this->render_404($request);

        $this->gamesActiveBuildsManager->createUpdateGameActiveBuild($request, $game->getPk(), $this->updateChannel, $gameBuild->getPk());

        $activity = $this->activityManager->trackActivity(
            $request,
            ActivityTypesManager::ACTIVITY_TYPE_USER_SET_BUILD_ACTIVE,
            $game->getPk(),
            $gameBuild->getPk(),
            $user->getUiLanguageId(),
            $user
        );

        $this->organizationsActivityManager->trackOrganizationActivity(
            $request,
            $activity,
            $this->activeOrganization,
            $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
        );


        $request->user->sendFlashMessage('Active Game Build Updated');

        return $this->redirect($game->getEditUrl("/{$this->updateChannel}/builds"));
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|HttpResponseRedirect|JSONResponse
     */
    protected function handle_delete_game_build(Request $request, UserEntity $user, GameEntity $game)
    {
        if (!$gameBuildId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request);

        if (!in_array($gameBuildId, array_keys($this->channelGameBuildOptions)))
            return $this->render_404($request);

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameBuildId, $game->getPk());

        if (!$gameBuild)
            return $this->render_404($request);

        $fields = [
            new MatchingIntegerField($this->gamesBuildsManager->getPkField(), 'PK', $gameBuildId)
        ];

        $form = new PostForm($fields, $request);

        if ($form->is_valid()) {
            $gameBuild->updateField(DBField::IS_ACTIVE, 0)->saveEntityToDb($request);

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_DELETE_GAME_BUILD,
                $game->getPk(),
                $gameBuild->getPk(),
                $user->getUiLanguageId(),
                $user
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $this->activeOrganization,
                $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            $request->user->sendFlashMessage('Game Build Deleted');
            return $form->handleRenderJsonSuccessResponse($game->getEditUrl("/{$this->updateChannel}/builds"));
        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::GAME => $game,
            TemplateVars::GAME_BUILD => $gameBuild
        ];

        return $this->renderAjaxResponse($request, $viewData, 'account/games/manage-game/manage-build/delete-build.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_view_game_build(Request $request, UserEntity $user, GameEntity $game)
    {
        $session = $request->user->session;

        if (!$gameBuildId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request);

        if (!in_array($gameBuildId, array_keys($this->channelGameBuildOptions)))
            return $this->render_404($request);

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameBuildId, $game->getPk(), true);

        $gameDataDefinition = $gameBuild->getCustomDataDefinition($request);

        if (!$gameBuild)
            return $this->render_404($request);

        try {

            $countInstances = $this->gamesInstancesManager->query($request->db)
                ->inner_join($this->hostInstancesManager)
                ->filter($this->gamesInstancesManager->filters->byGameId($gameBuild->getGameId()))
                ->filter($this->gamesInstancesManager->filters->byGameBuildId($gameBuild->getPk()))
                ->get([
                    new CountDBField(VField::PLAYERS, DBField::USER_ID, $this->hostInstancesManager->getTable(), true),
                    new CountDBField(VField::COUNT, DBField::GAME_INSTANCE_ID, $this->gamesInstancesManager->getTable())
                ]);
        } catch (DBException $e) {
            $countInstances = [];
        }

        $gameBuild->updateField(VField::GAME_INSTANCES, $countInstances);

        $isReadOnly = $gameBuild->is_live_build() || $gameBuild->is_published();

        $fields = [
            new TextField(DBField::DESCRIPTION, "", TEXT, false, "Changelogs usually describe changes, new features, and bugfixes in a build.", null, '', $isReadOnly), // "forms/barebones/content_editable_text_field.twig"
            new BooleanField(DBField::IS_AGGREGATE_GAME, "Is Aggregate Game", false, "Defines whether this game build connects directly to controllers, or receives messages in aggregate.", null, $isReadOnly),
        ];

        $defaults = [
            DBField::IS_AGGREGATE_GAME => $gameBuild->getIsAggregateGame(),
            DBField::DESCRIPTION => $gameBuild->getDescription()
        ];

        $form = new PostForm($fields, $request, $defaults);

        $formViewData = [
            TemplateVars::GAME => $game,
            TemplateVars::GAME_BUILD => $gameBuild,
        ];

        $form->assignViewData($formViewData)->setTemplateFile("account/games/forms/form-edit-game-build.twig");

        if ($form->is_valid()) {
            if (!$gameBuild->is_live_build() && !$gameBuild->is_published()) {
                $isAggregateGame = $form->getCleanedValue(DBField::IS_AGGREGATE_GAME, 0);
                if ($gameBuild->getIsAggregateGame() != $isAggregateGame) {
                    $gameBuild->updateField(DBField::IS_AGGREGATE_GAME, $isAggregateGame)->saveEntityToDb($request);
                }
            }

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());
        }

        $session->setIfNew($this->generateSelectedGameBuildIdSessionKey(), $gameBuild->getPk());
        if ($session->is_modified())
            $session->save_session();

        $this->selectedGameBuildId = $gameBuild->getPk();

        $userIds = [];
        $userIds[] = $gameBuild->getCreatorId();

        // If this game build is published, we want to get some details about the live build to display on the details page.
        if ($gameBuild->is_published()) {
            $publishedGameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameBuild->getPublishedGameBuildId(), $game->getPk());
            if (!in_array($publishedGameBuild->getCreatorId(), $userIds))
                $userIds[] = $publishedGameBuild->getCreatorId();
        } else {
            $publishedGameBuild = [];
        }
        $gameBuild->updateField(VField::PUBLISHED_GAME_BUILD, $publishedGameBuild);

        // Get custom game assets and creator user context for this game build
        $gamesActiveCustomAssets = $this->gamesActiveCustomAssetsManager->getGameActiveCustomAssetLinksByGameIds(
            $request,
            $game->getPk(),
            $this->updateChannel,
            $gameBuild->getPk()
        );
        $customGameAssetIds = unique_array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $gamesActiveCustomAssets);

        // Get Custom Game Asset Creator User Ids
        foreach ($gameBuild->getCustomGameAssets() as $customGameAsset) {
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
        $gameBuild->setUser($users[$gameBuild->getCreatorId()]);
        if ($publishedGameBuild) {
            $publishedGameBuild->setUser($users[$publishedGameBuild->getCreatorId()]);
        }

        // Set Creator Users for all custom game assets
        foreach ($gamesActiveCustomAssets as $gameActiveCustomAsset) {
            if ($customGameAssetId = $gameActiveCustomAsset->getContextXGameAssetId()) {
                $customGameAsset = $gameBuild->getCustomGameAssetById($customGameAssetId);
                if (array_key_exists($customGameAsset->getUserId(), $users))
                    $customGameAsset->updateField(VField::USER, $users[$customGameAsset->getUserId()]);

                $gameActiveCustomAsset->updateField(VField::CUSTOM_GAME_ASSET, $customGameAsset);
            }
        }

        // Get Custom Game Data for this game build
        $gameData = $this->gamesDataManager->getGameDataByGameBuildId($request, $gameBuild->getPk(), false);

        // Get VTT Info
        $vttInterface = $gameBuild->getVttInterface($request);

        $viewData = [
            TemplateVars::PAGE_TITLE => "Build: v{$gameBuild->getGameBuildVersion()} - {$game->getDisplayName()}",
            TemplateVars::PAGE_DESCRIPTION => "Build: v{$gameBuild->getGameBuildVersion()} - {$game->getDisplayName()}",
            TemplateVars::GAME_BUILD => $gameBuild,
            TemplateVars::PAGE_IDENTIFIER => 'view-game-build',
            TemplateVars::VTT_INTERFACE => $vttInterface,
            TemplateVars::CUSTOM_GAME_ASSETS => $gamesActiveCustomAssets,
            TemplateVars::GAME_DATA => $gameData,
            TemplateVars::GAME_DATA_DEFINITION => $gameDataDefinition,
            TemplateVars::FORM => $form,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/manage-build/view-game-build.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse
     */
    protected function handle_view_game_controller(Request $request, UserEntity $user, GameEntity $game)
    {
        if (!$gameControllerId = $request->getIdForEntity($this->url_key+1))
            return $this->render_404($request);

        $gameController = $this->gamesControllersManager->getGameControllerById($request, $gameControllerId, $game->getPk(), true);

        if (!$gameController)
            return $this->render_404($request);

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameController->getGameBuildId(), $game->getPk());

        $viewData = [
            TemplateVars::PAGE_TITLE => "{$gameController->getDisplayName()} Controller: v{$gameController->getGameControllerVersion()} - {$game->getDisplayName()}",
            TemplateVars::PAGE_DESCRIPTION => "{$gameController->getDisplayName()} Controller: v{$gameController->getGameControllerVersion()} - {$game->getDisplayName()}",
            TemplateVars::PAGE_IDENTIFIER => 'view-game-controller',
            TemplateVars::GAME_CONTROLLER => $gameController,
            TemplateVars::GAME_BUILD => $gameBuild
        ];

        return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/manage-build/view-game-controller.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_upload_game_build(Request $request, UserEntity $user, GameEntity $game)
    {
        force_slash($request);

        $oldGameBuilds = $this->gamesBuildsManager->getGameBuildsByGameAndUpdateChannel($request, $game->getPk(), $this->updateChannel);
        $defaultGameBuildId = null;

        $mostRecentGameBuild = [];

        foreach ($oldGameBuilds as $oldGameBuild) {
            if (!$mostRecentGameBuild)
                $mostRecentGameBuild = $oldGameBuild;

            $oldGameBuild->updateField(VField::GAME_BUILD_IS_ACTIVE, false);
            if (array_key_exists($this->updateChannel, $this->activeGameBuildSummaries)) {
                if ($this->activeGameBuildSummaries[$this->updateChannel][DBField::GAME_BUILD_ID] == $oldGameBuild->getPk()) {
                    $oldGameBuild->updateField(VField::GAME_BUILD_IS_ACTIVE, true);
                    $defaultGameBuildId = $oldGameBuild->getPk();
                }
            }
        }

        $activeGameBuild = $game->getGameBuildByUpdateChannel($this->updateChannel);

        $mostRecentGameBuildVersion = $mostRecentGameBuild ? $mostRecentGameBuild->getGameBuildVersion() : '';
        $currentActiveGameBuildVersion = $activeGameBuild ? $activeGameBuild->getGameBuildVersion() : '';
        $isAggregateGameDefault = $activeGameBuild ? $activeGameBuild->getIsAggregateGame() : 0;

        $gameBuildVersionHelpText = "Most recently created: {$mostRecentGameBuildVersion}. Currently active: {$currentActiveGameBuildVersion}";

        $inheritOptions = [
            [
                DBField::ID => 'controllers',
                DBField::DISPLAY_NAME => 'Controller(s)'
            ],
            [
                DBField::ID => 'custom-assets',
                DBField::DISPLAY_NAME => 'Custom Assets'
            ],
            [
                DBField::ID => 'custom-data',
                DBField::DISPLAY_NAME => 'Custom Data'
            ],

        ];

        $fields = [
            new BuildVersionField(DBField::GAME_BUILD_VERSION, 'Game Build Version Number', 32, true, $gameBuildVersionHelpText),
            new BooleanField(DBField::IS_AGGREGATE_GAME, "Is Aggregate Game", false, "Indicates whether controllers communicate in aggregate or directly with game."),
            new SelectField(DBField::GAME_BUILD_ID, 'Choose a game build version to inherit from', $oldGameBuilds, false, null, 'forms/custom-fields/game_build_select_field.twig'), // 'Choose which game build to inherit controllers, custom assets and data from.'
            new MultipleBooleanField(VField::INHERIT, null, $inheritOptions, false, 'Choose to explicitly inherit, or not, game controllers, custom assets, and custom data.', 'forms/custom-fields/game_build_inherit_multiple_boolean_select.twig'), // 'Which build aspects do you want to inherit?'
        ];

        if (!$game->is_type_offline_game())
            $fields[] = new FileField(VField::GAME_BUILD_ARCHIVE, 'Game Build ZIP File', true, ['zip']);

        $defaults = [
            DBField::GAME_BUILD_ID => $defaultGameBuildId,
            DBField::IS_AGGREGATE_GAME => $isAggregateGameDefault,
            VField::INHERIT => ['controllers', 'custom-assets', 'custom-data']
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->assignViewData([
            TemplateVars::GAME => $game,
        ]);

        $form->setTemplateFile("account/games/forms/new-game-build.twig");

        if ($isValid = $form->is_valid()) {

            $gameBuildVersion = $form->getCleanedValue(DBField::GAME_BUILD_VERSION);
            $inheritFromGameBuildId = $form->getCleanedValue(DBField::GAME_BUILD_ID, []);
            $isAggregateGame = $form->getCleanedValue(DBField::IS_AGGREGATE_GAME, 0);

            /** @var array $inheritOptions */
            $inheritOptions = $form->getCleanedValue(VField::INHERIT);

            if ($this->gamesBuildsManager->checkGameBuildVersionExists($request, $game->getPk(), $this->updateChannel, $gameBuildVersion)) {
                $isValid = false;
                $form->set_error("Version '{$gameBuildVersion}' already exists on this update channel.", DBField::GAME_BUILD_VERSION);
            }

            $dbConnection = $request->db->get_connection();
            $dbConnection->begin();

            if ($game->is_type_offline_game()) {
                $newGameBuild = $this->gamesBuildsManager->createNewGameBuild(
                    $request,
                    $game->getPk(),
                    $this->updateChannel,
                    $gameBuildVersion,
                    null,
                    0,
                    $isAggregateGame
                );

            } else {
                $gameBuildArchiveFileId = $form->getCleanedValue(VField::GAME_BUILD_ARCHIVE)['upload_id_game_build_archive'];
                $sourceFile = UploadsHelper::path_from_file_id($gameBuildArchiveFileId);

                Modules::load_helper(Helpers::ZIP_UPLOAD);

                $destinationFolder = $this->gamesBuildsManager->generateDestinationFolder($request, $game);

                $zipUploadHelper = new ZipUploadHelper(
                    $sourceFile,
                    $destinationFolder,
                    $this->gamesBuildsManager->getZipRootFileIdentifier()
                );

                if ($isValid && $zipUploadHelper->extract() && $zipUploadHelper->getRootFileFound() && $filePaths = $zipUploadHelper->getFilePaths()) {

                    $newGameBuild = $this->gamesBuildsManager->createNewGameBuild(
                        $request,
                        $game->getPk(),
                        $this->updateChannel,
                        $gameBuildVersion,
                        $zipUploadHelper->getArchiveVersionHash(),
                        0,
                        $isAggregateGame
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
                                $newGameBuild->getUpdateChannel()
                            );
                            $newGameBuild->setGameAsset($gameBuildAsset);

                        } catch (Exception $e) {
                            $form->set_error("An error has occurred, please try again later.", VField::GAME_BUILD_ARCHIVE);
                            $dbConnection->rollback();
                            $isValid = false;
                            break;
                        }
                    }

                }

                FilesToolkit::clear_directory($destinationFolder);
                if (is_dir($destinationFolder))
                    rmdir($destinationFolder);
                UploadsHelper::delete_upload($gameBuildArchiveFileId);

                if ($isValid) {
                    if (!$zipUploadHelper->getZipOpened()) {
                        $isValid = false;
                        $form->set_error('Zip archive failed to open.', VField::GAME_BUILD_ARCHIVE);
                    } else {
                        if (!$zipUploadHelper->getProcessedFiles()) {
                            $isValid = false;
                            $form->set_error('No files found in zip.', VField::GAME_BUILD_ARCHIVE);
                        } else {
                            if (!$zipUploadHelper->getRootFileFound()) {
                                $isValid = false;
                                $form->set_error("{$this->gamesBuildsManager->getZipRootFileIdentifier()} not found in archive.", VField::GAME_BUILD_ARCHIVE);
                            }
                        }
                    }
                }
            }

            if ($isValid) {

                if ($newGameBuild->getCustomDataDefinition($request)->is_moddable()) {
                    $newGameBuild->updateField(DBField::CAN_MOD, 1)->saveEntityToDb($request);
                }

                if ($this->updateChannel == GamesManager::UPDATE_CHANNEL_DEV)
                    $nextPath = $game->getEditUrl("/{$this->updateChannel}/set-active-build/{$newGameBuild->getPk()}");
                else
                    $nextPath = $game->getEditUrl("/{$this->updateChannel}/view-game-build/{$newGameBuild->getPk()}");


                if ($inheritFromGameBuildId) {
                    $oldGameBuild = $oldGameBuilds[$inheritFromGameBuildId];

                    if (is_array($inheritOptions)) {
                        if (in_array('controllers', $inheritOptions)) {
                            $this->gamesControllersManager->cloneGameBuildControllers($request, $oldGameBuild, $newGameBuild);
                        }

                        if (in_array('custom-assets', $inheritOptions)) {
                            $this->gamesActiveCustomAssetsManager->cloneCustomGameBuildAssets($request, $oldGameBuild, $newGameBuild);
                        }

                        if (in_array('custom-data', $inheritOptions)) {
                            $this->gamesDataManager->cloneGameBuildData($request, $oldGameBuild, $newGameBuild);
                        }
                    }

                }

                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_UPLOAD_GAME_BUILD,
                    $game->getPk(),
                    $newGameBuild->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $this->activeOrganization,
                    $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                $dbConnection->commit();

                $link = $game->getEditUrl("/{$this->updateChannel}/view-game-build/{$newGameBuild->getPk()}");

                if ($request->settings()->is_prod()) {

                    Modules::load_helper(Helpers::SLACK);

                    $slackMessage = "@channel {$user->getEmailAddress()} uploaded a new game build: {$newGameBuild->getGameBuildVersion()}";

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

                return $form->handleRenderJsonSuccessResponse($nextPath);
            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form,
        ];

        return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/upload-game-build.twig');

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_publish_build_to_live(Request $request, UserEntity $user, GameEntity $game)
    {
        if (!$gameBuildId = $request->getIdForEntity($this->url_key+1))
            throw new Http404();

        if (!in_array($gameBuildId, array_keys($this->channelGameBuildOptions)))
            return $this->render_404($request);

        if (!$gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameBuildId, $game->getPk(), true))
            throw new Http404();

        $changeSummary = [
            'custom_assets' => [
            ],
            'custom_data' => [
            ],
            'controllers' => [
            ]
        ];

        $gameBuildCustomAssets = $this->gamesActiveCustomAssetsManager->getActiveGameActiveCustomAssetLinksByGameBuildId(
            $request,
            $gameBuildId
        );
        if ($gameBuildCustomAssets)
            $gameBuildCustomAssets = array_index($gameBuildCustomAssets, DBField::SLUG);
        $gameBuild->updateField(VField::CUSTOM_GAME_ASSETS, $gameBuildCustomAssets);


        $customData = $this->gamesDataManager->getGameDataByGameId($request, $game->getPk(), $gameBuild->getUpdateChannel(), $gameBuildId);
        if ($customData)
            $customData = array_index($customData, DBField::KEY);
        $gameBuild->updateField(VField::CUSTOM_DATA, $customData);


        $fields = [
            new HiddenField(DBField::GAME_BUILD_ID, 'Game Build', 0, true),
            new BooleanField(DBField::IS_PUBLIC, 'Set Active', false, 'If checked, sets the build live in the host immediately.')
        ];

        if (array_key_exists(GamesManager::UPDATE_CHANNEL_LIVE, $this->activeGameBuildSummaries)) {
            $liveGameBuildId = $this->activeGameBuildSummaries[GamesManager::UPDATE_CHANNEL_LIVE][DBField::GAME_BUILD_ID];

            $liveGameBuild = $this->gamesBuildsManager->getGameBuildById($request, $liveGameBuildId, $game->getPk(), true);

            $liveGameBuildCustomAssets = $this->gamesActiveCustomAssetsManager->getActiveGameActiveCustomAssetLinksByGameBuildId(
                $request,
                $liveGameBuildId
            );
            if ($liveGameBuildCustomAssets)
                $liveGameBuildCustomAssets = array_index($liveGameBuildCustomAssets, DBField::SLUG);

            $liveGameBuild->updateField(VField::CUSTOM_GAME_ASSETS, $liveGameBuildCustomAssets);

            $newCustomAssets = 0;
            $changedCustomAssets = 0;
            $removedCustomAssets = 0;
            foreach ($gameBuildCustomAssets as $slug => $gameBuildCustomAsset) {
                if (!array_key_exists($slug, $liveGameBuildCustomAssets)) {
                    $newCustomAssets++;
                } else {
                    if ($gameBuildCustomAsset->getContextXGameAssetId() != $liveGameBuildCustomAssets[$slug]->getContextXGameAssetId())
                        $changedCustomAssets++;
                }

            }
            foreach ($liveGameBuildCustomAssets as $slug => $liveGameBuildCustomAsset) {
                if (!array_key_exists($slug, $gameBuildCustomAssets))
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
            $liveCustomData = $this->gamesDataManager->getGameDataByGameId($request, $game->getPk(), $liveGameBuild->getUpdateChannel(), $liveGameBuildId);
            if ($liveCustomData)
                $liveCustomData = array_index($customData, DBField::KEY);
            $liveGameBuild->updateField(VField::CUSTOM_DATA, $liveCustomData);

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

            $newControllers = 0;
            $changedControllers = 0;
            $removedControllers = 0;

            $gameBuildControllers = $gameBuild->getGameControllers();
            if ($gameBuildControllers)
                $gameBuildControllers = array_index($gameBuildControllers, DBField::GAME_CONTROLLER_TYPE_ID);

            $liveGameBuildControllers = $liveGameBuild->getGameControllers();
            if ($liveGameBuildControllers)
                $liveGameBuildControllers = array_index($liveGameBuildControllers, DBField::GAME_CONTROLLER_TYPE_ID);

            foreach ($gameBuildControllers as $typeId => $gameBuildController) {
                if (!array_key_exists($typeId, $liveGameBuildControllers))
                    $newControllers++;
                else {
                    if ($gameBuildController->getVersionHash() != $liveGameBuildControllers[$typeId]->getVersionHash())
                        $changedControllers++;
                }
            }

            foreach ($liveGameBuildControllers as $typeId => $liveGameBuildController) {
                if (!array_key_exists($typeId, $gameBuildControllers))
                    $removedControllers++;
            }

            if ($newControllers || $changedControllers || $removedControllers) {

                $changes = [];

                if ($newControllers)
                    $changes[] = "{$newControllers} new";

                if ($changedControllers)
                    $changes[] = "{$changedControllers} updated";

                if ($removedControllers)
                    $changes[] = "{$removedControllers} removed";

                if ($changes)
                    $changeSummary['controllers'] = join(', ', $changes);

                $changeSummary['controllers']['new'] = $newControllers;
                $changeSummary['controllers']['changed'] = $changedControllers;
                $changeSummary['controllers']['removed'] = $removedControllers;
            }


        } else {
            $liveGameBuild = [];
        }

        $defaults = [
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::IS_PUBLIC => true
        ];

        $form = new PostForm($fields, $request, $defaults);

        $formViewData = [
            TemplateVars::GAME => $game,
            TemplateVars::GAME_BUILD => $gameBuild
        ];

        $form->assignViewData($formViewData)->setTemplateFile('account/games/forms/publish-game-build.twig');

        if ($isValid = $form->is_valid()) {

            $devGameBuildId = $form->getCleanedValue(DBField::GAME_BUILD_ID);
            $setActive = $form->getCleanedValue(DBField::IS_PUBLIC, 0);

            if ($devGameBuildId != $gameBuildId) {
                $isValid = false;
                $form->set_error('Game Build Id Mismatch.');
            }

            if ($isValid) {
                // Create New Game Build
                $liveGameBuild = $this->gamesBuildsManager->createNewGameBuild(
                    $request,
                    $gameBuild->getGameId(),
                    GamesManager::UPDATE_CHANNEL_LIVE,
                    $gameBuild->getGameBuildVersion(),
                    $gameBuild->getVersionHash(),
                    $gameBuild->getCanMod(),
                    $gameBuild->getIsAggregateGame(),
                    $gameBuild->getDescription()
                );

                // Clone Game Assets
                $this->gamesAssetsManager->cloneGameBuildAssets($request, $gameBuild, $liveGameBuild);
                // Clone Game Controllers
                $this->gamesControllersManager->cloneGameBuildControllers($request, $gameBuild, $liveGameBuild);
                // Clone Custom Game Assets
                $this->gamesActiveCustomAssetsManager->cloneCustomGameBuildAssets($request, $gameBuild, $liveGameBuild);
                // Clone Custom Game Data
                $this->gamesDataManager->cloneGameBuildData($request, $gameBuild, $liveGameBuild);
                // Update Game Build to published
                $gameBuild->updateField(DBField::PUBLISHED_GAME_BUILD_ID, $liveGameBuild->getPk())->saveEntityToDb($request);

                // Track Activity
                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_PUBLISH_GAME_BUILD,
                    $gameBuild->getPk(),
                    $liveGameBuild->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $this->activeOrganization,
                    $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                if ($request->settings()->is_prod()) {

                    $link = $game->getEditUrl("/live/view-game-build/{$liveGameBuild->getPk()}");

                    Modules::load_helper(Helpers::SLACK);

                    $slackMessage = "@channel {$user->getEmailAddress()} published a new game build to live: {$liveGameBuild->getGameBuildVersion()}";

                    $slackAttachment = new SlackAttachment(
                        $user,
                        "{$game->getDisplayName()} - v{$liveGameBuild->getGameBuildVersion()}",
                        $link,
                        null,
                        new SlackActionButton('View', $link),
                        new SlackField('Environment', $request->host),
                        new SlackField('Game', $game->getDisplayName()),
                        new SlackField('Request ID', $request->requestId)
                    );

                    SlackHelper::sendCard($slackMessage, $slackAttachment);
                }

                $request->user->sendFlashMessage('Game Build Published Successfully', MSG_SUCCESS);

                if ($setActive)
                    $nextUrl = $game->getEditUrl("/{$liveGameBuild->getUpdateChannel()}/set-active-build/{$liveGameBuild->getPk()}");
                else
                    $nextUrl = $game->getEditUrl("/{$liveGameBuild->getUpdateChannel()}/builds");

                return $form->handleRenderJsonSuccessResponse($nextUrl);

            } else {
                return $form->handleRenderJsonErrorResponse();
            }



        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::GAME_BUILD => $gameBuild,
            TemplateVars::FORM => $form,
            TemplateVars::LIVE_GAME_BUILD => $liveGameBuild,
            TemplateVars::CHANGE_SUMMARY => $changeSummary
        ];

        return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/manage-build/publish-build-live.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_upload_game_controller(Request $request, UserEntity $user, GameEntity $game)
    {
        force_slash($request);

        $gameControllerTypes = $this->gamesControllersTypesManager->getAllGameControllerTypes($request);

        $gameBuildId = $request->getIdForEntity($this->url_key+1);
        if (!in_array($gameBuildId, array_keys($this->channelGameBuildOptions)))
            return $this->render_404($request);

        $gameControllerTypeId = $request->getIdForEntity($this->url_key+2);
        if (!$gameControllerTypeId)
            $gameControllerTypeId = GamesControllersTypesManager::ID_PLAYER;

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $gameBuildId, $game->getPk(), true);

        $controllersText = [];

        $activeGameControllers = $gameBuild->getGameControllers();

        foreach ($activeGameControllers as $gameController) {
            $controllersText[] = "{$gameController->getDisplayName()} (v{$gameController->getGameControllerVersion()})";
        }

        $controllersText = join(', ', $controllersText);

        $inheritControllersHelpText = "Current controllers: {$controllersText}.";

        $fields = [
            new HiddenField(DBField::GAME_BUILD_ID, 'Game Build Id', 0, true),
            new SelectField(DBField::GAME_CONTROLLER_TYPE_ID, 'Game Controller Type', $gameControllerTypes),
            new GameControllerBuildVersionField(DBField::GAME_CONTROLLER_VERSION, 'Game Controller Version Number', 32, true, $inheritControllersHelpText),
            new FileField(VField::GAME_BUILD_CONTROLLER_ARCHIVE, 'Game Controller ZIP File', true, ['zip']),
        ];

        $defaults = [
            DBField::GAME_BUILD_ID => $gameBuild->getPk(),
            DBField::GAME_CONTROLLER_TYPE_ID => $gameControllerTypeId
        ];


        $form = new PostForm($fields, $request, $defaults);

        $form->assignViewData([
            TemplateVars::GAME => $game,
            TemplateVars::GAME_BUILD => $gameBuild
        ]);

        $form->setTemplateFile("account/games/forms/new-game-controller.twig");

        if ($isValid = $form->is_valid()) {

            $gameBuildId = $form->getCleanedValue(DBField::GAME_BUILD_ID);
            $gameControllerTypeId = $form->getCleanedValue(DBField::GAME_CONTROLLER_TYPE_ID);
            $gameControllerVersion = $form->getCleanedValue(DBField::GAME_CONTROLLER_VERSION);
            $gameBuildControllerArchiveFileId = $form->getCleanedValue(VField::GAME_BUILD_CONTROLLER_ARCHIVE)['upload_id_game_build_controller_archive'];

            if ($this->gamesControllersManager->checkGameControllerVersionExists($request, $game->getPk(), $this->updateChannel, $gameControllerTypeId, $gameControllerVersion, $gameBuildId)) {
                $isValid = false;
                $form->set_error("Version '{$gameControllerVersion}' already exists on this update channel for build: {$gameBuild->getGameBuildVersion()}.", DBField::GAME_CONTROLLER_VERSION);
            }

            $sourceFile = UploadsHelper::path_from_file_id($gameBuildControllerArchiveFileId);

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
                    $gameBuildId,
                    $this->updateChannel,
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
                            $this->updateChannel
                        );
                        $gameControllerAssets[] = $gameControllerAsset;
                        $md5s[] = $gameControllerAsset->getMd5();

                    } catch (Exception $e) {
                        $form->set_error("An error has occurred, please try again later.", VField::GAME_BUILD_CONTROLLER_ARCHIVE);
                        $dbConnection->rollback();
                        $isValid = false;
                        break;
                    }
                }

                if ($md5s) {
                    sort($md5s);
                    foreach ($md5s as $md5) {
                        $versionHash .= $md5;
                    }
                }

                $nextPath = $game->getEditUrl("/{$this->updateChannel}/view-game-build/{$gameBuild->getPk()}?active_build_tab=controllers");

            }

            FilesToolkit::clear_directory($destinationFolder);
            if (is_dir($destinationFolder))
                rmdir($destinationFolder);
            UploadsHelper::delete_upload($gameBuildControllerArchiveFileId);

            if ($isValid) {
                if (!$zipUploadHelper->getZipOpened()) {
                    $isValid = false;
                    $form->set_error('Zip archive failed to open.', VField::GAME_BUILD_CONTROLLER_ARCHIVE);
                } else {
                    if (!$zipUploadHelper->getProcessedFiles()) {
                        $isValid = false;
                        $form->set_error('No files found in zip.', VField::GAME_BUILD_CONTROLLER_ARCHIVE);
                    } else {
                        $mainJsFile = GamesControllersManager::MAIN_JS_FILE_NAME;
                        $mainCssFile = GamesControllersManager::MAIN_CSS_FILE_NAME;

                        if (!$zipUploadHelper->hasFileName($mainJsFile)) {
                            $isValid = false;
                            $form->set_error("Primary JS file ({$mainJsFile}) not found in archive.", VField::GAME_BUILD_CONTROLLER_ARCHIVE);
                        }

                        if ($isValid && !$zipUploadHelper->hasFileName($mainCssFile)) {
                            $isValid = false;
                            $form->set_error("Primary CSS file ({$mainCssFile}) not found in archive.", VField::GAME_BUILD_CONTROLLER_ARCHIVE);
                        }

                        if ($isValid && !$zipUploadHelper->getRootFileFound()) {
                            $isValid = false;
                            $form->set_error("{$this->gamesBuildsManager->getZipRootFileIdentifier()} not found in archive.", VField::GAME_BUILD_CONTROLLER_ARCHIVE);
                        }
                    }
                }
            }

            if ($isValid) {

                $this->gamesControllersManager->deactivateOldGameControllerTypeForBuild(
                    $request,
                    $gameBuild->getPk(),
                    $gameController->getGameControllerTypeId(),
                    $gameController->getPk()
                );

                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_UPLOAD_GAME_CONTROLLER,
                    $gameBuild->getPk(),
                    $gameController->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $this->activeOrganization,
                    $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );


                $dbConnection->commit();

                if ($versionHash)
                    $gameController->updateField(DBField::VERSION_HASH, sha1($versionHash))->saveEntityToDb($request);


                if ($request->settings()->is_prod()) {

                    $link = $game->getEditUrl("/{$this->updateChannel}/view-game-build/{$gameBuild->getPk()}?active_build_tab=controllers");

                    Modules::load_helper(Helpers::SLACK);

                    $slackMessage = "@channel {$user->getEmailAddress()} uploaded a new controller build ({$gameController->getGameControllerVersion()}) for build: {$gameBuild->getGameBuildVersion()}";

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

                return $form->handleRenderJsonSuccessResponse($nextPath);
            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post())
                return $form->handleRenderJsonErrorResponse();
        }

        $viewData = [
            TemplateVars::FORM => $form,
            TemplateVars::GAME_BUILD => $gameBuild
        ];

        return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/manage-build/upload-game-controller.twig');

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @param $selectedGameBuildId
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_batch_upload_custom_game_assets(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $selectedGameBuildId, $game->getPk(), true);

        // Get custom game assets and creator user context for this game build
        $gamesActiveCustomAssets = $this->gamesActiveCustomAssetsManager->getGameActiveCustomAssetLinksByGameIds(
            $request,
            $game->getPk(),
            $this->updateChannel,
            $gameBuild->getPk()
        );
        $customGameAssetIds = unique_array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $gamesActiveCustomAssets);

        $customGameAssets = $this->gamesAssetsManager->getCustomGameAssetsByCustomGameAssetIds($request, $customGameAssetIds, $game->getPk(), $gameBuild->getUpdateChannel());

        if ($customGameAssets)
            $customGameAssets = array_index($customGameAssets, VField::CUSTOM_GAME_ASSET_ID);

        foreach ($gamesActiveCustomAssets as $gamesActiveCustomAsset) {
            if (array_key_exists($gamesActiveCustomAsset->getContextXGameAssetId(), $customGameAssets))
                $gamesActiveCustomAsset->setGameAsset($customGameAssets[$gamesActiveCustomAsset->getContextXGameAssetId()]);
            else
                $gamesActiveCustomAsset->updateField(VField::GAME_ASSET, []);
        }

        $fields = [];
        $form = new PostForm($fields, $request);

        $customGameAssetsJsObjects = $gamesActiveCustomAssets ? DBManagerEntity::extractJsonDataArrays(array_index($gamesActiveCustomAssets, DBField::SLUG)) : [];

        $pageJsViewData = [
            TemplateVars::CUSTOM_GAME_ASSETS => $customGameAssetsJsObjects,
            TemplateVars::UPLOAD_URL => $game->getEditUrl("/{$gameBuild->getUpdateChannel()}/upload-custom-game-asset/"),
            TemplateVars::GAME_BUILD => $gameBuild->getJSONData(true),
            TemplateVars::REDIRECT_URL => $game->getEditUrl("/{$gameBuild->getUpdateChannel()}/view-game-build/{$gameBuild->getPk()}?active_build_tab=custom-assets")
        ];

        $this->page_js_data->assign($pageJsViewData);

        $viewData = [
            TemplateVars::PAGE_TITLE => "Batch Upload Assets - {$game->getDisplayName()}",
            TemplateVars::PAGE_DESCRIPTION => "Batch Upload Assets - {$game->getDisplayName()}",
            TemplateVars::PAGE_IDENTIFIER => 'batch-upload-custom-assets',
            TemplateVars::GAME_BUILD => $gameBuild,
            TemplateVars::FORM => $form,
        ];

        return $this->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/manage-build/batch-upload-custom-assets.twig');
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @param $selectedGameBuildId
     */
    protected function handle_batch_download_custom_game_asset_files(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $selectedGameBuildId, $game->getPk(), true);

        // Get custom game assets and creator user context for this game build
        $gamesActiveCustomAssets = $this->gamesActiveCustomAssetsManager->getGameActiveCustomAssetLinksByGameIds(
            $request,
            $game->getPk(),
            $this->updateChannel,
            $gameBuild->getPk()
        );

        if ($gamesActiveCustomAssets)
            $gamesActiveCustomAssets = array_index($gamesActiveCustomAssets, DBField::SLUG);

        $customGameAssetIds = unique_array_extract(DBField::CONTEXT_X_GAME_ASSET_ID, $gamesActiveCustomAssets);

        $customGameAssets = $this->gamesAssetsManager->getCustomGameAssetsByCustomGameAssetIds($request, $customGameAssetIds, $game->getPk(), $gameBuild->getUpdateChannel());

        $buildVersion = str_replace('.', '-', $gameBuild->getGameBuildVersion());

        $fileName = "{$game->getSlug()}_{$this->updateChannel}-build-{$gameBuild->getPk()}-v-{$buildVersion}_custom-assets_{$request->getCurrentSqlTime('Y-m-d-H-i-s')}.zip";

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
                    ActivityTypesManager::ACTIVITY_TYPE_USER_DOWNLOAD_GAME_BUILD_CUSTOM_ASSETS_ZIP,
                    $game->getPk(),
                    $gameBuild->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $this->activeOrganization,
                    $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                return new DownloadZipResponse($content, $fileName);
            }

        }

        return $this->render_404($request, 'No Files Found');

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @param $selectedGameBuildId
     * @return DownloadZipResponse|HtmlResponse
     */
    protected function handle_batch_download_game_build_asset_files(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $selectedGameBuildId, $game->getPk(), true);

        $buildVersion = str_replace('.', '-', $gameBuild->getGameBuildVersion());

        $fileName = "{$game->getSlug()}_{$this->updateChannel}-build-{$gameBuild->getPk()}-v-{$buildVersion}_{$request->getCurrentSqlTime('Y-m-d-H-i-s')}.zip";

        $zipArchive = new ZipArchive();
        $parentFolder = "{$request->settings()->getUploadDir()}/{$request->requestId}";
        $filePath = "{$parentFolder}/{$fileName}";

        if (!is_dir($parentFolder))
            mkdir($parentFolder, 0700, true);

        $zipArchive->open($filePath, ZipArchive::CREATE);

        $hasFileData = false;

        foreach ($gameBuild->getGameAssets() as $gameAsset) {
            try {

                $gameAssetFileData = $request->s3->readIntoMemory($gameAsset->getBucket(), $gameAsset->getBucketKey());

                $assetFileName = "{$gameAsset->getFolderPath()}{$gameAsset->getFileName()}";

                $zipArchive->addFromString($assetFileName, $gameAssetFileData);

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
                    ActivityTypesManager::ACTIVITY_TYPE_USER_DOWNLOAD_GAME_BUILD_ZIP,
                    $game->getPk(),
                    $gameBuild->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $this->activeOrganization,
                    $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                return new DownloadZipResponse($content, $fileName);
            }

        }

        return $this->render_404($request, 'No Files Found');

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @param $selectedGameBuildId
     * @return DownloadZipResponse|HtmlResponse
     */
    protected function handle_batch_download_game_controller_asset_files(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $gameControllerId = $request->getIdForEntity($this->url_key+1);
        if (!$gameControllerId)
            return $this->render_404($request);

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $selectedGameBuildId, $game->getPk(), true);

        $gameController = [];

        foreach ($gameBuild->getGameControllers() as $gameBuildController) {
            if ($gameBuildController->getPk() == $gameControllerId)
                $gameController = $gameBuildController;
        }

        if (!$gameController)
            return $this->render_404($request);

        $gameBuildVersion = str_replace('.', '-', $gameBuild->getGameBuildVersion());
        $controllerBuildVersion = str_replace('.', '-', $gameController->getGameControllerVersion());
        $typeSlug = slugify($gameController->getDisplayName());

        $fileName = "{$game->getSlug()}_{$this->updateChannel}-build-{$gameBuild->getPk()}-v-{$gameBuildVersion}_controller-{$typeSlug}-v-{$controllerBuildVersion}_{$request->getCurrentSqlTime('Y-m-d-H-i-s')}.zip";

        $zipArchive = new ZipArchive();
        $parentFolder = "{$request->settings()->getUploadDir()}/{$request->requestId}";
        $filePath = "{$parentFolder}/{$fileName}";

        if (!is_dir($parentFolder))
            mkdir($parentFolder, 0700, true);

        $zipArchive->open($filePath, ZipArchive::CREATE);

        $hasFileData = false;

        foreach ($gameController->getGameControllerAssets() as $gameControllerAsset) {
            try {

                $gameAssetFileData = $request->s3->readIntoMemory($gameControllerAsset->getBucket(), $gameControllerAsset->getBucketKey());

                $assetFileName = "{$gameControllerAsset->getFolderPath()}{$gameControllerAsset->getFileName()}";

                $zipArchive->addFromString($assetFileName, $gameAssetFileData);

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
                    ActivityTypesManager::ACTIVITY_TYPE_USER_DOWNLOAD_GAME_BUILD_CONTROLLER_ASSETS_ZIP,
                    $gameBuild->getPk(),
                    $gameController->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $this->activeOrganization,
                    $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                return new DownloadZipResponse($content, $fileName);
            }

        }

        return $this->render_404($request, 'No Files Found');

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_view_custom_game_asset(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        if (!$customGameAssetSlug = $request->getSlugForEntity($this->url_key+1))
            return $this->render_404($request);

        $gameActiveCustomAsset = $this->gamesActiveCustomAssetsManager->getActiveGameActiveCustomAssetLinkByGameIdAndSlug(
            $request,
            $game->getPk(),
            $this->updateChannel,
            $customGameAssetSlug,
            $selectedGameBuildId
        );

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $selectedGameBuildId, $game->getPk());

        if (!$gameActiveCustomAsset)
            return $this->render_404($request);

        $gameAsset = [];

        if ($gameActiveCustomAsset->getContextXGameAssetId()) {
            foreach ($gameBuild->getCustomGameAssets() as $customGameAsset) {
                if ($customGameAsset->getCustomGameAssetId() == $gameActiveCustomAsset->getContextXGameAssetId()) {
                    $gameAsset = $customGameAsset;
                    break;
                }
            }
        }

        $customGameAssetsHistory = $this->gamesAssetsManager->getCustomGameAssetsHistoryBySlug(
            $request,
            $game->getPk(),
            $this->updateChannel,
            $customGameAssetSlug
        );

        $fields = [
            new ExtendedSlugField(DBField::SLUG, 'Custom Game Asset Slug', 0, true, 'Note: changing this slug does not update dependencies in game. Use with caution!'),
            new BooleanField(DBField::IS_PUBLIC, 'Shared with game controller', false, 'If checked, this asset is accessible via non-secure public URL by game controllers.')
        ];

        $defaults = [
            DBField::SLUG => $gameActiveCustomAsset->getSlug(),
            DBField::IS_PUBLIC => $gameActiveCustomAsset->getIsPublic()
        ];

        $form = new PostForm($fields, $request, $defaults);

        $form->setTemplateFile("account/games/forms/edit-custom-asset.twig");
        $form->assignViewData([
            TemplateVars::GAME => $game,
            TemplateVars::CUSTOM_GAME_ASSET_LINK => $gameActiveCustomAsset,
            TemplateVars::GAME_BUILD => $gameBuild,
            TemplateVars::UPDATE_CHANNEL => $this->updateChannel,
        ]);

        if ($isValid = $form->is_valid()) {

            $newSlug = strtolower($form->getCleanedValue(DBField::SLUG));
            $isPublic = $form->getCleanedValue(DBField::IS_PUBLIC, 0);

            if ($newSlug != $customGameAssetSlug) {
                $newGameActiveCustomAsset = $this->gamesActiveCustomAssetsManager->getActiveGameActiveCustomAssetLinkByGameIdAndSlug(
                    $request,
                    $game->getPk(),
                    $this->updateChannel,
                    $newSlug
                );

                if ($newGameActiveCustomAsset) {
                    $form->set_error("Slug already in use.", DBField::SLUG);
                    $isValid = false;
                }

            }

            if ($isValid) {

                $link = $game->getEditUrl("/{$this->updateChannel}/view-custom-game-asset/{$newSlug}?game_build_id={$gameBuild->getPk()}");

                if ($isPublic != $gameActiveCustomAsset->getIsPublic())
                    $gameActiveCustomAsset->updateField(DBField::IS_PUBLIC, $isPublic)->saveEntityToDb($request);

                if ($newSlug != $customGameAssetSlug) {
                    $this->gamesActiveCustomAssetsManager->renameCustomGameAssetSlug($request, $gameActiveCustomAsset, $newSlug);
                    $request->user->sendFlashMessage('Updated Custom Game Asset Slug Successfully');

                    if ($request->settings()->is_prod()) {

                        Modules::load_helper(Helpers::SLACK);

                        $slackMessage = "@channel {$user->getEmailAddress()} renamed a custom game asset: {$customGameAssetSlug} -> {$newSlug}";

                        $slackAttachment = new SlackAttachment(
                            $user,
                            $newSlug,
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

                return $form->handleRenderJsonSuccessResponse($link);

            } else {
                return $form->handleRenderJsonErrorResponse();
            }

        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }


        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'view-custom-game-asset',
            TemplateVars::GAME_ASSET => $gameAsset,
            TemplateVars::GAME_ASSETS => $customGameAssetsHistory,
            TemplateVars::CUSTOM_GAME_ASSET_LINK => $gameActiveCustomAsset,
            TemplateVars::FORM => $form,
            TemplateVars::GAME_BUILD => $gameBuild,
        ];

        return $this->setUseTooltip()->renderPageResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/manage-build/view-custom-game-asset.twig');

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_upload_custom_game_asset(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        force_slash($request);

        $customGameAssetSlug = $request->getSlugForEntity($this->url_key+1);

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $selectedGameBuildId, $game->getPk());

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
            TemplateVars::GAME => $game
        ]);

        $form->setTemplateFile("account/games/forms/new-custom-game-asset.twig");

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

            $gameActiveCustomAsset = $this->gamesActiveCustomAssetsManager->getGameActiveCustomAssetLinkByGameIdAndSlug(
                $request,
                $game->getPk(),
                $this->updateChannel,
                $slug,
                $gameBuild->getPk()
            );

            // Check if
            if (!$replaceSlug && $gameActiveCustomAsset) {
                if ($gameActiveCustomAsset->is_active()) {
                    $isValid = false;
                    $form->set_error("Slug '{$slug}' is already in use, choose another slug or opt to replace the active file.", DBField::SLUG);
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
                    $this->updateChannel,
                    $slug,
                    $customGameAssetFileId
                );

                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_UPLOAD_CUSTOM_GAME_ASSET_FILE,
                    $game->getPk(),
                    $customGameAsset->getCustomGameAssetId(),
                    $user->getUiLanguageId(),
                    $user
                );

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $this->activeOrganization,
                    $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                $gameActiveCustomAsset = $this->gamesActiveCustomAssetsManager->createUpdateGameActiveCustomAsset(
                    $request,
                    $game->getPk(),
                    $this->updateChannel,
                    $selectedGameBuildId,
                    $slug,
                    $customGameAsset->getCustomGameAssetId(),
                    $isPublic
                );

                if (!$request->settings()->is_dev()) {

                    $link = "{$game->getEditUrl()}/{$this->updateChannel}/view-custom-game-asset/{$gameActiveCustomAsset->getSlug()}?game_build_id={$gameBuild->getPk()}";

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

                if ($customGameAssetSlug || (!$customGameAssetSlug && $replaceSlug))
                    $next = "{$game->getEditUrl()}/{$this->updateChannel}/view-custom-game-asset/{$gameActiveCustomAsset->getSlug()}?game_build_id={$gameBuild->getPk()}";
                else
                    $next = "{$game->getEditUrl()}/{$this->updateChannel}/view-game-build/{$gameBuild->getPk()}?active_build_tab=custom-assets";

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
            TemplateVars::GAME_BUILD => $gameBuild,
        ];

        return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), 'account/games/manage-game/manage-build/upload-custom-game-asset.twig');

    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|HttpResponseRedirect
     */
    protected function handle_set_custom_asset_active(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $customGameAssetSlug = $request->getSlugForEntity($this->url_key+1);
        $customGameAssetId = $request->getIdForEntity($this->url_key+2);

        if (!$customGameAssetSlug || !$customGameAssetId) {
            return $this->render_404($request);
        }

        $activeCustomGameAsset = $this->gamesActiveCustomAssetsManager->getActiveGameActiveCustomAssetLinkByGameIdAndSlug(
            $request,
            $game->getPk(),
            $this->updateChannel,
            $customGameAssetSlug,
            $selectedGameBuildId
        );

        if (!$activeCustomGameAsset)
            return $this->render_404($request);

        $customGameAsset = $this->gamesAssetsManager->getCustomGameAssetByCustomGameAssetId(
            $request,
            $game->getPk(),
            $customGameAssetId,
            $this->updateChannel
        );

        if (!$customGameAsset)
            return $this->render_404($request);

        if ($customGameAsset->getCustomGameAssetId() == $activeCustomGameAsset->getContextXGameAssetId())
            return $this->render_404($request);

        $activeCustomGameAsset->updateField(DBField::CONTEXT_X_GAME_ASSET_ID, $customGameAsset->getCustomGameAssetId())->saveEntityToDb($request);

        $activity = $this->activityManager->trackActivity(
            $request,
            ActivityTypesManager::ACTIVITY_TYPE_USER_SET_CUSTOM_GAME_ASSET_ACTIVE,
            $activeCustomGameAsset->getPk(),
            $customGameAsset->getCustomGameAssetId(),
            $user->getUiLanguageId(),
            $user
        );

        $this->organizationsActivityManager->trackOrganizationActivity(
            $request,
            $activity,
            $this->activeOrganization,
            $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
        );

        return $this->redirect($request->getRedirectBackUrl());
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_delete_custom_asset(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $customGameAssetSlug = $request->getSlugForEntity($this->url_key+1);

        if (!$customGameAssetSlug)
            return $this->render_404($request);

        $activeCustomGameAsset = $this->gamesActiveCustomAssetsManager->getActiveGameActiveCustomAssetLinkByGameIdAndSlug(
            $request,
            $game->getPk(),
            $this->updateChannel,
            $customGameAssetSlug,
            $selectedGameBuildId
        );

        $gameBuild = $this->gamesBuildsManager->getGameBuildById($request, $selectedGameBuildId, $game->getPk());

        if (!$activeCustomGameAsset)
            return $this->render_404($request);

        $fields = [
            new HiddenField(DBField::SLUG, 'Slug')
        ];

        $defaults = [
            DBField::SLUG => $customGameAssetSlug
        ];

        $form = new PostForm($fields, $request, $defaults);

        if ($form->is_valid()) {

            $this->gamesActiveCustomAssetsManager->deleteGameActiveCustomAssetLink(
                $request,
                $game->getPk(),
                $this->updateChannel,
                $selectedGameBuildId,
                $customGameAssetSlug
            );

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_DELETE_CUSTOM_GAME_ASSET,
                $game->getPk(),
                $activeCustomGameAsset->getPk(),
                $user->getUiLanguageId(),
                $user
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $this->activeOrganization,
                $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            $request->user->sendFlashMessage('Custom Game Asset Deleted');

            return $form->handleRenderJsonSuccessResponse($game->getEditUrl("/{$this->updateChannel}/view-game-build/{$gameBuild->getPk()}?active_build_tab=custom-assets"));

        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }


        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'delete-custom-game-asset',
            TemplateVars::FORM => $form,
            TemplateVars::SLUG => $customGameAssetSlug,
            TemplateVars::GAME_BUILD => $gameBuild,
        ];

        return $this->renderAjaxResponse($request, $viewData, "account/games/manage-game/manage-build/delete-custom-game-asset.twig");
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|JSONResponse
     */
    protected function handle_delete_custom_asset_file(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $customGameAssetSlug = $request->getSlugForEntity($this->url_key+1);
        $customGameAssetId = $request->getIdForEntity($this->url_key+2);

        return false;

        if (!$customGameAssetSlug || !$customGameAssetId)
            return $this->render_404($request);

        $activeCustomGameAsset = $this->gamesActiveCustomAssetsManager->getActiveGameActiveCustomAssetLinkByGameIdAndSlug(
            $request,
            $game->getPk(),
            $this->updateChannel,
            $customGameAssetSlug,
            $selectedGameBuildId
        );

        if (!$activeCustomGameAsset)
            return $this->render_404($request);

        $customGameAsset = $this->gamesAssetsManager->getCustomGameAssetByCustomGameAssetId(
            $request,
            $game->getPk(),
            $customGameAssetId,
            $this->updateChannel
        );

        if (!$customGameAsset)
            return $this->render_404($request);

        if ($customGameAsset->getCustomGameAssetId() == $activeCustomGameAsset->getContextXGameAssetId())
            return $this->render_404($request);

        $fields = [];

        $defaults = [];

        $form = new PostForm($fields, $request, $defaults);


        if ($form->is_valid()) {

            $this->contextXGamesAssetsManager->deactivateLink($request, $customGameAsset->getCustomGameAssetId());

            $activity = $this->activityManager->trackActivity(
                $request,
                ActivityTypesManager::ACTIVITY_TYPE_USER_DELETE_CUSTOM_GAME_ASSET_FILE,
                $activeCustomGameAsset->getPk(),
                $customGameAsset->getCustomGameAssetId(),
                $user->getUiLanguageId(),
                $user
            );

            $this->organizationsActivityManager->trackOrganizationActivity(
                $request,
                $activity,
                $this->activeOrganization,
                $this->activeOrganization->getOrganizationUserByUserId($request->user->getId())
            );

            $request->user->sendFlashMessage('Custom Game Asset File Deleted');

            return $form->handleRenderJsonSuccessResponse($request->getRedirectBackUrl());

        } else {
            if ($request->is_post() && $request->is_ajax())
                return $form->handleRenderJsonErrorResponse();
        }


        $viewData = [
            TemplateVars::PAGE_IDENTIFIER => 'delete-custom-game-asset-file',
            TemplateVars::FORM => $form,
            TemplateVars::SLUG => $customGameAssetSlug,
            TemplateVars::GAME_ASSET => $customGameAsset
        ];

        return $this->renderAjaxResponse($request, $this->mergeSharedTemplateVars($request, $viewData), "account/games/manage-game/manage-build/delete-custom-game-asset-file.twig");
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param GameEntity $game
     * @return HtmlResponse|GameAssetResponse
     */
    protected function handle_download_custom_game_asset_file(Request $request, UserEntity $user, GameEntity $game, $selectedGameBuildId)
    {
        $customGameAssetSlug = $request->getSlugForEntity($this->url_key + 1);
        $customGameAssetId = $request->getIdForEntity($this->url_key + 2);

        if (!$customGameAssetSlug || !$customGameAssetId)
            return $this->render_404($request);

        $activeCustomGameAsset = $this->gamesActiveCustomAssetsManager->getActiveGameActiveCustomAssetLinkByGameIdAndSlug(
            $request,
            $game->getPk(),
            $this->updateChannel,
            $customGameAssetSlug,
            $selectedGameBuildId
        );

        if (!$activeCustomGameAsset)
            return $this->render_404($request);

        $customGameAsset = $this->gamesAssetsManager->getCustomGameAssetByCustomGameAssetId(
            $request,
            $game->getPk(),
            $customGameAssetId,
            $this->updateChannel
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
}