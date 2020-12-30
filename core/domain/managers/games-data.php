<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/25/19
 * Time: 11:35 AM
 */


class GamesDataManager extends BaseEntityManager {

    const ID_BOROUGH_GODS = 1;
    const ID_THE_RACES = 4;
    const KEY_HORSE_DATA = 'horse-data';
    const KEY_TICKER_TEXT = 'ticker-text';

    protected $entityClass = GameDataEntity::class;
    protected $table = Table::GameData;
    protected $table_alias = TableAlias::GameData;
    protected $pk = DBField::GAME_DATA_ID;

    public static $fields = [
        DBField::GAME_DATA_ID,
        DBField::GAME_ID,
        DBField::UPDATE_CHANNEL,
        DBField::GAME_BUILD_ID,
        DBField::KEY,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
        DBField::UPDATE_TIME
    ];

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::GAME_DATA_SHEETS))
            $data->updateField(VField::GAME_DATA_SHEETS, []);
    }

    /**
     * @param Request $request
     * @param GameDataEntity|GameDataEntity[] $gameData
     * @param bool $includeRows
     */
    public function postProcessGameData(Request $request, $gameData, $includeRows = true)
    {
        $gameDataSheetsManager = $request->managers->gamesDataSheets();

        if ($gameData) {
            if ($gameData instanceof GameDataEntity)
                $gameData = [$gameData];

            /** @var GameDataEntity[] $gameData */
            $gameData = array_index($gameData, $this->getPkField());
            $gameDataIds = array_keys($gameData);

            $gameDataSheets = $gameDataSheetsManager->getGameDataSheetsByGameDataIds($request, $gameDataIds, $includeRows);
            foreach ($gameDataSheets as $gameDataSheet) {
                $gameData[$gameDataSheet->getGameDataId()]->setGameDataSheet($gameDataSheet);
            }
        }
    }

    /**
     * @param array $games
     * @param array $updateChannels
     * @return FormField[]
     */
    public function getFormFields($games = [], $updateChannels = [])
    {
        $fields = [
            new SelectField(DBField::GAME_ID, 'Game ID', $games, true),
            new SelectField(DBField::UPDATE_CHANNEL, 'Update Channel', $updateChannels, true),
            new CharField(DBField::KEY, 'Key', 128, true),
            new CharField(DBField::VALUE, 'Meta Data Value', MEDIUMTEXT, false),
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $gameBuildId
     * @param $key
     * @param $value
     * @return GameDataEntity
     */
    public function createNewGameData(Request $request, $gameId, $updateChannel, $gameBuildId, $key)
    {
        $data = [
            DBField::GAME_ID => $gameId,
            DBField::GAME_BUILD_ID => $gameBuildId,
            DBField::UPDATE_CHANNEL => $updateChannel,
            DBField::KEY => $key,
            DBField::IS_ACTIVE => 1,
            DBFIELD::UPDATE_TIME => $request->getCurrentSqlTime()
        ];

        /** @var GameDataEntity $gameData */
        $gameData = $this->query($request->db)->createNewEntity($request, $data);

        return $gameData;
    }

    /**
     * @param Request $request
     * @param $gameDataId
     * @param null $gameBuildId
     * @param bool $expand
     * @return array|GameDataEntity
     * @throws ObjectNotFound
     */
    public function getGameDataById(Request $request, $gameDataId, $gameBuildId = null, $expand = true)
    {
        $queryBuilder = $this->query($request->db)->filter($this->filters->byPk($gameDataId));

        if ($gameBuildId)
            $queryBuilder->filter($this->filters->byGameBuildId($gameBuildId));

        /** @var GameDataEntity $gameData */
        $gameData = $queryBuilder->get_entity($request);

        $this->postProcessGameData($request, $gameData, $expand);

        return $gameData;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $key
     * @param null $gameBuildId
     * @return array|GameDataEntity
     * @throws ObjectNotFound
     */
    public function getGameDataByChannelAndKey(Request $request, $gameId, $updateChannel, $key, $gameBuildId = null)
    {
        $gameData = $this->query($request->db)
            ->filter($this->filters->isActive())
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->byKey($key))
            ->get_entity($request);

        $this->postProcessGameData($request, $gameData);

        return $gameData;
    }



    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $key
     * @param bool $countOnly
     * @return GameDataEntity
     * @throws ObjectNotFound
     */
    public function getActiveGameDataByChannelAndKey(Request $request, $gameId, $updateChannel, $key)
    {
        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();

        $joinGamesActiveBuildsFilter = $this->filters->And_(
            $gamesActiveBuildsManager->filters->byGameId($this->field(DBField::GAME_ID)),
            $gamesActiveBuildsManager->filters->byUpdateChannel($this->field(DBField::UPDATE_CHANNEL)),
            $gamesActiveBuildsManager->filters->byGameBuildId($this->field(DBField::GAME_BUILD_ID))
        );

        $queryBuilder = $this->query($request->db)
            ->inner_join($gamesActiveBuildsManager, $joinGamesActiveBuildsFilter)
            ->filter($this->filters->isActive())
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->filter($this->filters->byKey($key));

        /** @var GameDataEntity $gameData */
        $gameData = $queryBuilder->get_entity($request);

        $this->postProcessGameData($request, $gameData);

        return $gameData;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param null $gameBuildId
     * @return GameDataEntity[]
     */
    public function getGameDataByGameId(Request $request, $gameId, $updateChannel, $gameBuildId = null)
    {
        $gameData = $this->query($request->db)
            ->filter($this->filters->isActive())
            ->filter($this->filters->byGameId($gameId))
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->byUpdateChannel($updateChannel))
            ->get_entities($request);

        $this->postProcessGameData($request, $gameData);

        return $gameData;
    }

    /**
     * @param Request $request
     * @param $gameId
     * @param $updateChannel
     * @param $gameBuildId
     * @param $gameDataKey
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadSheet
     * @return array|DBManagerEntity|GameDataEntity
     * @throws ObjectNotFound
     */
    public function processSpreadsheet(Request $request, $gameId, $updateChannel, $gameBuildId, $gameDataKey, \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadSheet)
    {
        $gamesDataSheetsManager = $request->managers->gamesDataSheets();
        $gamesDataSheetsColumnsManager = $request->managers->gamesDataSheetsColumns();
        $gamesDataSheetsRowsManager = $request->managers->gamesDataSheetsRows();

        $processedData = [];

        // Try to get game data record from DB
        if ($gameData = $this->getGameDataByChannelAndKey($request, $gameId, $updateChannel, $gameDataKey, $gameBuildId)) {
            // If the data exists, deactivate previous sheets.
            foreach ($gameData->getGameDataSheets() as $gameDataSheet) {
                $gamesDataSheetsManager->deactivateEntity($request, $gameDataSheet);
            }
        } else {
            // We need to create a new game data for this key/channel/build.
            $gameData = $this->createNewGameData($request, $gameId, $updateChannel, $gameBuildId, $gameDataKey, $processedData);
        }

        // Process All Sheets in XLS
        foreach($spreadSheet->getSheetNames() as $sheetName) {

            /** @var array $sheetData */
            $sheetData = $spreadSheet->getSheetByName($sheetName)->toArray();

            $headerRow = array_shift($sheetData);

            $oldIndexFieldName = null;

            // Preserve can_mod setting from previous sheet of same name if it exists.
            if ($oldGameDataSheet = $gameData->getGameDataSheetByName($sheetName)) {
                $canMod = $oldGameDataSheet->getCanMod();
                $modType = $oldGameDataSheet->getGameDataSheetModTypeId();
                $oldIndexFieldName = $oldGameDataSheet->getIndexColumn()->getName();
            } else {
                $canMod = 0;
                $modType = GamesDataSheetsModsTypesManager::ID_APPEND;
            }

            // Create New Sheet
            $gameDataSheet = $gamesDataSheetsManager->createNewGameDataSheet(
                $request,
                $gameData->getPk(),
                $sheetName,
                $canMod,
                $modType
            );

            // Extract and create the sheet column names.
            $headerIsEmpty = true;

            foreach ($headerRow as $index => $header) {
                if ($header !== null)
                    $headerIsEmpty= false;
            }

            if (!$headerIsEmpty) {
                $displayOrder = 1;
                foreach ($headerRow as $index => $columnName) {
                    if ($columnName != null) {
                        $gameDataSheetColumn = $gamesDataSheetsColumnsManager->createNewGameDataSheetColumn(
                            $request,
                            $gameDataSheet->getPk(),
                            $columnName,
                            $displayOrder
                        );
                        $gameDataSheet->setGameDataSheetColumn($gameDataSheetColumn);

                        // If this is the first column, or the column that previously matches index field, set the index field.
                        if ($displayOrder == 1 || $oldIndexFieldName == $columnName) {
                            $gameDataSheet->updateField(DBField::GAME_DATA_SHEET_COLUMN_ID, $gameDataSheetColumn->getPk())->saveEntityToDb($request);
                        }

                        $displayOrder++;

                    } else {
                        continue;
                    }
                }

                $displayOrder = 1;
                foreach ($sheetData as $row) {

                    $isEmpty = true;

                    foreach ($headerRow as $index => $header) {
                        if ($row[$index] !== null)
                            $isEmpty = false;
                    }

                    if ($isEmpty)
                        continue;

                    $dataRow = [];

                    foreach ($headerRow as $index => $header) {
                        $dataRow[$header] = $row[$index];
                    }

                    $gameDataSheetRow = $gamesDataSheetsRowsManager->createNewGameDataSheetRow(
                        $request,
                        $gameDataSheet->getPk(),
                        $dataRow,
                        $displayOrder,
                        $dataRow[$gameDataSheet->getIndexColumn()->getName()]
                    );

                    $gameDataSheet->setGameDataSheetRow($gameDataSheetRow);

                    $processedData[$sheetName][] = $dataRow;
                    $displayOrder++;
                }
                $gameData->setGameDataSheet($gameDataSheet);
            }
        }

        $gameData->updateField(DBField::UPDATE_TIME, $request->getCurrentSqlTime())->saveEntityToDb($request);

        return $gameData;
    }

    /**
     * @param Request $request
     * @param GameBuildEntity $oldGameBuild
     * @param GameBuildEntity $newGameBuild
     */
    public function cloneGameBuildData(Request $request, GameBuildEntity $oldGameBuild, GameBuildEntity $newGameBuild)
    {
        $gamesDataSheetsManager = $request->managers->gamesDataSheets();
        $gamesDataSheetsColumnsManager = $request->managers->gamesDataSheetsColumns();
        $gamesDataSheetsRowsManager = $request->managers->gamesDataSheetsRows();

        $customGameDataSpreadsheets = $this->getGameDataByGameId($request, $oldGameBuild->getGameId(), $oldGameBuild->getUpdateChannel(), $oldGameBuild->getPk());

        foreach ($customGameDataSpreadsheets as $customGameData) {
            $newGameData = $this->createNewGameData(
                $request,
                $newGameBuild->getGameId(),
                $newGameBuild->getUpdateChannel(),
                $newGameBuild->getPk(),
                $customGameData->getKey()
            );

            foreach ($customGameData->getGameDataSheets() as $gameDataSheet) {
                /** @var GameDataSheetEntity $gameDataSheet */
                $newGameDataSheet = $gamesDataSheetsManager->createNewGameDataSheet(
                    $request,
                    $newGameData->getPk(),
                    $gameDataSheet->getName(),
                    $gameDataSheet->getCanMod(),
                    $gameDataSheet->getGameDataSheetModTypeId()
                );

                foreach ($gameDataSheet->getGameDataSheetColumns() as $column) {
                    $newGameDataSheetColumn = $gamesDataSheetsColumnsManager->createNewGameDataSheetColumn(
                        $request,
                        $newGameDataSheet->getPk(),
                        $column->getName(),
                        $column->getDisplayOrder()
                    );
                    $newGameDataSheet->setGameDataSheetColumn($newGameDataSheetColumn);

                    if ($gameDataSheet->getIndexColumn() && $gameDataSheet->getIndexColumn()->getName() == $newGameDataSheetColumn->getName())
                        $newGameDataSheet->updateField(DBField::GAME_DATA_SHEET_COLUMN_ID, $newGameDataSheetColumn->getPk())->saveEntityToDb($request);
                }

                foreach ($gameDataSheet->getGameDataSheetRows() as $gameDataSheetRow) {
                    $newGameDataSheetRow = $gamesDataSheetsRowsManager->createNewGameDataSheetRow(
                        $request,
                        $newGameDataSheet->getPk(),
                        $gameDataSheetRow->getProcessedValues(),
                        $gameDataSheetRow->getDisplayOrder(),
                        $gameDataSheetRow->getIndexKey()
                    );
                    $newGameDataSheet->setGameDataSheetRow($newGameDataSheetRow);
                }
            }
        }
    }

    /**
     * @param Request $request
     * @param $gameBuildId
     * @param bool $includeRows
     * @return GameDataEntity[]
     */
    public function getGameDataByGameBuildId(Request $request, $gameBuildId, $includeRows = false)
    {
        /** @var GameDataEntity[] $gameData */
        $gameData = $this->query($request->db)
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        $this->postProcessGameData($request, $gameData, $includeRows);

        return $gameData;
    }

    /**
     * @param Request $request
     * @param $gameBuildId
     * @param $key
     * @param bool $includeRows
     * @return GameDataEntity
     * @throws ObjectNotFound
     */
    public function getGameDataByGameBuildIdAndKey(Request $request, $gameBuildId, $key, $includeRows = true)
    {
        /** @var GameDataEntity $gameData */
        $gameData = $this->query($request->db)
            ->filter($this->filters->byGameBuildId($gameBuildId))
            ->filter($this->filters->byKey($key))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        $this->postProcessGameData($request, $gameData, $includeRows);

        return $gameData;
    }
}




class GamesDataSheetsManager extends BaseEntityManager
{
    protected $entityClass = GameDataSheetEntity::class;
    protected $table = Table::GameDataSheet;
    protected $table_alias = TableAlias::GameDataSheet;
    protected $pk = DBField::GAME_DATA_SHEET_ID;

    const SINGLE_SHEET_NAME = 'Sheet1';

    public static $fields = [
        DBField::GAME_DATA_SHEET_ID,
        DBField::GAME_DATA_ID,
        DBField::GAME_DATA_SHEET_MOD_TYPE_ID,
        DBField::NAME,
        DBField::GAME_DATA_SHEET_COLUMN_ID,
        DBField::CAN_MOD,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        GamesDataSheetsModsTypesManager::class => DBField::GAME_DATA_SHEET_MOD_TYPE_ID
    ];

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::GAME_DATA_SHEET_COLUMNS))
            $data->updateField(VField::GAME_DATA_SHEET_COLUMNS, []);

        if (!$data->hasField(VField::GAME_DATA_SHEET_ROWS))
            $data->updateField(VField::GAME_DATA_SHEET_ROWS, []);
    }

    /**
     * @param Request $request
     * @param GameDataSheetEntity|GameDataSheetEntity[] $gameDataSheets
     * @param bool $countOnly
     */
    public function postProcessGameDataSheets(Request $request, $gameDataSheets, $includeRows = true)
    {
        $gameDataSheetRowsManager = $request->managers->gamesDataSheetsRows();
        $gameDataSheetColumnsManager = $request->managers->gamesDataSheetsColumns();

        if ($gameDataSheets) {
            if ($gameDataSheets instanceof GameDataSheetEntity)
                $gameDataSheets = [$gameDataSheets];

            $gameDataSheets = array_index($gameDataSheets, $this->getPkField());
            $gameDataSheetIds = array_keys($gameDataSheets);

            $gameDataSheetsColumns = $gameDataSheetColumnsManager->getActiveGameDataSheetColumnsByGameDataSheetIds($request, $gameDataSheetIds);
            foreach ($gameDataSheetsColumns as $gameDataSheetsColumn) {
                $gameDataSheets[$gameDataSheetsColumn->getGameDataSheetId()]->setGameDataSheetColumn($gameDataSheetsColumn);
            }

            if ($includeRows) {
                $gameDataSheetRows = $gameDataSheetRowsManager->getGameDataSheetRowsBySheetIds($request, $gameDataSheetIds);
                foreach ($gameDataSheetRows as $gameDataSheetRow) {
                    $gameDataSheets[$gameDataSheetRow->getGameDataSheetId()]->setGameDataSheetRow($gameDataSheetRow);
                }
            }
        }
    }

    /**
     * @param Request $request
     * @param $gameDataId
     * @param $name
     * @param int $canMod
     * @param int $gameDataSheetModTypeId
     * @return GameDataSheetEntity
     */
    public function createNewGameDataSheet(Request $request, $gameDataId, $name, $canMod = 0,
                                           $gameDataSheetModTypeId = GamesDataSheetsModsTypesManager::ID_APPEND)
    {
        $data = [
            DBField::GAME_DATA_ID => $gameDataId,
            DBField::NAME => $name,
            DBField::GAME_DATA_SHEET_MOD_TYPE_ID => $gameDataSheetModTypeId,
            DBField::GAME_DATA_SHEET_COLUMN_ID => null,
            DBField::CAN_MOD => $canMod,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var GameDataSheetEntity $gameDataSheet */
        $gameDataSheet = $this->query($request->db)->createNewEntity($request, $data);

        return $gameDataSheet;
    }

    /**
     * @param Request $request
     * @param $gameDataIds
     * @param bool $expand
     * @return GameDataSheetEntity[]
     */
    public function getGameDataSheetsByGameDataIds(Request $request, $gameDataIds, $expand = true)
    {
        /** @var GameDataSheetEntity[] $gameData */
        $gameData = $this->query($request->db)
            ->filter($this->filters->byGameDataId($gameDataIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        $this->postProcessGameDataSheets($request, $gameData, $expand);

        return $gameData;
    }

    /**
     * @param Request $request
     * @param GameDataSheetEntity $gameDataSheet
     */
    public function updateGameDataSheetRowsIndexKeys(Request $request, GameDataSheetEntity $gameDataSheet)
    {
        foreach ($gameDataSheet->getGameDataSheetRows() as $gameDataSheetRow) {
            $indexKey = $gameDataSheetRow->getProcessedValueByKey($gameDataSheet->getIndexColumn()->getName());
            $gameDataSheetRow->updateField(DBField::INDEX_KEY, $indexKey);
            $gameDataSheetRow->saveEntityToDb($request);
        }
    }

    /**
     * @param Request $request
     * @param $gameSlug
     * @param $updateChannel
     * @param $key
     * @return SQLQuery
     */
    protected function queryJoinGameActiveData(Request $request, $gameSlug, $updateChannel, $key)
    {
        $gamesManager = $request->managers->games();
        $joinGamesFilter = $this->filters->And_(
            $gamesManager->filters->bySlug($gameSlug),
            $gamesManager->filters->isActive()
        );

        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();
        $joinGamesActiveBuildsFilter = $this->filters->And_(
            $gamesActiveBuildsManager->filters->byGameId($gamesManager->createPkField()),
            $gamesActiveBuildsManager->filters->byUpdateChannel($updateChannel),
            $gamesActiveBuildsManager->filters->isActive()
        );

        $gamesDataManager = $request->managers->gamesData();
        $joinGamesDataManager = $this->filters->And_(
            $gamesDataManager->filters->byGameBuildId($gamesActiveBuildsManager->field(DBField::GAME_BUILD_ID)),
            $gamesDataManager->filters->byKey($key),
            $gamesDataManager->filters->isActive()
        );

        return $this->query($request->db)
            ->inner_join($gamesManager, $joinGamesFilter)
            ->inner_join($gamesActiveBuildsManager, $joinGamesActiveBuildsFilter)
            ->inner_join($gamesDataManager, $joinGamesDataManager);
    }

    /**
     * @param Request $request
     * @param $gameSlug
     * @param $updateChannel
     * @param $key
     * @param $sheetName
     * @param bool $includeRows
     * @return GameDataSheetEntity
     */
    public function getGameDataSheetByName(Request $request, $gameSlug, $updateChannel, $key, $sheetName, $includeRows = false)
    {
        $gamesDataManager = $request->managers->gamesData();

        /** @var GameDataSheetEntity $gameDataSheet */
        $gameDataSheet = $this->queryJoinGameActiveData($request, $gameSlug, $updateChannel, $key)
            ->filter($this->filters->byGameDataId($gamesDataManager->createPkField()))
            ->filter($this->filters->byName($sheetName))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        $this->postProcessGameDataSheets($request, $gameDataSheet, $includeRows);

        return $gameDataSheet;
    }
}

class GamesDataSheetsColumnsManager extends BaseEntityManager
{
    protected $entityClass = GameDataSheetColumnEntity::class;
    protected $table = Table::GameDataSheetColumn;
    protected $table_alias = TableAlias::GameDataSheetColumn;
    protected $pk = DBField::GAME_DATA_SHEET_COLUMN_ID;

    public static $fields = [
        DBField::GAME_DATA_SHEET_COLUMN_ID,
        DBField::GAME_DATA_SHEET_ID,
        DBField::NAME,
        DBField::DISPLAY_ORDER,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    public function processVFields(DBManagerEntity $data, Request $request)
    {
        return parent::processVFields($data, $request); // TODO: Change the autogenerated stub
    }

    /**
     * @param Request $request
     * @param $gameDataSheetId
     * @param $columnName
     * @param int $displayOrder
     * @return GameDataSheetColumnEntity
     */
    public function createNewGameDataSheetColumn(Request $request, $gameDataSheetId, $columnName, $displayOrder = 0)
    {
        $data = [
            DBField::GAME_DATA_SHEET_ID => $gameDataSheetId,
            DBField::NAME => $columnName,
            DBField::DISPLAY_ORDER => $displayOrder,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var GameDataSheetColumnEntity $gameDataSheetColumn */
        $gameDataSheetColumn = $this->query($request->db)->createNewEntity($request, $data);

        return $gameDataSheetColumn;
    }

    /**
     * @param Request $request
     * @param $gameDataSheetIds
     * @return GameDataSheetColumnEntity[]
     */
    public function getActiveGameDataSheetColumnsByGameDataSheetIds(Request $request, $gameDataSheetIds)
    {
        /** @var GameDataSheetColumnEntity[] $gameDataSheetColumns */
        $gameDataSheetColumns = $this->query($request->db)
            ->filter($this->filters->byGameDataSheetId($gameDataSheetIds))
            ->filter($this->filters->isActive())
            ->sort_asc(DBField::DISPLAY_ORDER)
            ->get_entities($request);

        return $gameDataSheetColumns;
    }
}

class GamesDataSheetsModsTypesManager extends BaseEntityManager
{
    const ID_APPEND = 1;
    const ID_REPLACE = 2;
    const ID_REPLACE_CASCADE = 3;

    protected $entityClass = GameDataSheetModTypeEntity::class;
    protected $table = Table::GameDataSheetModType;
    protected $table_alias = TableAlias::GameDataSheetModType;
    protected $pk = DBField::GAME_DATA_SHEET_MOD_TYPE_ID;

    /** @var GameDataSheetModTypeEntity[]  */
    protected $gameDataSheetModTypes = [];

    public static $fields = [
        DBField::GAME_DATA_SHEET_MOD_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @return DBManagerEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        return $data ;
    }

    /**
     * @param Request $request
     * @return array|GameDataSheetModTypeEntity[]
     */
    public function getAllGameDataSheetModTypes(Request $request)
    {
        if (!$this->gameDataSheetModTypes) {
            $gameDataSheetModTypes = $this->query($request->db)->get_entities($request);
            $this->gameDataSheetModTypes = array_index($gameDataSheetModTypes, $this->getPkField());
        }
        return $this->gameDataSheetModTypes;
    }
}
class GamesDataSheetsRowsManager extends BaseEntityManager
{

    protected $entityClass = GameDataSheetRowEntity::class;
    protected $table = Table::GameDataSheetRow;
    protected $table_alias = TableAlias::GameDataSheetRow;
    protected $pk = DBField::GAME_DATA_SHEET_ROW_ID;

    public static $fields = [
        DBField::GAME_DATA_SHEET_ROW_ID,
        DBField::GAME_DATA_SHEET_ID,
        DBField::DISPLAY_ORDER,
        DBField::VALUE,
        DBField::INDEX_KEY,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param DBManagerEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

        $data->updateField(VField::PROCESSED_VALUES, unserialize($data->getValue()));
    }

    /**
     * @param Request $request
     * @param $gameDataSheetId
     * @param $value
     * @param int $displayOrder
     * @param string|null $indexKey
     * @return GameDataSheetRowEntity
     */
    public function createNewGameDataSheetRow(Request $request, $gameDataSheetId, $value, $displayOrder = 0, $indexKey = null)
    {
        $data = [
            DBField::GAME_DATA_SHEET_ID => $gameDataSheetId,
            DBField::DISPLAY_ORDER => $displayOrder,
            DBField::VALUE => serialize($value),
            DBField::INDEX_KEY => $indexKey,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var GameDataSheetRowEntity $gameDataSheetRow */
        $gameDataSheetRow = $this->query($request->db)->createNewEntity($request, $data);

        return $gameDataSheetRow;
    }

    /**
     * @param Request $request
     * @param $gameDataSheetIds
     * @param int|null $offset
     * @param int|null $count
     * @return GameDataSheetRowEntity[]
     */
    public function getGameDataSheetRowsBySheetIds(Request $request, $gameDataSheetIds)
    {
        return $this->query($request->db)
            ->filter($this->filters->byGameDataSheetId($gameDataSheetIds))
            ->filter($this->filters->isActive())
            ->sort_asc(DBField::DISPLAY_ORDER)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $gameSlug
     * @param $updateChannel
     * @param $key
     * @param $sheetName
     * @return SQLQuery
     */
    protected function queryJoinGameActiveDataSheet(Request $request, $gameSlug, $updateChannel, $key, $sheetName)
    {
        $gamesManager = $request->managers->games();
        $joinGameFilter = $this->filters->And_(
            $gamesManager->filters->bySlug($gameSlug),
            $gamesManager->filters->isActive()
        );

        $gamesActiveBuildsManager = $request->managers->gamesActiveBuilds();
        $joinGamesActiveBuildsFilter = $this->filters->And_(
            $gamesActiveBuildsManager->filters->byGameId($gamesManager->createPkField()),
            $gamesActiveBuildsManager->filters->byUpdateChannel($updateChannel),
            $gamesActiveBuildsManager->filters->isActive()
        );

        $gamesDataManager = $request->managers->gamesData();
        $joinGameDataFilter = $this->filters->And_(
            $gamesDataManager->filters->byGameId($gamesManager->createPkField()),
            $gamesDataManager->filters->byGameBuildId($gamesActiveBuildsManager->field(DBField::GAME_BUILD_ID)),
            $gamesDataManager->filters->byKey($key),
            $gamesDataManager->filters->isActive()
        );

        $gamesDataSheetsManager = $request->managers->gamesDataSheets();
        $joinGameDataSheetsFilter = $this->filters->And_(
            $gamesDataSheetsManager->filters->byPk($this->field(DBField::GAME_DATA_SHEET_ID)),
            $gamesDataSheetsManager->filters->byGameDataId($gamesDataManager->createPkField()),
            $gamesDataSheetsManager->filters->byName($sheetName),
            $gamesDataSheetsManager->filters->isActive(),
            $this->filters->byGameDataSheetId($gamesDataSheetsManager->createPkField())
        );

        return $this->query($request->db)
            ->inner_join($gamesManager, $joinGameFilter)
            ->inner_join($gamesActiveBuildsManager, $joinGamesActiveBuildsFilter)
            ->inner_join($gamesDataManager, $joinGameDataFilter)
            ->inner_join($gamesDataSheetsManager, $joinGameDataSheetsFilter);
    }

    /**
     * @param Request $request
     * @param $gameSlug
     * @param $updateChannel
     * @param $key
     * @param $sheetName
     * @param int|null $offset
     * @param int|null $count
     * @return GameDataSheetRowEntity[]
     * @throws Exception
     */
    public function getGameDataSheetRowsByContext(Request $request, $gameSlug, $updateChannel, $key, $sheetName, $offset = null, $count = 40)
    {
        $queryBuilder = $this->queryJoinGameActiveDataSheet($request, $gameSlug, $updateChannel, $key, $sheetName)
            ->filter($this->filters->isActive())
            ->sort_asc($this->field(DBField::DISPLAY_ORDER))
            ->limit($count);

        if (!is_null($offset) && $offset > 0) {
            $queryBuilder->offset($offset);
        }

        $gameDataSheetRows = $queryBuilder->get_entities($request);

        return $gameDataSheetRows;
    }

    /**
     * @param Request $request
     * @param $gameSlug
     * @param $updateChannel
     * @param $key
     * @param $sheetName
     * @return int
     */
    public function getGameDataSheetRowCountByContext(Request $request, $gameSlug, $updateChannel, $key, $sheetName)
    {
        try {
            $count = $this->queryJoinGameActiveDataSheet($request, $gameSlug, $updateChannel, $key, $sheetName)
                ->filter($this->filters->isActive())
                ->count();
        } catch (ObjectNotFound $e) {
            $count = 0;
        }
        return $count;

    }

}


