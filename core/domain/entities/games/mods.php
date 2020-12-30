<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/25/19
 * Time: 11:44 AM
 */

class GameModEntity extends DBManagerEntity
{
    use
        hasOrganizationIdField,
        hasGameIdField,
        hasDisplayNameField,
        hasDescriptionField,
        hasCreateTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,
        hasVirtualOrganizationSlugField,
        hasVirtualGameSlugField,
        hasVirtualGameField,
        hasVirtualEditUrlField,
        hasVirtualDeleteUrlField,
        hasVirtualCloneUrlField,
        hasVirtualGameModBuildsField;

}

class GameModBuildEntity extends DBManagerEntity
{
    use
        hasGameModIdField,
        hasUpdateChannelField,
        hasPublishedGameModBuildIdField,
        hasPublishedTimeField,
        hasBuildVersionField,
        hasCreateTimeField,
        hasCreatorIdField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUserField,
        hasVirtualCustomGameModAssetsField;

    /**
     * @param $gameModBuildId
     * @return bool
     */
    public function is_active_game_mod_build($gameModBuildId)
    {
        return $gameModBuildId == $this->getPk();
    }

    /**
     * @return bool
     */
    public function can_publish()
    {
        return !$this->getPublishedGameModBuildIdField();
    }

    /**
     * @return bool
     */
    public function is_published()
    {
        return $this->getPublishedGameModBuildIdField() ? true : false;
    }
}

class GameModDataEntity extends DBManagerEntity
{
    use
        hasGameModBuildIdField,
        hasKeyField,
        hasFirstActiveGameBuildIdField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualGameModDataSheetsField;
}

class GameModDataSheetEntity extends DBManagerEntity
{
    use
        hasGameModDataIdField,
        hasNameField,
        hasGameModDataSheetColumnIdField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualGameModDataSheetColumnsField,
        hasVirtualGameModDataSheetRowsField;
}

class GameModDataSheetColumnEntity extends DBManagerEntity
{
    use
        hasGameModDataSheetIdField,
        hasNameField,
        hasDisplayOrderField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class GameModDataSheetRowEntity extends DBManagerEntity
{
    use
        hasGameModDataSheetIdField,
        hasDisplayOrderField,
        hasValueField,
        hasIndexKeyField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualProcessedValuesField;
}

class GameModActiveBuildEntity extends DBManagerEntity
{
    use
        hasGameModIdField,
        hasUpdateChannelField,
        hasGameModBuildIdField,
        hasCreateTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class CustomGameModBuildAssetEntity extends GameAssetEntity {

    use
        hasUserIdField,
        hasFolderPathField,
        hasFileNameField,
        hasExtensionField,
        hasSlugField,
        hasGameIdField,
        hasGameModBuildIdField,
        hasUpdateChannelField,
        hasIsPublicField,
        hasCreateTimeField,
        hasVirtualCustomGameAssetIdField;
}

class GameModActiveCustomAssetEntity extends DBManagerEntity
{
    use
        hasGameModIdField,
        hasGameModBuildIdField,
        hasUpdateChannelField,
        hasSlugField,
        hasContextXGameAssetIdField,
        hasFirstActiveGameBuildIdField,
        hasIsPublicField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualGameModAssetField;

}

class GameModLicenseEntity extends DBManagerEntity
{
    use
        hasGameModIdField,
        hasUserIdField,
        hasUpdateChannelField,
        hasStartTimeField,
        hasEndTimeField,
        hasCreateTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUserField;
}
