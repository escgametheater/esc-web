<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/3/19
 * Time: 2:21 PM
 */

class CoinAwardEntity extends BaseDataEntity
{
    use
        hasSessionHashField,
        hasValueField,
        hasNameField,
        hasGameInstanceRoundPlayerIdField;
}

class CoinAwardTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class UserCoinEntity extends DBManagerEntity
{
    use
        hasCoinAwardTypeIdField,
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasUserIdField,
        hasHostIdField,
        hasGameBuildIdField,
        hasValueField,
        hasGameInstanceRoundIdField,
        hasGameInstanceRoundPlayerIdField,
        hasNameField,
        hasCreateTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class GuestCoinEntity extends DBManagerEntity
{
    use
        hasCoinAwardTypeIdField,
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasHostIdField,
        hasGameBuildIdField,
        hasGuestIdField,
        hasValueField,
        hasGameInstanceRoundIdField,
        hasGameInstanceRoundPlayerIdField,
        hasNameField,
        hasCreateTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}