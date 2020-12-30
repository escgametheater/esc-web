<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 7/5/18
 * Time: 4:06 PM
 */

class ApplicationEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class ApplicationUserEntity extends DBManagerEntity
{
    use
        hasApplicationIdField,
        hasUserIdField,
        hasApiKeyField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class ApplicationUserAccessTokenEntity extends DBManagerEntity
{
    use
        hasApplicationUserIdField,
        hasUserIdField,
        hasTokenField,
        hasExpiresOnField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}