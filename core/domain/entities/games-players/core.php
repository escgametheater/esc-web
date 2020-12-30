<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 3/4/19
 * Time: 2:32 AM
 */

class GamePlayerStatEntity extends DBManagerEntity
{
    use
        hasGameIdField,
        hasGamePlayerStatTypeIdField,
        hasHostIdField,
        hasGuestIdField,
        hasUserIdField,
        hasNameField,
        hasValueField,
        hasCreateTimeField,
        hasGameInstanceRoundPlayerIdField,
        hasContextXGameAssetIdField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class GamePlayerStatTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}