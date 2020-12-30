<?php

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/19/18
 * Time: 10:58 AM
 */

class GamesDataApiV1Controller extends BaseApiV1Controller implements BaseApiControllerV1CRUDInterface {

    const REQUIRES_POST = true;
    const REQUIRES_AUTH = true;

    /** @var GamesDataManager $manager */
    protected $manager;
    /** @var GamesManager $gamesManager */
    protected $gamesManager;
    /** @var GamesBuildsManager $gamesBuildsManager */
    protected $gamesBuildsManager;
    /** @var GamesActiveBuildsManager$gamesActiveBuildsManager*/
    protected $gamesActiveBuildsManager;

    /** @var GamesDataManager $gamesDataManager */
    protected $gamesDataManager;
    /** @var GamesDataSheetsManager $gamesDataSheetsManager */
    protected $gamesDataSheetsManager;
    /** @var GamesDataSheetsRowsManager $gamesDataSheetsRowsManager */
    protected $gamesDataSheetsRowsManager;


    /** @var GamesControllersManager $gamesControllersManager */
    protected $gamesControllersManager;
    /** @var GamesControllersTypesManager $gamesControllersTypesManager */
    protected $gamesControllersTypesManager;


    /** @var GamesModsManager $gamesModsManager */
    protected $gamesModsManager;
    /** @var GamesModsDataManager $gamesModsDataManager */
    protected $gamesModsDataManager;
    /** @var GamesModsDataSheetsManager $gamesModsDataSheetsManager */
    protected $gamesModsDataSheetsManager;
    /** @var GamesModsDataSheetsRowsManager $gamesModsDataSheetsRowsManager */
    protected $gamesModsDataSheetsRowsManager;

    /** @var GamesAssetsManager $gamesAssetsManager */
    protected $gamesAssetsManager;
    /** @var GamesActiveCustomAssetsManager $gamesActiveCustomAssetsManager */
    protected $gamesActiveCustomAssetsManager;
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

        'list-custom-data' => 'handle_list_custom_data',
        'upload-custom-data' => 'handle_upload_custom_data',
        'download-custom-data' => 'handle_download_custom_data_xls',
        'get-sheet-data' => 'handle_get_sheet_data',
        'get-sheet-counts' => 'handle_get_sheet_counts',

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

        $this->gamesDataManager = $request->managers->gamesData();
        $this->gamesDataSheetsManager = $request->managers->gamesDataSheets();
        $this->gamesDataSheetsRowsManager = $request->managers->gamesDataSheetsRows();

        $this->gamesModsManager = $request->managers->gamesMods();
        $this->gamesModsDataManager = $request->managers->gamesModsData();
        $this->gamesModsDataSheetsManager = $request->managers->gamesModsDataSheets();
        $this->gamesModsDataSheetsRowsManager = $request->managers->gamesModsDataSheetsRows();

        $this->activityManager = $request->managers->activity();
        $this->organizationsActivityManager = $request->managers->organizationsActivities();

        $this->gamesControllersManager = $request->managers->gamesControllers();
        $this->gamesControllersTypesManager = $request->managers->gamesControllersTypes();

        $this->gamesAssetsManager = $request->managers->gamesAssets();
        $this->gamesActiveCustomAssetsManager = $request->managers->gamesActiveCustomAssets();
    }

    /**
     * @param Request $request
     * @return HttpResponse
     */
    public function handle_index(Request $request) : HttpResponse
    {
        $request->user->sendFlashMessage('Index Not Implemented Yet');
        return $this->redirect(HOMEPAGE);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_get(Request $request) : ApiV1Response
    {
        $this->form = $this->buildGetEntityForm($request);

        if ($this->form->is_valid()) {

        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_create(Request $request): ApiV1Response
    {
        $games = $this->gamesManager->getGamesByIds($request, [1,2,3]);

        $fields = $this->manager->getFormFields($games);

        $defaults = [];

        $this->form = new ApiV1PostForm($fields, $request, $defaults);

        if ($this->form->is_valid()) {

            $gameId = $this->form->getCleanedValue(DBField::GAME_ID);
            $key = $this->form->getCleanedValue(DBField::KEY);
            $value = $this->form->getCleanedValue(DBField::VALUE);
            $updateChannel = $this->form->getCleanedValue(DBField::UPDATE_CHANNEL);

            $gameBuild = $this->gamesBuildsManager->getMostRecentGameBuildByGameId($request, $gameId, $updateChannel);
            $gameData = $this->manager->createNewGameData($request, $gameId, $updateChannel, $gameBuild->getPk(), $key, $value);

            $this->setResults($gameData);
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

        } else {
            $this->form = $getEntityForm;
        }

        return $this->renderApiV1Response($request);
    }


    /**
     * @param Request $request
     * @return ApiV1Response|DownloadXlsxResponse|HtmlResponse
     * @throws BaseEntityException
     * @throws ObjectNotFound
     */
    protected function handle_list_custom_data(Request $request)
    {
        $UPDATE_CHANNEL_DEV = GamesManager::UPDATE_CHANNEL_DEV;

        /** @var GameEntity[] $games */
        $games = [];

        foreach ($this->gamesManager->getDevGamesByUserId($request, $request->user->id) as $game) {
            if (!array_key_exists($game->getPk(), $games)) $games[$game->getPk()] = $game;
        }

        $gameSlugs = [];
        $gamesBySlug = [];

        foreach ($games as $game) {
            $gamesBySlug[$game->getSlug()] = $game;

            $gameSlugs[] = [DBField::ID => $game->getSlug(), DBField::DISPLAY_NAME => $game->getDisplayName()];
        }

        $fields = [
            new SelectField(VField::GAME_SLUG, 'Game Slug', $gameSlugs, true),
        ];


        $this->form = new ApiV1PostForm($fields, $request);

        if ($this->form->is_valid()) {
            $gamesDataManager = $request->managers->gamesData();

            $game = $gamesBySlug[$this->form->getCleanedValue(VField::GAME_SLUG)];
            $gameBuild = null;

            $gameBuild = $this->gamesBuildsManager->getMostRecentGameBuildByGameId($request, $game->getPk(), $UPDATE_CHANNEL_DEV);

            if ($gameData = $gamesDataManager->getGameDataByGameBuildId($request, $gameBuild->getPk())) {
                $this->setResults($gameData);
            }

        }
        return $this->renderApiV1Response($request);

    }
            /**
     * @param Request $request
     * @param UserEntity $user
     * @return HtmlResponse|JSONResponse
     * @throws ObjectNotFound
     */
    protected function handle_upload_custom_data(Request $request)
    {

        $UPDATE_CHANNEL_DEV = GamesManager::UPDATE_CHANNEL_DEV;

        /** @var GameEntity[] $games */
        $games = [];

        foreach ($this->gamesManager->getDevGamesByUserId($request, $request->user->id) as $game) {
            if (!array_key_exists($game->getPk(), $games))
                $games[$game->getPk()] = $game;
        }

        $gameSlugs = [];
        $gamesBySlug = [];

        foreach ($games as $game) {
            $gamesBySlug[$game->getSlug()] = $game;

            $gameSlugs[] = [
                DBField::ID => $game->getSlug(),
                DBField::DISPLAY_NAME => $game->getDisplayName()
            ];
        }


        $fields = [
            new SelectField(VField::GAME_SLUG, 'Game Slug', $gameSlugs, true),
            new SlugField(DBField::KEY,"Game Data Key", 128, true),
            new FileField(DBField::FILE, 'Spreadsheet File', true, ['xls', 'xlsx'])
        ];

        $defaults = [

        ];

        $this->form = new ApiV1PostForm($fields,$request);

        if ($isValid = $this->form->is_valid()) {


            /** @var GameEntity $game */
            $game = $gamesBySlug[$this->form->getCleanedValue(VField::GAME_SLUG)];
            $gameBuild = $this->gamesBuildsManager->getMostRecentGameBuildByGameId($request, $game->getPk(), $UPDATE_CHANNEL_DEV);
            $key = $this->form->getCleanedValue(DBField::KEY);
            $value = $this->form->getCleanedValue(DBField::VALUE);
            $uploadId = $this->form->getCleanedValue(DBField::FILE)['upload_id_file'];

            $sourceFile = UploadsHelper::path_from_file_id($uploadId);

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


            $existingGameData = $this->gamesDataManager->getGameDataByChannelAndKey(
                $request,
                $game->getPk(),
                $UPDATE_CHANNEL_DEV,
                $key,
                $gameBuild->getPk()
            );

            if (!$existingGameData) {
                $this->manager->createNewGameData($request, $game->getPk(), $UPDATE_CHANNEL_DEV, $gameBuild->getPk(), $key);
            }

            $reader = new Xlsx();

            try {
                $spreadSheet = $reader->load($sourceFile);
                $gameData = $this->gamesDataManager->processSpreadsheet(
                    $request,
                    $game->getPk(),
                    $UPDATE_CHANNEL_DEV,
                    $gameBuild->getPk(),
                    $key,
                    $spreadSheet
                );


            } catch (Exception $e) {
                $gameData = [];
            }

            UploadsHelper::delete_upload($uploadId);

            if ($gameData) {

                $user = $request->user->getEntity();
                $activity = $this->activityManager->trackActivity(
                    $request,
                    ActivityTypesManager::ACTIVITY_TYPE_USER_UPLOAD_GAME_SPREADSHEET,
                    $game->getPk(),
                    $gameData->getPk(),
                    $user->getUiLanguageId(),
                    $user
                );

                $activeOrganization = $game->getOrganization();

                $this->organizationsActivityManager->trackOrganizationActivity(
                    $request,
                    $activity,
                    $activeOrganization,
                    $activeOrganization->getOrganizationUserByUserId($request->user->getId())
                );

                $this->setResults($gameData);
            } else {
                $this->form->set_error('Spreadsheet failed to be processed.', DBField::FILE);
            }
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response|DownloadXlsxResponse|HtmlResponse
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws BaseEntityException
     * @throws ObjectNotFound
     */
    protected function handle_download_custom_data_xls(Request $request)
    {
        $UPDATE_CHANNEL_DEV = GamesManager::UPDATE_CHANNEL_DEV;

        $user = $request->user->getEntity();

        /** @var GameEntity[] $games */
        $games = [];

        foreach ($this->gamesManager->getDevGamesByUserId($request, $request->user->id) as $game) {
            if (!array_key_exists($game->getPk(), $games))
                $games[$game->getPk()] = $game;
        }

        $gameSlugs = [];
        $gamesBySlug = [];

        foreach ($games as $game) {
            $gamesBySlug[$game->getSlug()] = $game;

            $gameSlugs[] = [
                DBField::ID => $game->getSlug(),
                DBField::DISPLAY_NAME => $game->getDisplayName()
            ];
        }

        $fields = [
            new SelectField(VField::GAME_SLUG, 'Game Slug', $gameSlugs, true),
            new SlugField(DBField::KEY,"Game Data Key", 128, true),
        ];


        $this->form = new ApiV1PostForm($fields,$request);

        if($this->form->is_valid()) {
            $gamesDataManager = $request->managers->gamesData();

            $game = $gamesBySlug[$this->form->getCleanedValue(VField::GAME_SLUG)];
            $key = $this->form->getCleanedValue(DBField::KEY);

            if (!$gameData = $gamesDataManager->getActiveGameDataByChannelAndKey($request, $game->getPk(), $UPDATE_CHANNEL_DEV, $key)) {
                return $this->render_404($request, 'Game Data Not Found');
            }

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

            return new DownloadXlsxResponse($writer, "{$game->getSlug()}_custom-data_{$gameData->getKey()}-{$UPDATE_CHANNEL_DEV}-{$gameData->getPk()}_{$request->getCurrentSqlTime('Y-m-d-H-i-s')}.xlsx");
        }

        return $this->renderApiV1Response($request);
    }

    /**
     * @param Request $request
     * @return ApiV1Response
     * @throws Exception
     */
    public function handle_get_sheet_data(Request $request): ApiV1Response
    {
        $updateChannels = $this->gamesManager->getUpdateChannelOptions();

        $fields = [
            new SlugField(DBField::SLUG,"Game Slug",128, true),
            new SelectField(DBField::UPDATE_CHANNEL, 'Update Channel', $updateChannels, true),
            new SlugField(DBField::KEY,"Game Data Key", 128, true),
            new SlugField(DBField::NAME,"Sheet name", 128, true),
            new IntegerField(DBField::GAME_MOD_ID, 'Game Mod Id', false),
            new RangeField(VField::OFFSET,"Row offset", 0, PHP_INT_MAX, false),
            new RangeField(VField::COUNT,"Row count", 0, PHP_INT_MAX, false),
        ];

        $this->form = new ApiV1PostForm($fields,$request);

        if ($this->form->is_valid()) {

            $slug = $this->form->getCleanedValue(DBField::SLUG);
            $updateChannel = $this->form->getCleanedValue(DBField::UPDATE_CHANNEL, GamesManager::UPDATE_CHANNEL_LIVE);
            $gameModId = $this->form->getCleanedValue(DBField::GAME_MOD_ID);
            $key = $this->form->getCleanedValue(DBField::KEY);
            $sheetName = $this->form->getCleanedValue(DBField::NAME);
            $offset = (int) $this->form->getCleanedValue(VField::OFFSET, 0);
            $count = (int) $this->form->getCleanedValue(VField::COUNT, 10);

            if ($slug && $key && $sheetName && $updateChannel) {

                $gameDataSheet = $this->gamesDataSheetsManager->getGameDataSheetByName(
                    $request,
                    $slug,
                    $updateChannel,
                    $key,
                    $sheetName,
                    false // Don't fetch the rows for this sheet, only the sheet.
                );

                // First we check if we have a sheet with that name and context.
                if ($gameDataSheet) {

                    // Initialize vars that will be used when returning results for this endpoint.
                    $totalGameDataSheetRowCount = 0;
                    $totalGameModDataSheetRowCount = 0;
                    $gameDataSheetRows = [];
                    $gameModDataSheetRows = [];

                    // If this sheet is mod type append, or no game mod id was specified (to replace content),
                    // we need to get the count of rows in the sheet explicitly for offset validation.
                    if ($gameDataSheet->is_mod_append() || !$gameModId) {
                        $totalGameDataSheetRowCount = $this->gamesDataSheetsRowsManager->getGameDataSheetRowCountByContext(
                            $request,
                            $slug,
                            $updateChannel,
                            $key,
                            $sheetName
                        );
                    }

                    // Check if we need to fetch rows from the original game data sheet. This is determined by whether
                    // rows actually exist in the game data sheet, or if the offset is greater than the count in the sheet
                    if ($this->shouldQueryGameDataSheetRows($gameDataSheet, $gameModId, $totalGameDataSheetRowCount, $offset)) {
                        $gameDataSheetRows = $this->gamesDataSheetsRowsManager->getGameDataSheetRowsByContext(
                            $request,
                            $slug,
                            $updateChannel,
                            $key,
                            $sheetName,
                            $offset,
                            $count
                        );
                    }

                    $results = [];

                    if ($gameDataSheet->can_mod() && $this->shouldQueryGameModDataSheetRows($gameDataSheet, $gameModId, $totalGameModDataSheetRowCount, $offset, $count)) {

                        $gameDataSheetRowResultCount = count($gameDataSheetRows);

                        if ($gameDataSheetRows && $gameDataSheetRowResultCount < $count) {
                            $modOffset = ($offset - $gameDataSheetRowResultCount);
                            $modCount = $count - $gameDataSheetRowResultCount;
                        } else {
                            $modOffset = $offset;
                            $modCount = $count;
                        }

                        $gameModDataSheetRows = $this->gamesModsDataSheetsRowsManager->getGameModDataSheetRowsByContext(
                            $request,
                            $slug,
                            $gameModId,
                            $updateChannel,
                            $key,
                            $sheetName,
                            $modOffset,
                            $modCount
                        );
                    }

                    $position = $offset;
                    foreach ($gameDataSheetRows as $gameDataSheetRow) {
                        $position++;
                        $rawResults = $gameDataSheetRow->getProcessedValues();
                        $rawResults[VField::V_PK] = $gameDataSheetRow->getPk();
                        $rawResults[VField::V_ENTITY_TYPE_ID] = EntityType::GAME_DATA_SHEET_ROW;
                        $rawResults[VField::V_POSITION] = $position;
                        $results[] = $rawResults;
                    }

                    foreach ($gameModDataSheetRows as $gameModDataSheetRow) {
                        $position++;
                        $rawResults = $gameModDataSheetRow->getProcessedValues();
                        $rawResults[VField::V_PK] = $gameModDataSheetRow->getPk();
                        $rawResults[VField::V_ENTITY_TYPE_ID] = EntityType::GAME_MOD_DATA_SHEET_ROW;
                        $rawResults[VField::V_POSITION] = $position;
                        $results[] = $rawResults;

                    }

                    $this->setResults($results);

                }

            }
        }
        return $this->renderApiV1Response($request);
    }

    /**
     * @param $gameDataSheetRowCount
     * @param $offset
     * @return bool
     */
    private function shouldQueryGameDataSheetRows(GameDataSheetEntity $gameDataSheet, $gameModId, $gameDataSheetRowCount, $offset)
    {
        // If there's no game mod id, we should further validate whether to get sheet rows.
        if (!$gameModId) {

            if ($gameDataSheetRowCount && $offset < $gameDataSheetRowCount)
                return true;

        } else {

            if ($gameDataSheet->is_mod_append() && $gameDataSheetRowCount && $offset < $gameDataSheetRowCount) {
                return true;
            }

        }

        // if there's no rows in the game data sheet, or the offset is greater than the count of rows in the sheet,
        // we do not need to query the sheet rows table.
        return false;
    }

    /**
     * @param GameDataSheetEntity $gameDataSheet
     * @param $gameModId
     * @param $totalGameDataSheetRowCount
     * @param $offset
     * @param $count
     * @return bool
     */
    private function shouldQueryGameModDataSheetRows(GameDataSheetEntity $gameDataSheet, $gameModId, $totalGameDataSheetRowCount, $offset, $count)
    {
        if ($gameModId) {

            if ($gameDataSheet->is_mod_append()) {

                // if this is type append, and we're asking for results that span both sources, we need to query mod rows
                if (($offset + $count) > $totalGameDataSheetRowCount)
                    return true;
            }

            // If this is type replace, or there's no rows in the original game data sheet, we need to query mods.
            if ($gameDataSheet->is_mod_replace() || !$totalGameDataSheetRowCount)
                return true;
        }

        // If no mod was specified, we should not query for mod results. Also handles default states not covered above.
        return false;
    }


    /**
     * @param Request $request
     * @return ApiV1Response
     */
    public function handle_get_sheet_counts(Request $request): ApiV1Response
    {
        $updateChannels = $this->gamesManager->getUpdateChannelOptions();

        $fields = [
            new SlugField(DBField::SLUG,"Game Slug",128, true),
            new SelectField(DBField::UPDATE_CHANNEL, 'Update Channel', $updateChannels, true),
            new SlugField(DBField::KEY,"Game Data Key", 128, true),
            new SlugField(DBField::NAME,"Sheet name", 128, true),
            new IntegerField(DBField::GAME_MOD_ID, 'Game Mod Id', false)
        ];

        $this->form = new ApiV1PostForm($fields,$request);

        if ($this->form->is_valid()) {

            $slug = $this->form->getCleanedValue(DBField::SLUG);
            $updateChannel = $this->form->getCleanedValue(DBField::UPDATE_CHANNEL, GamesManager::UPDATE_CHANNEL_LIVE);
            $gameModId = $this->form->getCleanedValue(DBField::GAME_MOD_ID);
            $key = $this->form->getCleanedValue(DBField::KEY);
            $sheetName = $this->form->getCleanedValue(DBField::NAME);

            if ($slug && $key && $sheetName && $updateChannel) {

                $gameDataSheet = $this->gamesDataSheetsManager->getGameDataSheetByName(
                    $request,
                    $slug,
                    $updateChannel,
                    $key,
                    $sheetName,
                    false // Don't fetch the rows for this sheet, only the sheet.
                );

                // First we check if we have a sheet with that name and context.
                if ($gameDataSheet) {

                    $totalRowCount = 0;

                    if (!$gameDataSheet->can_mod() || $gameDataSheet->is_mod_append() || !$gameModId) {
                        $gameDataSheetRowCount = $this->gamesDataSheetsRowsManager->getGameDataSheetRowCountByContext(
                            $request,
                            $slug,
                            $updateChannel,
                            $key,
                            $sheetName
                        );

                        $totalRowCount += $gameDataSheetRowCount;
                    }

                    if ($gameModId && $gameDataSheet->can_mod()) {

                        $gameModDataSheetRowCount = $this->gamesModsDataSheetsRowsManager->getGameModDataSheetRowCountByContext(
                            $request,
                            $slug,
                            $gameModId,
                            $updateChannel,
                            $key,
                            $sheetName
                        );


                        if ($gameDataSheet->is_mod_replace()) {
                            $totalRowCount = $gameModDataSheetRowCount;
                        } else {
                            $totalRowCount += $gameModDataSheetRowCount;
                        }
                    }

                    $results = [
                        VField::COUNT => $totalRowCount
                    ];

                    $this->setResults($results);
                }
            }
        }

        return $this->renderApiV1Response($request);
    }

}
