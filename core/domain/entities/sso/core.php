<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 11/14/18
 * Time: 4:57 PM
 */

class SSOServiceEntity extends DBManagerEntity
{
    use
        hasSlugField,
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class UserSSOServiceEntity extends DBManagerEntity
{
    use
        hasSsoServiceIdField,
        hasUserIdField,
        hasSsoAccountIdField,
        hasDisplayNameField,
        hasScopeField,
        hasTokenField,
        hasExpiresOnField,
        hasRefreshTokenField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}