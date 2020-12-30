<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 11/29/18
 * Time: 12:18 PM
 */


class HostAssetEntity extends DBManagerEntity {

    use
        hasMd5Field,
        hasSha512Field,
        hasMimeTypeField,
        hasBucketField,
        hasBucketPathField,
        hasFileSizeField,
        hasComputedFileNameField,

        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUrlField,
        hasVirtualBucketKeyField;
}

class HostControllerAssetEntity extends HostAssetEntity {

    use
        hasFolderPathField,
        hasFileNameField,
        hasExtensionField,
        hasHostControllerIdField,
        hasVirtualHostControllerAssetIdField;
}

class HostBuildAssetEntity extends HostAssetEntity {

    use
        hasFolderPathField,
        hasFileNameField,
        hasExtensionField,
        hasHostBuildIdField,
        hasVirtualHostBuildAssetIdField;


}

class ContextXHostAssetEntity extends DBManagerEntity {

    use
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasHostAssetIdField,
        hasFileNameField,
        hasExtensionField,
        hasFolderPathField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}