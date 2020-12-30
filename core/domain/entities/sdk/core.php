<?php

class SdkPlatformEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasDisplayOrderField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class SdkPlatformVersionEntity extends DBManagerEntity
{
    use
        hasSdkPlatformIdField,
        hasDisplayNameField,
        hasDisplayOrderField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

}
