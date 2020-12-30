<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/25/19
 * Time: 11:44 AM
 */



class GameAssetEntity extends DBManagerEntity {

    use
        hasGameIdField,
        hasMd5Field,
        hasMimeTypeField,
        hasBucketField,
        hasBucketPathField,
        hasComputedFileNameField,
        hasFileSizeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUrlField,
        hasVirtualPublicUrlField,
        hasVirtualBucketKeyField;
}


class ContextXGameAssetEntity extends DBManagerEntity {

    use
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasGameAssetIdField,
        hasUpdateChannelField,
        hasSlugField,
        hasFolderPathField,
        hasFileNameField,
        hasExtensionField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}


class GameControllerAssetEntity extends GameAssetEntity {

    use
        hasUserIdField,
        hasFolderPathField,
        hasFileNameField,
        hasExtensionField,
        hasGameControllerIdField,
        hasUpdateChannelField,
        hasVirtualGameControllerAssetIdField;
}

class GameBuildAssetEntity extends GameAssetEntity {

    use
        hasUserIdField,
        hasFolderPathField,
        hasFileNameField,
        hasExtensionField,
        hasGameBuildIdField,
        hasUpdateChannelField,
        hasVirtualGameBuildAssetIdField;
}

class CustomGameAssetEntity extends GameAssetEntity {

    use
        hasUserIdField,
        hasFolderPathField,
        hasFileNameField,
        hasExtensionField,
        hasSlugField,
        hasGameIdField,
        hasCanModField,
        hasUpdateChannelField,
        hasIsPublicField,
        hasCreateTimeField,
        hasVirtualCustomGameAssetIdField,
        hasVirtualGameActiveCustomAssetField;

}

class GameInstanceLogAssetEntity extends GameAssetEntity
{
    use
        hasUserIdField,
        hasFolderPathField,
        hasFileNameField,
        hasExtensionField,
        hasSlugField,
        hasGameIdField,
        hasUpdateChannelField,
        hasIsPublicField,
        hasCreateTimeField,
        hasGameInstanceLogIdField,

        hasVirtualGameInstanceLogAssetIdField;
}

class GameActiveCustomAssetEntity extends DBManagerEntity {

    use
        hasGameIdField,
        hasUpdateChannelField,
        hasGameBuildIdField,
        hasSlugField,
        hasContextXGameAssetIdField,
        hasIsPublicField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualGameAssetField;
}
