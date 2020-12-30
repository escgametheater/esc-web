<?php

class SdkAssetEntity extends DBManagerEntity {

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


class SdkBuildAssetEntity extends SdkAssetEntity {

    use
        hasFolderPathField,
        hasFileNameField,
        hasExtensionField,
        hasSdkBuildIdField,
        hasVirtualSdkBuildAssetIdField;
}

class ContextXSdkAssetEntity extends DBManagerEntity {

    use
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasSdkAssetIdField,
        hasFileNameField,
        hasExtensionField,
        hasFolderPathField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}