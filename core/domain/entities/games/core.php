<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/6/18
 * Time: 12:24 AM
 */

require "assets.php";
require "controllers.php";
require "data.php";
require "instances.php";
require "licenses.php";
require "mods.php";
require "settings.php";

class GameEntity extends DBManagerEntity
{
    use
        hasGameIdField,
        hasSlugField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasGameTypeIdField,
        hasCanModField,
        hasGameCategoryIdField,
        hasGameEngineIdField,
        hasDisplayNameField,
        hasDescriptionField,
        hasIsWanEnabledField,
        hasIsAggregateGameField,
        hasIsDownloadableField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualOrganizationSlugField,
        hasVirtualParsedDescriptionField,
        hasVirtualEditUrlField,
        hasVirtualAvatarsField,
        hasVirtualGameTypeField,
        hasVirtualGameBuildsField,
        hasVirtualGameEngineField,
        hasVirtualGameCategoryField,
        hasVirtualOrganizationField,
        hasVirtualGameModsField;

    /**
     * @param $ownerDisplayName
     */
    public function setOwnerDisplayName($ownerDisplayName)
    {
        $this->dataArray[VField::OWNER_DISPLAY_NAME] = $ownerDisplayName;
    }

    /**
     * @return string|null
     */
    public function getOwnerDisplayName()
    {
        return $this->getVField(VField::OWNER_DISPLAY_NAME);
    }

    /**
     * @return array
     */
    public function getAvailableUpdateChannels()
    {
        return array_keys($this->getGameBuilds());
    }

    /**
     * @param GameBuildEntity $gameBuild
     * @return bool
     */
    public function canPublish(GameBuildEntity $gameBuild)
    {
        if ($gameBuild->getUpdateChannel() == GamesManager::UPDATE_CHANNEL_LIVE)
            return false;

        if ($gameBuild->is_published())
            return false;

        if ($this->is_type_offline_game()) {
            return true;
        } else {
            return $gameBuild->can_publish();
        }
    }

    /**
     * @return bool
     */
    public function can_play()
    {
        return $this->is_downloadable()
            && (
                $this->getGameBuildByUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE)
                || $this->getGameBuildByUpdateChannel(GamesManager::UPDATE_CHANNEL_DEV)
            );
    }

    /**
     * @return bool
     */
    public function can_customize()
    {
        if ($liveGameBuild = $this->getGameBuildByUpdateChannel(GamesManager::UPDATE_CHANNEL_LIVE)) {
            return $liveGameBuild->can_mod();
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function is_org_owner()
    {
        return $this->getOwnerTypeId() == EntityType::ORGANIZATION;
    }
}


class PlatformEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasDisplayOrderField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class PlatformVersionEntity extends DBManagerEntity
{
    use
        hasPlatformIdField,
        hasDisplayNameField,
        hasDisplayOrderField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

}

class GameXPlatformEntity extends DBManagerEntity
{
    use
        hasGameIdField,
        hasPlatformIdField,
        hasPlatformVersionIdField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}


class GameBuildEntity extends DBManagerEntity
{
    use
        hasGameIdField,
        hasUpdateChannelField,
        hasGameBuildVersionField,
        hasCanModField,
        hasIsAggregateGameField,
        hasPublishedGameBuildIdField,
        hasVersionHashField,
        hasDescriptionField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatorIdField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualParsedDescriptionField,
        hasVirtualUserField,
        hasVirtualGameField,
        hasVirtualGameAssetsField,
        hasVirtualGameControllersField,
        hasVirtualCustomGameAssetsField;

    /**
     * @param Request $request
     * @return CustomDataDefinitionsEntity|array
     */
    public function getCustomDataDefinition(Request $request)
    {
        $gameDataAsset = [];

        foreach ($this->getGameAssets() as $gameAsset) {
            if ($gameAsset->getFolderPath() == '.ESCSystem/' && $gameAsset->getFileName() == 'GameData.json') {

                $gameDataAsset = $gameAsset;
                break;
            }
        }

        if (!$gameDataAsset)
            return new CustomDataDefinitionsEntity();

        try {

            $cacheKey = "esc.games.builds.assets.game-data.interface.{$gameDataAsset->getPk()}";

            /** @var array $gameData */
            $gameData = $request->cache[$cacheKey];

        } catch (CacheEntryNotFound $c) {

            $rawAssetData = $request->s3->readIntoMemoryAsText(S3::BUCKET_GAME_ASSETS, $gameDataAsset->getBucketKey());

            $gameData = json_decode($rawAssetData, true);

            if ($gameData)
                $c->set($gameData, ONE_DAY);
        }

        $customDataDefinition = new CustomDataDefinitionsEntity($gameData);

        // Ensure that game builds don't fall behind when checking ;-)
        if (!$this->can_mod() && $customDataDefinition->is_moddable()) {
            $this->updateField(DBField::CAN_MOD, 1)->saveEntityToDb($request);
        }

        return $customDataDefinition;
    }

    /**
     * @param $activeGameBuildId
     * @return bool
     */
    public function can_publish()
    {
        return !$this->is_published() && $this->getGameAssets() && $this->getGameControllers();
    }

    /**
     * @param $activeGameBuildId
     * @return bool
     */
    public function is_active_game_build($activeGameBuildId)
    {
        return $this->getPk() == $activeGameBuildId;
    }

    /**
     * @return bool
     */
    public function is_live_build()
    {
        return $this->getUpdateChannel() == GamesManager::UPDATE_CHANNEL_LIVE;
    }
}

class CustomDataDefinitionsEntity extends BaseDataEntity
{
    const MOD_GROUP_BRANDING = 'branding';
    const MOD_GROUP_SPONSOR = 'sponsor';

    protected $customizerModGroups = [
        self::MOD_GROUP_BRANDING,
        self::MOD_GROUP_SPONSOR
    ];

    protected $modGroups = [];

    protected $hasData = false;

    protected $sourceData = [];

    /**
     * CustomDataDefinitionsEntity constructor.
     * @param array $data
     */
    public function __construct($data = [])
    {
        if ($data)
            $this->hasData = true;

        $this->sourceData = $data;

        $adaptedData = [
            'dataDefinitions' => [],
            'phases' => []
        ];

        if (array_key_exists('dataDefinitions', $data) && is_array($data['dataDefinitions'])) {

            foreach ($data['dataDefinitions'] as $dataDefinition) {

                $keyName = $dataDefinition[DBField::KEY];
                $adaptedData['dataDefinitions'][$keyName] = [];

                $keyDefinition = [
                    DBField::KEY => $keyName,
                    VField::SHEETS => [],
                ];


                foreach ($dataDefinition[VField::SHEETS] as $sheet) {

                    $sheetName = $sheet[DBField::NAME];

                    $sheetDefinition = [
                        DBField::NAME => $sheetName,
                        VField::COLUMNS => [],
                    ];

                    foreach ($sheet[VField::COLUMNS] as $columnDefinition) {
                        $columnName = $columnDefinition[DBField::NAME];
                        if(!isset($columnDefinition['modGroup'])) {
                            continue;
                        }
                        $modGroup = $columnDefinition['modGroup'];

                        if (!in_array($modGroup, $this->modGroups))
                            $this->modGroups[] = $modGroup;

                        $sheetDefinition[VField::COLUMNS][$columnName] = $columnDefinition;
                    }

                    $keyDefinition[VField::SHEETS][$sheetName] = $sheetDefinition;
                }

                $adaptedData['dataDefinitions'][$keyName] = $keyDefinition;
            }

            $adaptedData['phases'] = $data['phases'] ?? [];

        }

        parent::__construct($adaptedData);
    }

    /**
     * @return array
     */
    public function getPhases()
    {
        return $this->dataArray['phases'] ?? [];
    }

    /**
     * @return array
     */
    public function getModGroups()
    {
        return $this->modGroups;
    }

    /**
     * @return bool
     */
    public function has_data()
    {
        return $this->hasData;
    }

    /**
     * @return array
     */
    public function getSourceData()
    {
        return $this->sourceData;
    }

    /**
     * @return bool
     */
    public function is_moddable()
    {
        return count(array_intersect($this->customizerModGroups, $this->modGroups)) > 0;
    }

    /**
     * @param $modGroup
     * @return bool
     */
    public function validateGroupIsModable($modGroup)
    {
        return in_array($modGroup, $this->customizerModGroups);
    }

    /**
     * @param $key
     * @param $sheet
     * @param $column
     * @return array
     */
    public function getSheetColumnDefinitionByKeyAndSheetNameAndColumn($key, $sheet, $column)
    {

        return $this->dataArray['dataDefinitions'][$key][VField::SHEETS][$sheet][VField::COLUMNS][$column] ?? [];

    }

    /**
     * @param $key
     * @param $sheet
     * @return array
     */
    public function getSheetColumnDefinitionsByKeyAndSheetName($key, $sheet)
    {
        return $this->dataArray['dataDefinitions'][$key][VField::SHEETS][$sheet][VField::COLUMNS] ?? [];
    }
}
