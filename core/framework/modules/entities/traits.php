<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 1/3/16
 * Time: 1:52 AM
 */

trait hasAuditFields {
    use
        hasCreatorIdField,
        hasUpdaterIdField,
        hasCreateTimeField,
        hasUpdateTimeField;
}

trait hasLanguageIdField {

    /**
     * @return string
     */
    public function getLanguageId()
    {
        return $this->field(DBField::LANGUAGE_ID);
    }
}

trait hasTextField {

    /**
     * @return string
     */
    public function getText()
    {
        return $this->field(DBField::TEXT);
    }
}


trait hasIsPublicField {

    /**
     * @return int|null
     */
    public function getIsPublic()
    {
        return $this->hasField(DBField::IS_PUBLIC) ? $this->field(DBField::IS_PUBLIC) : null;
    }

    /**
     * @return bool
     */
    public function is_public()
    {
        return $this->getIsPublic() ? true : false;
    }
}


trait hasIsDeletedField {

    /**
     * @return int|null
     */
    public function getIsDeleted()
    {
        return $this->hasField(DBField::IS_DELETED) ? $this->field(DBField::IS_DELETED) : null;
    }

    /**
     * @return bool
     */
    public function is_deleted()
    {
        return $this->getIsDeleted() ? true : false;
    }
}


trait hasIsVisibleField {

    /**
     * @return int|null
     */
    public function getIsVisible()
    {
        return $this->hasField(DBField::IS_VISIBLE) ? $this->field(DBField::IS_VISIBLE) : null;
    }

    /**
     * @return bool
     */
    public function is_visible()
    {
        return $this->getIsVisible() ? true : false;
    }

}

trait hasIsOpenField
{
    /**
     * @return int
     */
    public function getIsOpen()
    {
        return $this->field(DBField::IS_OPEN);
    }

    /**
     * @return bool
     */
    public function is_open()
    {
        return $this->getIsOpen() ? true : false;
    }
}

trait hasIsActiveField {

    /**
     * @return int|null
     */
    public function getIsActive()
    {
        return $this->hasField(DBField::IS_ACTIVE) ? $this->field(DBField::IS_ACTIVE) : null;
    }

    /**
     * @return bool
     */
    public function is_active()
    {
        return $this->getIsActive() ? true : false;
    }

}

trait hasIsPrimaryField {

    /**
     * @return int
     */
    public function getIsPrimary()
    {
        return (int)$this->field(DBField::IS_PRIMARY);
    }

    /**
     * @return bool
     */
    public function is_primary()
    {
        return $this->getIsPrimary() ? true : false;
    }
}


trait hasIsModeratedField {

    /**
     * @return int|null
     */
    public function getIsModerated()
    {
        return $this->hasField(DBField::IS_MODERATED) ? $this->field(DBField::IS_MODERATED) : null;
    }

    /**
     * @return bool
     */
    public function is_moderated()
    {
        return $this->getIsModerated() ? true : false;
    }

}


trait hasErrorMsgField {

    /**
     * @return string|null
     */
    public function getErrorMsg()
    {
        return $this->field(DBField::ERROR_MSG);
    }
}

trait hasMd5Field {

    /**
     * @return string|null
     */
    public function getMd5()
    {
        return $this->field(DBField::MD5);
    }
}

trait hasSha512Field {

    /**
     * @return string
     */
    public function getSha512()
    {
        return $this->field(DBField::SHA512);
    }
}


trait hasDescriptionField {

    /**
     * @return string|null
     */
    public function getDescription()
    {
        return $this->field(DBField::DESCRIPTION);
    }
}

trait hasTitleField {

    /**
     * @return string|null
     */
    public function getTitle()
    {
        return $this->field(DBField::TITLE);
    }
}

trait hasBodyField {

    /**
     * @return string|null
     */
    public function getBody()
    {
        return $this->field(DBField::BODY);
    }
}

trait hasKeyField {

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->field(DBField::KEY);
    }
}

trait hasValueField {

    /**
     * @return string|null
     */
    public function getValue()
    {
        return $this->field(DBField::VALUE);
    }
}

trait hasIndexKeyField
{
    public function getIndexKey()
    {
        return $this->field(DBField::INDEX_KEY);
    }
}

trait hasUserIdField {

    /**
     * @return int|null
     */
    public function getUserId()
    {
        return $this->field(DBField::USER_ID);
    }

    /**
     * @param $userId
     * @return bool
     */
    public function isUserId($userId)
    {
        return $this->getUserId() == $userId;
    }
}
trait hasApplicationIdField
{
    /**
     * @return int
     */
    public function getApplicationId()
    {
        return $this->field(DBField::APPLICATION_ID);
    }
}

trait hasApplicationUserAccessTokenIdField
{
    /**
     * @return int|null
     */
    public function getApplicationUserAccessTokenId()
    {
        return $this->field(DBField::APPLICATION_USER_ACCESS_TOKEN_ID);
    }
}

trait hasApplicationUserIdField
{
    /**
     * @return int
     */
    public function getApplicationUserId()
    {
        return $this->field(DBField::APPLICATION_USER_ID);
    }
}

trait hasUsernameField {

    /**
     * @return int|null
     */
    public function getUsername()
    {
        return $this->field(DBField::USERNAME);
    }
}
trait hasUserGroupIdField {

    /**
     * @return int
     */
    public function getUserGroupId()
    {
        return $this->field(DBField::USERGROUP_ID);
    }
}

trait hasObjectTypeField {

    /**
     * @return null
     */
    public function getObjectType()
    {
        return $this->field(DBField::OBJECT_TYPE);
    }
}


trait hasRightIdField {

    /**
     * @return int|null
     */
    public function getRightId()
    {
        return $this->field(DBField::RIGHT_ID);
    }
}

trait hasRightGroupIdField {

    /**
     * @return int
     */
    public function getRightGroupId()
    {
        return $this->field(DBField::RIGHT_GROUP_ID);
    }

}

trait hasAccessLevelField {

    /**
     * @return int|null
     */
    public function getAccessLevel()
    {
        return $this->field(DBField::ACCESS_LEVEL);
    }
}

trait hasFullNameField {

    /**
     * @return string|null
     */
    public function getFullName()
    {
        return $this->field(DBField::FULL_NAME);
    }
}

trait hasFirstNameField {

    /**
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->field(DBField::FIRST_NAME);
    }
}

trait hasLastNameField {

    /**
     * @return string|null
     */
    public function getLastName()
    {
        return $this->field(DBField::LAST_NAME);
    }
}

trait hasGenderField {

    /**
     * @return int
     */
    public function getGender()
    {
        return $this->field(DBField::GENDER);
    }
}

trait hasSaltField
{
    /**
     * @return mixed
     */
    public function getSalt()
    {
        return $this->field(DBField::SALT);
    }
}

trait hasAddressIdField {

    /**
     * @return int
     */
    public function getAddressId()
    {
        return $this->field(DBField::ADDRESS_ID);
    }
}

trait hasAddressTypeIdField {

    /**
     * @return int
     */
    public function getAddressTypeId()
    {
        return $this->field(DBField::ADDRESS_TYPE_ID);
    }
}

trait hasAddressLine1Field {

    /**
     * @return string
     */
    public function getAddressLine1()
    {
        return $this->field(DBField::ADDRESS_LINE1);
    }
}

trait hasAddressLine2Field {

    /**
     * @return string
     */
    public function getAddressLine2()
    {
        return $this->field(DBField::ADDRESS_LINE2);
    }
}

trait hasAddressLine3Field {

    /**
     * @return string
     */
    public function getAddressLine3()
    {
        return $this->field(DBField::ADDRESS_LINE3);
    }
}

trait hasPhoneNumberField {

    /**
     * @return string
     */
    public function getPhoneNumber()
    {
        return $this->field(DBField::PHONE_NUMBER);
    }
}

trait hasEmailField {

    /**
     * @return string|null
     */
    public function getEmail()
    {
        return $this->field(DBField::EMAIL);
    }
}

trait hasEmailTrackingIdField {

    /**
     * @return int
     */
    public function getEmailTrackingId()
    {
        return $this->field(DBField::EMAIL_TRACKING_ID);
    }
}

trait hasSenderField {

    /**
     * @return string
     */
    public function getSender()
    {
        return $this->field(DBField::SENDER);
    }
}

trait hasEmailAddressField {

    /**
     * @return string
     */
    public function getEmailAddress()
    {
        return $this->field(DBField::EMAIL_ADDRESS);
    }
}

trait hasEmailTypeIdField {

    /**
     * @return int
     */
    public function getEmailTypeId()
    {
        return $this->field(DBField::EMAIL_TYPE_ID);
    }
}


trait hasChecksumField {

    /**
     * @return string
     */
    public function getChecksum()
    {
        return $this->field(DBField::CHECKSUM);
    }
}


trait hasEmailSettingGroupIdField {

    /**
     * @return int|null
     */
    public function getEmailSettingGroupId()
    {
        return $this->field(DBField::EMAIL_SETTING_GROUP_ID);
    }
}

trait hasDefaultSettingField {

    /**
     * @return 0|1
     */
    public function getDefaultSetting()
    {
        return $this->field(DBField::DEFAULT_SETTING);
    }
}

trait hasBetaAccessField {

    /**
     * @return int|null
     */
    public function getHasBetaAccess()
    {
        return $this->field(DBField::HAS_BETA_ACCESS);
    }

    /**
     * @return bool
     */
    public function has_beta_access()
    {
        return $this->getHasBetaAccess() ? true : false;
    }
}

trait hasZipField {

    /**
     * @return string
     */
    public function getZip()
    {
        return $this->field(DBField::ZIP);
    }
}

trait hasZipCodeField {

    /**
     * @return string
     */
    public function getZipCode()
    {
        return $this->field(DBField::ZIP_CODE);
    }
}

trait hasCityField {

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->field(DBField::CITY);
    }

}

trait hasStateField {

    /**
     * @return string
     */
    public function getState()
    {
        return $this->field(DBField::STATE);
    }

}

trait hasFirstSessionIdField {

    /**
     * @return int
     */
    public function getFirstSessionId()
    {
        return $this->field(DBField::FIRST_SESSION_ID);
    }
}

trait hasIpFromField {

    /**
     * @return int
     */
    public function getIpFrom()
    {
        return $this->field(DBField::IP_FROM);
    }
}

trait hasIpToField {

    /**
     * @return int
     */
    public function getIpTo()
    {
        return $this->field(DBField::IP_TO);
    }
}

trait hasCountryIdField {

    /**
     * @return string
     */
    public function getCountryId()
    {
        return $this->field(DBField::COUNTRY_ID);
    }
}

trait hasRegionNameField {

    /**
     * @return string
     */
    public function getRegionName()
    {
        return $this->field(DBField::REGION_NAME);
    }
}

trait hasCityNameField {

    /**
     * @return string
     */
    public function getCityName()
    {
        return $this->field(DBField::CITY_NAME);
    }
}

trait hasLocationHashField {

    /**
     * @return string
     */
    public function getLocationHash()
    {
        return $this->field(DBField::LOCATION_HASH);
    }
}

trait hasLatitudeField {

    /**
     * @return float
     */
    public function getLatitude()
    {
        return $this->field(DBField::LATITUDE);
    }
}

trait hasLongitudeField {

    /**
     * @return float
     */
    public function getLongitude()
    {
        return $this->field(DBField::LONGITUDE);
    }
}

trait hasTimeZoneField {

    /**
     * @return int
     */
    public function getTimeZone()
    {
        return $this->field(DBField::TIME_ZONE);
    }
}

trait hasUiLanguageIdField {

    /**
     * @return string|null
     */
    public function getUiLanguageId()
    {
        return $this->field(DBField::UI_LANGUAGE_ID);
    }
}

trait hasI18nActiveField {

    /**
     * @return int
     */
    public function getI18nActive()
    {
        return $this->field(DBField::I18N_ACTIVE);
    }

    /**
     * @return bool
     */
    public function is_i18n_active()
    {
        return $this->getI18nActive() ? true : false;
    }
}

trait hasI18nPublicField {

    /**
     * @return int
     */
    public function getI18nPublic()
    {
        return $this->field(DBField::I18N_PUBLIC);
    }

    /**
     * @return bool
     */
    public function is_i18n_public()
    {
        return $this->getI18nPublic() ? true : false;
    }
}

trait hasNameField {

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->field(DBField::NAME);
    }
}

trait hasDisplayNameField {

    /**
     * @return string|null
     */
    public function getDisplayName()
    {
        return $this->field(DBField::DISPLAY_NAME);
    }
}

trait hasSsoServiceIdField
{
    /**
     * @return int
     */
    public function getSsoServiceId()
    {
        return $this->field(DBField::SSO_SERVICE_ID);
    }
}

trait hasSsoAccountIdField
{
    /**
     * @return string|int
     */
    public function getSsoAccountId()
    {
        return $this->field(DBField::SSO_ACCOUNT_ID);
    }
}

trait hasScopeField
{
    /**
     * @return string
     */
    public function getScope()
    {
        return $this->field(DBField::SCOPE);
    }
}

trait hasTokenField
{
    /**
     * @return string
     */
    public function getToken()
    {
        return $this->field(DBField::TOKEN);
    }
}

trait hasRefreshTokenField
{
    /**
     * @return string
     */
    public function getRefreshToken()
    {
        return $this->field(DBField::REFRESH_TOKEN);
    }
}

trait hasExpiresOnField
{
    /**
     * @return string
     */
    public function getExpiresOn()
    {
        return $this->field(DBField::EXPIRES_ON);
    }
}

trait hasApiKeyField
{
    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->field(DBField::API_KEY);
    }
}

trait hasPriorityField {

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->field(DBField::PRIORITY);
    }
}

trait hasFileField {

    /**
     * @return string
     */
    public function getFile()
    {
        return $this->field(DBField::FILE);
    }

}

trait hasFileNameField {

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->field(DBField::FILENAME);
    }

}

trait hasFileSizeField {

    /**
     * @return int
     */
    public function getFileSize()
    {
        return $this->field(DBField::FILE_SIZE);
    }
}

trait hasExtensionField {

    public function getExtension()
    {
        return $this->field(DBField::EXTENSION);
    }
}

trait hasComputedFileNameField
{
    /**
     * @return string
     */
    public function getComputedFileName()
    {
        return $this->field(DBField::COMPUTED_FILENAME);
    }
}

trait hasMimeTypeField {

    /**
     * @return string
     */
    public function getMimeType()
    {
        return $this->field(DBField::MIME_TYPE);
    }
}

trait hasBucketPathField {

    /**
     * @return string
     */
    public function getBucketPath()
    {
        return $this->field(DBField::BUCKET_PATH);
    }
}

trait hasBucketField {

    /**
     * @return string
     */
    public function getBucket()
    {
        return $this->field(DBField::BUCKET);
    }
}

trait hasFolderPathField {

    /**
     * @return string|null
     */
    public function getFolderPath()
    {
        return $this->field(DBField::FOLDER_PATH);
    }
}

trait hasFuncField {

    /**
     * @return string
     */
    public function getFunc()
    {
        return $this->field(DBField::FUNC);
    }
}

trait hasArgsField {

    /**
     * @return string
     */
    public function getArgs()
    {
        return $this->field(DBField::ARGS);
    }
}

trait hasDisplayOrderField
{

    /**
     * @return int
     */
    public function getDisplayOrder()
    {
        return $this->field(DBField::DISPLAY_ORDER);
    }
}

trait hasPluralNameField
{

    /**
     * @return string|null
     */
    public function getPluralName()
    {
        return $this->field(DBField::PLURAL_NAME);
    }
}

trait hasSlugField
{

    /**
     * @return string|null
     */
    public function getSlug()
    {
        return $this->field(DBField::SLUG);
    }
}

trait hasAlternateUrlField
{
    /**
     * @return null|string
     */
    public function getAlternateUrl()
    {
        return $this->field(DBField::ALTERNATE_URL);
    }
}

trait hasIsProdField
{
    /**
     * @return int
     */
    public function getIsProd()
    {
        return $this->field(DBField::IS_PROD);
    }

    /**
     * @return bool
     */
    public function is_prod()
    {
        return $this->getIsProd() ? true : false;
    }
}

trait hasHasCustomSlugField
{
    /**
     * @return int
     */
    public function getHasCustomSlug()
    {
        return $this->field(DBField::HAS_CUSTOM_SLUG);
    }

    /**
     * @return bool
     */
    public function has_custom_slug()
    {
        return $this->getHasCustomSlug() ? true : false;
    }
}

trait hasOfflineGameIdField
{
    /**
     * @return int|null
     */
    public function getOfflineGameId()
    {
        return $this->field(DBField::OFFLINE_GAME_ID);
    }
}


trait hasOfflineGameModIdField
{
    /**
     * @return int|null
     */
    public function getOfflineGameModId()
    {
        return $this->field(DBField::OFFLINE_GAME_MOD_ID);
    }
}




trait hasJoinDateField
{

    /**
     * @return string|null
     */
    public function getJoinDate()
    {
        return $this->field(DBField::JOIN_DATE);
    }

    /**
     * @return int
     */
    public function getJoinTime()
    {
        return strtotime($this->getJoinDate());
    }
}

trait hasLastLoginField
{
    /**
     * @return string|null
     */
    public function getLastLogin()
    {
        return $this->field(DBField::LAST_LOGIN);
    }
}

trait hasEntityIdField {

    /**
     * @return int
     */
    public function getEntityId()
    {
        return $this->field(DBField::ENTITY_ID);
    }
}

trait hasCoinAwardTypeIdField
{
    /**
     * @return int
     */
    public function getCoinAwardTypeId()
    {
        return $this->field(DBField::COIN_AWARD_TYPE_ID);
    }
}

trait hasContextEntityIdField
{

    /**
     * @return int
     */
    public function getContextEntityId()
    {
        return $this->field(DBField::CONTEXT_ENTITY_ID);
    }
}

trait hasContextEntityTypeIdField
{

    /**
     * @return int
     */
    public function getContextEntityTypeId()
    {
        return $this->field(DBField::CONTEXT_ENTITY_TYPE_ID);
    }
}


trait hasTypeField
{

    /**
     * @return int
     */
    public function getTypeId()
    {
        return $this->field(DBField::TYPE);
    }

}

trait hasOptionsField
{

    /**
     * @return array|string|null
     */
    public function getOptions($json_decode = false)
    {
        if ($this->hasField(DBField::OPTIONS))
            return $json_decode && $this->field(DBField::OPTIONS) ? json_decode($this->field(DBField::OPTIONS)) : $this->field(DBField::OPTIONS);

        return null;
    }

}


trait hasObjectSourceIdField {

    /**
     * @return null
     */
    public function getObjectSourceId()
    {
        return $this->field(DBField::OBJECT_SOURCE_ID);
    }
}
trait hasOwnerUserIdField {

    /**
     * @return null
     */
    public function getOwnerUserId()
    {
        return $this->field(DBField::OWNER_USER_ID);
    }
}

trait hasObjectIdField {

    /**
     * @return int
     */
    public function getObjectId()
    {
        return $this->field(DBField::OBJECT_ID);
    }
}

trait hasContentField {

    /**
     * @return string|null
     */
    public function getContent()
    {
        return $this->field(DBField::CONTENT);
    }
}

trait hasObjectLangIdField {

    /**
     * @return null
     */
    public function getObjectLangId()
    {
        return $this->field(DBField::OBJECT_LANG);
    }
}

trait hasUpdaterIdField {

    /**
     * @return int
     */
    public function getUpdaterId()
    {
        return $this->field(DBField::UPDATER_ID);
    }
}

trait hasUpdateTimeField {

    /**
     * @return mixed
     */
    public function getUpdateTime()
    {
        return $this->field(DBField::UPDATE_TIME);
    }
}

trait hasCurrencyIdField {

    /**
     * @return int
     */
    public function getCurrencyId()
    {
        return $this->field(DBField::CURRENCY_ID);
    }
}


trait hasParamsField {

    /**
     * @return string
     */
    public function getParams()
    {
        return $this->field(DBField::PARAMS);
    }
}

trait hasOwnerTypeIdField {

    /**
     * @return int
     */
    public function getOwnerTypeId()
    {
        return $this->field(DBField::OWNER_TYPE_ID);
    }
}

trait hasOwnerIdField {

    /**
     * @return int
     */
    public function getOwnerId()
    {
        return $this->field(DBField::OWNER_ID);
    }
}

trait hasVirtualBucketKeyField
{
    /**
     * @return string
     */
    public function getBucketKey()
    {
        return "{$this->getBucketPath()}{$this->getComputedFileName()}";
    }
}

trait hasVirtualUserIsHostAdminField
{
    /**
     * @return bool
     */
    public function user_is_host_admin()
    {
        return $this->getVField(VField::USER_IS_HOST_ADMIN);
    }
}

trait hasVirtualUrlField {

    /**
     * @return string|null
     */
    public function getUrl($param_string = '')
    {
        return $this->field(VField::URL).$param_string;
    }
}

trait hasVirtualEffectiveUrlField {

    /**
     * @return string|null
     */
    public function getEffectiveUrl($suffix = '')
    {
        return $this->field(VField::URL).$suffix;
    }
}

trait hasVirtualActivationsUrlField {

    /**
     * @return string|null
     */
    public function getActivationsUrl($suffix = '')
    {
        return $this->field(VField::ACTIVATIONS_URL).$suffix;
    }
}
trait hasVirtualGamesUrlField {

    /**
     * @return string|null
     */
    public function getGamesUrl($suffix = '')
    {
        return $this->field(VField::GAMES_URL).$suffix;
    }
}
trait hasVirtualModsUrlField {

    /**
     * @return string|null
     */
    public function getModsUrl($suffix = '')
    {
        return $this->field(VField::MODS_URL).$suffix;
    }
}


trait hasVirtualPublicUrlField {

    /**
     * @return string|null
     */
    public function getPublicUrl()
    {
        return $this->field(VField::PUBLIC_URL);
    }
}

trait hasVirtualTargetUrlField {

    /**
     * @return string|null
     */
    public function getTargetUrl()
    {
        return $this->field(VField::TARGET_URL);
    }
}

trait hasVirtualEditUrlField {

    /**
     * @return string|null
     */
    public function getEditUrl($suffix = "")
    {
        return $this->field(VField::EDIT_URL).$suffix;
    }
}

trait hasVirtualAdminEditUrlField {

    /**
     * @return string|null
     */
    public function getAdminEditUrl($suffix = "")
    {
        return $this->field(VField::ADMIN_EDIT_URL).$suffix;
    }
}

trait hasVirtualEditPermissionsUrlField {

    /**
     * @return string
     */
    public function getEditPermissionsUrl()
    {
        return $this->field(VField::EDIT_PERMISSIONS_URL);
    }

}
trait hasVirtualDeleteUrlField {

    /**
     * @return string
     */
    public function getDeleteUrl()
    {
        return $this->field(VField::DELETE_URL);
    }
}

trait hasVirtualCloneUrlField {

    /**
     * @return string
     */
    public function getCloneUrl()
    {
        return $this->field(VField::CLONE_URL);
    }
}
trait hasVirtualGameBuildsField {

    /**
     * @param $updateChannel
     * @return GameBuildEntity|array
     */
    public function getGameBuildByUpdateChannel($updateChannel)
    {
        return $this->dataArray[VField::GAME_BUILDS][$updateChannel] ?? null;
    }

    /**
     * @return GameBuildEntity[]
     */
    public function getGameBuilds()
    {
        return $this->getVField(VField::GAME_BUILDS);
    }

    /**
     * @param GameBuildEntity $gameBuild
     */
    public function setGameBuild(GameBuildEntity $gameBuild)
    {
        $this->dataArray[VField::GAME_BUILDS][$gameBuild->getUpdateChannel()] = $gameBuild;
    }
}

trait hasVirtualAvatarsField
{
    /**
     * @param ImageEntity $image
     */
    public function setAvatarImageUrls(ImageEntity $image)
    {
        foreach ($image->getImageType()->getImageTypeSizes() as $imageTypeSize) {
            $this->dataArray[VField::AVATAR][$imageTypeSize->generateUrlField()] = $image->field($imageTypeSize->generateUrlField());
        }
    }
}

trait hasVirtualGameTypeField
{
    /**
     * @param GameTypeEntity $gameType
     */
    public function setGameType(GameTypeEntity $gameType)
    {
        $this->dataArray[VField::GAME_TYPE] = $gameType;
    }

    /**
     * @return GameTypeEntity
     */
    public function getGameType()
    {
        return $this->getVField(VField::GAME_TYPE);
    }
}

trait hasVirtualGameEngineField {

    /**
     * @param GameEngineEntity $gameEngine
     */
    public function setGameEngine(GameEngineEntity $gameEngine)
    {
        $this->dataArray[VField::GAME_ENGINE] = $gameEngine;
    }

    /**
     * @return GameEngineEntity
     */
    public function getGameEngine()
    {
        return $this->getVField(VField::GAME_ENGINE);
    }
}

trait hasVirtualGameCategoryField
{

    /**
     * @param GameCategoryEntity $gameCategory
     */
    public function setGameCategory(GameCategoryEntity $gameCategory)
    {
        $this->updateField(VField::GAME_CATEGORY, $gameCategory);
    }

    /**
     * @return GameCategoryEntity
     */
    public function getGameCategory()
    {
        return $this->getVField(VField::GAME_CATEGORY);
    }

}

trait hasVirtualGameModsField
{
    /**
     * @param GameModEntity $gameMod
     */
    public function setGameMod(GameModEntity $gameMod)
    {
        $this->dataArray[VField::GAME_MODS][] = $gameMod;
    }

    /**
     * @return GameModEntity[]
     */
    public function getGameMods()
    {
        return $this->getVField(VField::GAME_MODS);
    }
}

trait hasVirtualGameAssetsField
{
    /**
     * @param GameBuildAssetEntity $gameBuildAsset
     */
    public function setGameAsset(GameBuildAssetEntity $gameBuildAsset)
    {
        $this->dataArray[VField::GAME_ASSETS][] = $gameBuildAsset;
    }

    /**
     * @return GameBuildAssetEntity[]
     */
    public function getGameAssets()
    {
        return $this->getVField(VField::GAME_ASSETS);
    }

    /**
     * @return int
     */
    public function getGameAssetsFileSize()
    {
        $fileSize = 0;
        foreach ($this->getGameAssets() as $gameAsset) {
            $fileSize = $fileSize + $gameAsset->getFileSize();
        }

        return $fileSize;
    }


    /**
     * @param Request $request
     * @return array
     */
    public function getVttInterface(Request $request)
    {
        $vttGameAsset = [];

        foreach ($this->getGameAssets() as $gameAsset) {
            if ($gameAsset->getFolderPath() == '.ESCSystem/' && $gameAsset->getFileName() == 'VTTInterface.json') {

                $vttGameAsset = $gameAsset;
                break;
            }
        }

        if (!$vttGameAsset)
            return [];

        try {

            $cacheKey = "esc.games.builds.assets.vtt-interface.{$vttGameAsset->getPk()}";

            /** @var array $vttInterfaceData */
            $vttInterfaceData = $request->cache[$cacheKey];

        } catch (CacheEntryNotFound $c) {

            $rawAssetData = $request->s3->readIntoMemoryAsText(S3::BUCKET_GAME_ASSETS, $vttGameAsset->getBucketKey());

            $vttInterfaceData = json_decode($rawAssetData, true);

            $c->set($vttInterfaceData, ONE_DAY);
        }

        return $vttInterfaceData;
    }
}

trait hasVirtualCustomGameAssetsField
{
    protected $slugsToKeys = [];
    protected $customGameAssets = [];

    /**
     * @param CustomGameAssetEntity $customGameAsset
     */
    public function setCustomGameAsset(CustomGameAssetEntity $customGameAsset)
    {
        $this->dataArray[VField::CUSTOM_GAME_ASSETS][] = $customGameAsset;
        $this->customGameAssets[$customGameAsset->getCustomGameAssetId()] = $customGameAsset;
    }

    /**
     * @return CustomGameAssetEntity[]
     */
    public function getCustomGameAssets()
    {
        return $this->getVField(VField::CUSTOM_GAME_ASSETS);
    }

    /**
     * @param $customGameAssetId
     * @return CustomGameAssetEntity
     */
    public function getCustomGameAssetById($customGameAssetId)
    {
        return $this->customGameAssets[$customGameAssetId];
    }
}

trait hasVirtualCustomGameModAssetsField
{
    /** @var CustomGameModBuildAssetEntity[] */
    protected $customGameModBuildAssets = [];

    /**
     * @param CustomGameModBuildAssetEntity $customGameModAsset
     */
    public function setCustomGameModBuildAsset(CustomGameModBuildAssetEntity $customGameModAsset)
    {
        $this->dataArray[VField::CUSTOM_GAME_ASSETS][] = $customGameModAsset;
        $this->customGameModBuildAssets[$customGameModAsset->getCustomGameAssetId()] = $customGameModAsset;
    }

    /**
     * @return CustomGameModBuildAssetEntity[]
     */
    public function getCustomGameModBuildAssets()
    {
        return $this->getVField(VField::CUSTOM_GAME_ASSETS);
    }

    /**
     * @param $customGameAssetId
     * @return CustomGameModBuildAssetEntity
     */
    public function getCustomGameModBuildAssetById($customGameAssetId)
    {
        return $this->customGameModBuildAssets[$customGameAssetId] ?? [];
    }
}

trait hasVirtualOrganizationField
{

    /**
     * @param OrganizationEntity $organization
     */
    public function setOrganization(OrganizationEntity $organization)
    {
        $this->updateField(VField::ORGANIZATION, $organization);
    }

    /**
     * @return OrganizationEntity
     */
    public function getOrganization()
    {
        if (!$this->getOwnerTypeId() == EntityType::ORGANIZATION)
            return [];
        else
            return $this->field(VField::ORGANIZATION);
    }

}

trait hasVirtualOrganizationBaseRoleField
{
    /**
     * @param OrganizationBaseRoleEntity $organizationBaseRole
     */
    public function setOrganizationBaseRole(OrganizationBaseRoleEntity $organizationBaseRole)
    {
        $this->updateField(VField::ORGANIZATION_BASE_ROLE, $organizationBaseRole);
    }

    /**
     * @return OrganizationBaseRoleEntity
     */
    public function getOrganizationBaseRole()
    {
        return $this->getVField(VField::ORGANIZATION_BASE_ROLE);
    }
}

trait hasVirtualOrganizationBaseRightField
{
    /**
     * @param OrganizationBaseRightEntity $organizationBaseRight
     */
    public function setOrganizationBaseRight(OrganizationBaseRightEntity $organizationBaseRight)
    {
        $this->updateField(VField::ORGANIZATION_BASE_RIGHT, $organizationBaseRight);
    }

    /**
     * @return OrganizationBaseRightEntity
     */
    public function getOrganizationBaseRight()
    {
        return $this->getVField(VField::ORGANIZATION_BASE_RIGHT);
    }
}
trait hasVirtualOrganizationRightsField
{
    /**
     * @param OrganizationRightEntity $organizationRight
     */
    public function setOrganizationRight(OrganizationRightEntity $organizationRight)
    {
        $this->dataArray[VField::ORGANIZATION_RIGHTS][$organizationRight->getPk()] = $organizationRight;
    }

    /**
     * @return OrganizationRightEntity[]
     */
    public function getOrganizationRights()
    {
        return $this->getVField(VField::ORGANIZATION_RIGHTS);
    }

    /**
     * @param $organizationRightId
     * @return OrganizationRightEntity|array
     */
    public function getOrganizationRight($organizationRightId)
    {
        return $this->getOrganizationRights()[$organizationRightId] ?? [];
    }

    /**
     * @param $organizationBaseRightId
     * @return array|OrganizationRightEntity
     */
    public function getOrganizationRightByBaseRightId($organizationBaseRightId)
    {
        foreach ($this->getOrganizationRights() as $organizationRight) {
            if ($organizationRight->getOrganizationBaseRightId() == $organizationBaseRightId)
                return $organizationRight;
        }
        return [];
    }

    /**
     * @return OrganizationRightEntity|array
     */
    public function getOrganizationRightByName($name)
    {
        foreach ($this->getOrganizationRights() as $organizationRight) {
            if ($organizationRight->getName() == $name)
                return $organizationRight;
        }
        return [];
    }
}

trait hasVirtualOrganizationRolesField
{
    /**
     * @param OrganizationRoleEntity $organizationRole
     */
    public function setOrganizationRole(OrganizationRoleEntity $organizationRole)
    {
        $this->dataArray[VField::ORGANIZATION_ROLES][$organizationRole->getPk()] = $organizationRole;
    }

    /**
     * @return OrganizationRoleEntity[]
     */
    public function getOrganizationRoles()
    {
        return $this->getVField(VField::ORGANIZATION_ROLES);
    }

    /**
     * @param $organizationRoleId
     * @return array|OrganizationRoleEntity
     */
    public function getOrganizationRoleById($organizationRoleId)
    {
        return $this->getOrganizationRoles()[$organizationRoleId] ?? [];
    }

    /**
     * @param $organizationBaseRoleId
     * @return array|OrganizationRoleEntity
     */
    public function getOrganizationRoleByOrganizationBaseRoleId($organizationBaseRoleId)
    {
        foreach ($this->getOrganizationRoles() as $organizationRole) {
            if ($organizationRole->getOrganizationBaseRoleId() == $organizationBaseRoleId)
                return $organizationRole;
        }
        return [];
    }
}

trait hasVirtualOrganizationPermissionsField
{
    /**
     * @param OrganizationPermissionEntity $organizationPermission
     */
    public function setOrganizationPermission(OrganizationPermissionEntity $organizationPermission)
    {
        $this->dataArray[VField::ORGANIZATION_PERMISSIONS][$organizationPermission->getPk()] = $organizationPermission;
    }

    /**
     * @return OrganizationPermissionEntity[]
     */
    public function getOrganizationPermissions()
    {
        return $this->getVField(VField::ORGANIZATION_PERMISSIONS);
    }

    /**
     * @param $organizationRoleId
     * @return OrganizationPermissionEntity[]
     */
    public function getOrganizationPermissionsByRoleId($organizationRoleId)
    {
        /** @var OrganizationPermissionEntity[] $permissions */
        $permissions = [];

        foreach ($this->getOrganizationPermissions() as $organizationPermission) {
            if ($organizationPermission->getOrganizationRoleId() == $organizationRoleId)
                $permissions[$organizationPermission->getPk()] = $organizationPermission;
        }

        return $permissions;
    }

    /**
     * @param $organizationRightId
     * @param $organizationRoleId
     * @return array|OrganizationPermissionEntity
     */
    public function getOrganizationPermissionByRightAndRole($organizationRightId, $organizationRoleId)
    {
        foreach ($this->getOrganizationPermissions() as $organizationPermission) {
            if ($organizationPermission->getOrganizationRightId() == $organizationRightId && $organizationPermission->getOrganizationRoleId() == $organizationRoleId)
                return $organizationPermission;
        }

        return [];
    }
}

trait hasVirtualCreatorUserField
{
    /**
     * @param UserEntity $user
     */
    public function setCreatorUser(UserEntity $user)
    {
        $this->updateField(VField::CREATOR_USER, $user);
    }

    /**
     * @return UserEntity
     */
    public function getCreatorUser()
    {
        return $this->getVField(VField::CREATOR_USER);
    }

}

trait hasVirtualOwnerUserField
{
    /**
     * @param UserEntity $user
     */
    public function setOwnerUser(UserEntity $user)
    {
        $this->updateField(VField::OWNER_USER, $user);
    }

    /**
     * @return UserEntity
     */
    public function getOwnerUser()
    {
        return $this->getVField(VField::OWNER_USER);
    }

}

trait hasVirtualEntityField
{
    public function getEntity()
    {
        return $this->getVField(VField::ENTITY);
    }

    /**
     * @param DBManagerEntity $entity
     */
    public function setEntity(DBManagerEntity $entity)
    {
        $this->dataArray[VField::ENTITY] = $entity;
    }
}

trait hasVirtualContextField
{
    public function getContext()
    {
        return $this->getVField(VField::CONTEXT);
    }

    /**
     * @param DBManagerEntity $context
     */
    public function setContext(DBManagerEntity $context)
    {
        $this->dataArray[VField::CONTEXT] = $context;
    }
}

trait hasVirtualOrganizationUsersField
{

    /**
     * @param OrganizationUserEntity $organizationUser
     */
    public function setOrganizationUser(OrganizationUserEntity $organizationUser)
    {
        $this->dataArray[VField::ORGANIZATION_USERS][$organizationUser->getPk()] = $organizationUser;
    }

    /**
     * @return OrganizationUserEntity[]
     */
    public function getOrganizationUsers()
    {
        return $this->getVField(VField::ORGANIZATION_USERS);
    }

    /**
     * @param $organizationUserId
     * @return OrganizationUserEntity|array
     */
    public function getOrganizationUser($organizationUserId)
    {
        return $this->getOrganizationUsers()[$organizationUserId] ?? [];
    }

    /**
     * @param $userId
     * @return null|OrganizationUserEntity
     */
    public function getOrganizationUserByUserId($userId)
    {
        foreach ($this->getOrganizationUsers() as $organizationUser) {
            if ($organizationUser->getUserId() == $userId)
                return $organizationUser;
        }
        return null;
    }

    /**
     * @return int
     */
    public function getOrganizationUsersCount()
    {
        return count($this->getOrganizationUsers());
    }
}

trait hasVirtualOrganizationUserField
{

    /**
     * @param OrganizationUserEntity $organizationUser
     */
    public function setOrganizationUser(OrganizationUserEntity $organizationUser)
    {
        $this->dataArray[VField::ORGANIZATION_USER] = $organizationUser;
    }

    /**
     * @return OrganizationUserEntity
     */
    public function getOrganizationUser()
    {
        return $this->getVField(VField::ORGANIZATION_USER);
    }
}

trait hasVirtualRightField
{
    /**
     * @param RightEntity $right
     */
    public function setRight(RightEntity $right)
    {
        $this->updateField(VField::RIGHT, $right);
    }

    /**
     * @return RightEntity
     */
    public function getRight()
    {
        return $this->getVField(VField::RIGHT);
    }
}

trait hasOrganizationIdField
{
    /**
     * @return int
     */
    public function getOrganizationId()
    {
        return $this->field(DBField::ORGANIZATION_ID);
    }
}

trait hasOrganizationTypeIdField
{
    /**
     * @return int
     */
    public function getOrganizationTypeId()
    {
        return $this->field(DBField::ORGANIZATION_TYPE_ID);
    }

    /**
     * @return bool
     */
    public function is_type_private()
    {
        return $this->getOrganizationTypeId() == OrganizationsTypesManager::ID_PRIVATE;
    }
}

trait hasOrganizationBaseRoleIdField
{
    /**
     * @return int
     */
    public function getOrganizationBaseRoleId()
    {
        return $this->field(DBField::ORGANIZATION_BASE_ROLE_ID);
    }

    /**
     * @return bool
     */
    public function is_admin()
    {
        return $this->getOrganizationBaseRoleId() == OrganizationsBaseRolesManager::ID_ADMINISTRATOR;
    }
}

trait hasOrganizationBaseRightIdField
{
    /**
     * @return int
     */
    public function getOrganizationBaseRightId()
    {
        return $this->field(DBField::ORGANIZATION_BASE_RIGHT_ID);
    }
}

trait hasOrganizationRightIdField
{
    /**
     * @return int
     */
    public function getOrganizationRightId()
    {
        return $this->field(DBField::ORGANIZATION_RIGHT_ID);
    }
}

trait hasOrganizationUserStatusIdField
{
    /**
     * @return int
     */
    public function getOrganizationUserStatusId()
    {
        return $this->field(DBField::ORGANIZATION_USER_STATUS_ID);
    }
}

trait hasOrganizationRoleIdField
{
    /**
     * @return int
     */
    public function getOrganizationRoleId()
    {
        return $this->field(DBField::ORGANIZATION_ROLE_ID);
    }
}

trait hasOrganizationUserIdField
{
    /**
     * @return int
     */
    public function getOrganizationUserId()
    {
        return $this->field(DBField::ORGANIZATION_USER_ID);
    }
}



trait hasCreatorUserIdField {

    /**
     * @return int|null
     */
    public function getCreatorUserId()
    {
        return $this->field(DBField::CREATOR_USER_ID);
    }

}
trait hasCreatorIdField {

    /**
     * @return int|null
     */
    public function getCreatorId()
    {
        return $this->field(DBField::CREATOR_ID);
    }
}

trait hasEtIdField {

    /**
     * @return int|null
     */
    public function getEtId()
    {
        return $this->field(DBField::ET_ID);
    }
}

trait hasActivityIdField {

    /**
     * @return int|null
     */
    public function getActivityId()
    {
        return $this->field(DBField::ACTIVITY_ID);
    }

}

trait hasActivityTypeIdField {

    /**
     * @return int|null
     */
    public function getActivityTypeId()
    {
        return $this->field(DBField::ACTIVITY_TYPE_ID);
    }

}
trait hasGuestIdField {

    /**
     * @return int|null
     */
    public function getGuestId()
    {
        return $this->field(DBField::GUEST_ID);
    }

}

trait hasGuestHashField {

    /**
     * @return int|null
     */
    public function getGuestHash()
    {
        return $this->field(DBField::GUEST_HASH);
    }

}
trait hasSessionIdField {

    /**
     * @return int|null
     */
    public function getSessionId()
    {
        return $this->field(DBField::SESSION_ID);
    }

}
trait hasSessionHashField {

    /**
     * @return int|null
     */
    public function getSessionHash()
    {
        return $this->field(DBField::SESSION_HASH);
    }

}

trait hasFirstUserIdField
{
    /**
     * @return int|null
     */
    public function getFirstUserId()
    {
        return $this->field(DBField::FIRST_USER_ID);
    }
}


trait hasInternalCurrentTimeStampField {

    /**
     * @return mixed
     */
    protected function getCurrentTimeStamp()
    {
        return $this->field(VField::CURRENT_TIMESTAMP);
    }
}

trait hasDynamicFormFieldNameGenerator {

    /**
     * @param $fieldName
     * @return string
     */
    public function getDynamicFormField($fieldName = '', $identifier = '')
    {
        if (!$fieldName)
            $fieldName = $this->getPkField();

        if (!$identifier)
            $identifier = $this->getPk();

        return FormField::createDynamicFieldName($fieldName, $identifier);
    }
}

trait hasPictureField {

    /**
     * @return null
     */
    public function getHasPicture()
    {
        return $this->field(DBField::HAS_PICTURE);
    }

    /**
     * @return bool
     */
    public function has_picture()
    {
        return $this->getHasPicture() ? true : false;
    }

}


trait hasCreateTimeField {

    /**
     * @param string $format
     * @return string
     */
    public function getCreateTime($format = NULL)
    {
        $create_time = $this->field(DBField::CREATE_TIME);

        if (!$format)
            return $create_time;

        $date_time = new DateTime($create_time);

        return $date_time->format($format);
    }

}

trait hasUpdateDateField {

    public function getUpdateDate()
    {
        return $this->field(DBField::UPDATE_DATE);
    }
}

trait hasClickedTimeField {

    /**
     * @return String|null
     */
    public function getClickedTime()
    {
        return $this->field(DBField::CLICKED_TIME);
    }

}

trait hasOpenedTimeField {

    /**
     * @return String|null
     */
    public function getOpenedTime()
    {
        return $this->field(DBField::OPENED_TIME);
    }

}

trait hasSentTimeField {

    /**
     * @return String|null
     */
    public function getSentTime()
    {
        return $this->field(DBField::SENT_TIME);
    }

    /**
     * @return bool
     */
    public function is_sent()
    {
        return $this->getSentTime() ? true : false;
    }

}

trait hasIsOpenedField {

    /**
     * @return int
     */
    public function getIsOpened()
    {
        return $this->field(DBField::IS_OPENED);
    }

    public function is_opened()
    {
        return $this->getIsOpened() ? true : false;
    }
}

trait hasIsClickedField {

    public function getIsClicked()
    {
        return $this->field(DBField::IS_CLICKED);
    }
    /**
     * @return bool
     */
    public function is_clicked()
    {
        return $this->getIsClicked() ? true : false;
    }
}

trait hasGeoRegionIdField {

    /** @var GeoRegionEntity  */
    protected $geo_region = [];

    /**
     * @return int
     */
    public function getGeoRegionId()
    {
        return $this->dataArray[DBField::GEO_REGION_ID];
    }

    /**
     * @param $id
     * @return bool
     */
    public function isGeoRegion($id)
    {
        return $this->getGeoRegionId() == $id;
    }

    /**
     * @param Request $request
     * @return GeoRegionEntity
     */
    public function getGeoRegion(Request $request)
    {
        $geoRegionsManager = $request->managers->geoRegions();

        return !empty($this->geo_region) ? $this->geo_region : $this->geo_region = $geoRegionsManager->getGeoRegionById(
            $request,
            $this->getGeoRegionId()
        );
    }
}


/*
 *
 *
 * New Traits ESC
 *
 *
 */

trait hasPhoneCodeField
{
    /**
     * @return int
     */
    public function getPhoneCode()
    {
        return $this->field(DBField::PHONE_CODE);
    }
}

trait hasIso3Field
{
    /**
     * @return string
     */
    public function getIso3()
    {
        return $this->field(DBField::ISO3);
    }
}

trait hasCreatedByField
{
    /**
     * @return string
     */
    public function getCreatedBy()
    {
        return $this->field(DBField::CREATED_BY);
    }
}

trait hasModifiedByField
{
    /**
     * @return string
     */
    public function getModifiedBy()
    {
        return $this->field(DBField::MODIFIED_BY);
    }
}

trait hasDeletedByField
{
    /**
     * @return string
     */
    public function getDeletedBy()
    {
        return $this->field(DBField::DELETED_BY);
    }
}

trait hasHostIdField
{
    /**
     * @return int
     */
    public function getHostId()
    {
        return $this->field(DBField::HOST_ID);
    }
}

trait hasActivationTypeIdField
{
    /**
     * @return int
     */
    public function getActivationTypeId()
    {
        return $this->field(DBField::ACTIVATION_TYPE_ID);
    }
}

trait hasActivationStatusIdField
{
    /**
     * @return int
     */
    public function getActivationStatusId()
    {
        return $this->field(DBField::ACTIVATION_STATUS_ID);
    }
}

trait hasActivationGroupIdField
{
    /**
     * @return int
     */
    public function getActivationGroupId()
    {
        return $this->field(DBField::ACTIVATION_GROUP_ID);
    }
}

trait hasLocationIdField
{
    /**
     * @return int
     */
    public function getLocationId()
    {
        return $this->field(DBField::LOCATION_ID);
    }
}

trait hasScreenIdField
{
    /**
     * @return int
     */
    public function getScreenId()
    {
        return $this->field(DBField::SCREEN_ID);
    }
}

trait hasIpAddressField
{
    /**
     * @return string
     */
    public function getIpAddress()
    {
        return $this->field(DBField::IP_ADDRESS);
    }
}

trait hasNetworkIdField
{
    /**
     * @return int
     */
    public function getNetworkId()
    {
        return $this->field(DBField::NETWORK_ID);
    }
}

trait hasSsidField
{
    /**
     * @return string
     */
    public function getSsid()
    {
        return $this->field(DBField::SSID);
    }
}

trait hasPasswordField
{
    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->field(DBField::PASSWORD);
    }
}

trait hasUrlField
{
    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->field(DBField::URL);
    }
}

trait hasClientSessionIdField
{
    /**
     * @return int
     */
    public function getClientSessionId()
    {
        return $this->field(DBField::CLIENT_SESSION_ID);
    }
}


trait hasDeviceIdField
{
    /**
     * @return int
     */
    public function getDeviceId()
    {
        return $this->field(DBField::DEVICE_ID);
    }
}

trait hasDeviceTypeIdField
{
    /**
     * @return int
     */
    public function getDeviceTypeId()
    {
        return $this->field(DBField::DEVICE_TYPE_ID);
    }
}

trait hasSchemeField
{
    /**
     * @return string
     */
    public function getScheme()
    {
        return $this->field(DBField::SCHEME);
    }
}

trait hasMethodField
{
    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->field(DBField::METHOD);
    }
}

trait hasHostField
{
    /**
     * @return string
     */
    public function getHost()
    {
        return $this->field(DBField::HOST);
    }
}

trait hasAppField
{
    /**
     * @return string
     */
    public function getApp()
    {
        return $this->field(DBField::APP);
    }
}

trait hasUriField
{
    /**
     * @return string
     */
    public function getUri()
    {
        return $this->field(DBField::URI);
    }
}

trait hasReferrerField
{
    /**
     * @return string|null
     */
    public function getReferrer()
    {
        return $this->field(DBField::REFERRER);
    }
}

trait hasAcqMediumField
{
    /**
     * @return string|null
     */
    public function getAcqMedium()
    {
        return $this->field(DBField::ACQ_MEDIUM);
    }
}

trait hasAcqSourceField
{
    /**
     * @return string|null
     */
    public function getAcqSource()
    {
        return $this->field(DBField::ACQ_SOURCE);
    }
}

trait hasAcqCampaignField
{
    /**
     * @return string|null
     */
    public function getAcqCampaign()
    {
        return $this->field(DBField::ACQ_CAMPAIGN);
    }
}

trait hasTotalViewsField
{
    /**
     * @return int
     */
    public function getTotalViews()
    {
        return $this->field(DBField::TOTAL_VIEWS);
    }
}

trait hasAcqTermField
{
    /**
     * @return string|null
     */
    public function getAcqTerm()
    {
        return $this->field(DBField::ACQ_TERM);
    }
}

trait hasResponseTimeField
{
    /**
     * @return int
     */
    public function getResponseTime()
    {
        return $this->field(DBField::RESPONSE_TIME);
    }
}

trait hasResponseCodeField
{
    /**
     * @return int
     */
    public function getResponseCode()
    {
        return $this->field(DBField::RESPONSE_CODE);
    }
}

/*
 * Games Data
 */

trait hasGameDataIdField
{
    /**
     * @return int
     */
    public function getGameDataId()
    {
        return $this->field(DBField::GAME_DATA_ID);
    }
}

trait hasGameDataSheetModTypeIdField
{
    /**
     * @return int
     */
    public function getGameDataSheetModTypeId()
    {
        return $this->field(DBField::GAME_DATA_SHEET_MOD_TYPE_ID);
    }

    /**
     * @return bool
     */
    public function is_mod_replace()
    {
        return $this->getGameDataSheetModTypeId() == GamesDataSheetsModsTypesManager::ID_REPLACE;
    }

    /**
     * @return bool
     */
    public function is_mod_replace_cascade()
    {
        return $this->getGameDataSheetModTypeId() == GamesDataSheetsModsTypesManager::ID_REPLACE_CASCADE;
    }

    /**
     * @return bool
     */
    public function is_mod_append()
    {
        return $this->getGameDataSheetModTypeId() == GamesDataSheetsModsTypesManager::ID_APPEND;
    }
}

trait hasGameDataSheetColumnIdField
{
    /**
     * @return int|null
     */
    public function getGameDataSheetColumnId()
    {
        return $this->field(DBField::GAME_DATA_SHEET_COLUMN_ID);
    }
}

trait hasCanModField
{
    /**
     * @return int
     */
    public function getCanMod()
    {
        return $this->field(DBField::CAN_MOD);
    }

    /**
     * @return bool
     */
    public function can_mod()
    {
        return $this->getCanMod() ? true : false;
    }

    /**
     * @param bool $canMod
     */
    public function setCanMod($canMod = true)
    {
        $this->dataArray[DBField::CAN_MOD] = $canMod ? 1 : 0;
    }
}

trait hasGameDataSheetIdField
{
    /**
     * @return int
     */
    public function getGameDataSheetId()
    {
        return $this->field(DBField::GAME_DATA_SHEET_ID);
    }
}

/*
 * Games Mods
 */

trait hasGameModIdField
{
    /**
     * @return int
     */
    public function getGameModId()
    {
        return $this->field(DBField::GAME_MOD_ID);
    }
}

trait hasActivationIdField
{
    /**
     * @return int|null
     */
    public function getActivationId()
    {
        return $this->field(DBField::ACTIVATION_ID);
    }
}

trait hasGameModBuildIdField
{
    /**
     * @return int
     */
    public function getGameModBuildId()
    {
        return $this->field(DBField::GAME_MOD_BUILD_ID);
    }
}

trait hasFirstActiveGameBuildIdField
{
    /**
     * @return int
     */
    public function getFirstActiveGameBuildId()
    {
        return $this->field(DBField::FIRST_ACTIVE_GAME_BUILD_ID);
    }
}
trait hasPublishedTimeField
{
    /**
     * @return string
     */
    public function getPublishedTime()
    {
        return $this->field(DBField::PUBLISHED_TIME);
    }
}

trait hasPublishedGameModBuildIdField
{
    /**
     * @return int
     */
    public function getPublishedGameModBuildIdField()
    {
        return $this->field(DBField::PUBLISHED_GAME_MOD_BUILD_ID);
    }
}

trait hasGameModDataIdField
{
    /**
     * @return int
     */
    public function getGameModDataId()
    {
        return $this->field(DBField::GAME_MOD_DATA_ID);
    }
}

trait hasGameModDataSheetColumnIdField
{
    /**
     * @return int
     */
    public function getGameModDataSheetColumnId()
    {
        return $this->field(DBField::GAME_MOD_DATA_SHEET_COLUMN_ID);
    }
}

trait hasGameModDataSheetIdField
{
    /**
     * @return int
     */
    public function getGameModDataSheetId()
    {
        return $this->field(DBField::GAME_MOD_DATA_SHEET_ID);
    }
}



/*
 * Games
 */

trait hasGameIdField
{
    /**
     * @return int
     */
    public function getGameId()
    {
        return $this->field(DBField::GAME_ID);
    }
}

trait hasGamePlayerStatTypeIdField
{
    /**
     * @return int
     */
    public function getGamePlayerStatTypeId()
    {
        return $this->field(DBField::GAME_PLAYER_STAT_TYPE_ID);
    }
}

trait hasEventKeyField
{
    /**
     * @return string
     */
    public function getEventKey()
    {
        return $this->field(DBField::EVENT_KEY);
    }
}

trait hasUuidField
{
    /**
     * @return string
     */
    public function getUuid()
    {
        return $this->field(DBField::UUID);
    }
}

trait hasPlatformIdField
{
    /**
     * @return int
     */
    public function getPlatformId()
    {
        return $this->field(DBField::PLATFORM_ID);
    }
}

trait hasPlatformVersionIdField
{
    /**
     * @return int
     */
    public function getPlatformVersionId()
    {
        return $this->field(DBField::PLATFORM_VERSION_ID);
    }
}

trait hasSdkPlatformIdField
{
    /**
     * @return int
     */
    public function getSdkPlatformId()
    {
        return $this->field(DBField::SDK_PLATFORM_ID);
    }
}


trait hasSdkPlatformVersionIdField
{
    /**
     * @return int
     */
    public function getPlatformVersionId()
    {
        return $this->field(DBField::SDK_PLATFORM_VERSION_ID);
    }
}

trait hasGameInstanceLogIdField
{
    /**
     * @return int
     */
    public function getGameInstanceLogId()
    {
        return $this->field(DBField::GAME_INSTANCE_LOG_ID);
    }
}

trait hasGameInstanceLogStatusIdField
{
    /**
     * @return int
     */
    public function getGameInstanceLogStatusId()
    {
        return $this->field(DBField::GAME_INSTANCE_LOG_STATUS_ID);
    }
}

trait hasVirtualGameInstanceLogStatusField
{
    /**
     * @return GameInstanceLogStatusEntity
     */
    public function getGameInstanceLogStatus()
    {
        return $this->getVField(VField::GAME_INSTANCE_LOG_STATUS);
    }

    /**
     * @param GameInstanceLogStatusEntity $gameInstanceLogStatus
     */
    public function setGameInstanceLogStatus(GameInstanceLogStatusEntity $gameInstanceLogStatus)
    {
        $this->updateField(VField::GAME_INSTANCE_LOG_STATUS, $gameInstanceLogStatus);
    }
}



trait hasGameInstanceIdField
{
    /**
     * @return int
     */
    public function getGameInstanceId()
    {
        return $this->field(DBField::GAME_INSTANCE_ID);
    }
}

trait hasGameInstanceRoundIdField
{
    /**
     * @return int
     */
    public function getGameInstanceRoundId()
    {
        return $this->field(DBField::GAME_INSTANCE_ROUND_ID);
    }
}

trait hasGameInstanceRoundPlayerIdField
{
    /**
     * @return int
     */
    public function getGameInstanceRoundPlayerId()
    {
        return $this->field(DBField::GAME_INSTANCE_ROUND_PLAYER_ID);
    }
}

trait hasGameInstanceRoundEventIdField
{
    /**
     * @return int
     */
    public function getGameInstanceRoundEventId()
    {
        return $this->field(DBField::GAME_INSTANCE_ROUND_EVENT_ID);
    }
}


trait hasGameAssetIdField
{
    /**
     * @return int
     */
    public function getGameAssetId()
    {
        return $this->field(DBField::GAME_ASSET_ID);
    }
}
trait hasGameControllerIdField
{
    /**
     * @return int
     */
    public function getGameControllerId()
    {
        return $this->field(DBField::GAME_CONTROLLER_ID);
    }
}
trait hasGameBuildIdField
{
    /**
     * @return int
     */
    public function getGameBuildId()
    {
        return $this->field(DBField::GAME_BUILD_ID);
    }
}

trait hasPublishedGameBuildIdField
{
    /**
     * @return int
     */
    public function getPublishedGameBuildId()
    {
        return $this->field(DBField::PUBLISHED_GAME_BUILD_ID);
    }

    /**
     * @return bool
     */
    public function is_published()
    {
        return $this->getPublishedGameBuildId() ? true : false;
    }
}

trait hasHostBuildIdField
{
    /**
     * @return int
     */
    public function getHostBuildId()
    {
        return $this->field(DBField::HOST_BUILD_ID);
    }
}

trait hasHostControllerIdField
{
    /**
     * @return int
     */
    public function getHostControllerId()
    {
        return $this->field(DBField::HOST_CONTROLLER_ID);
    }
}

trait hasHostInstanceIdField
{
    /**
     * @return int
     */
    public function getHostInstanceId()
    {
        return $this->field(DBField::HOST_INSTANCE_ID);
    }
}

trait hasDeviceHashField
{
    /**
     * @return string
     */
    public function getDeviceHash()
    {
        return $this->field(DBField::DEVICE_HASH);
    }
}

trait hasMinimumPlayersField
{
    /**
     * @return int
     */
    public function getMinimumPlayers()
    {
        return $this->field(DBField::MINIMUM_PLAYERS);
    }
}

trait hasMaximumPlayersField
{
    /**
     * @return int
     */
    public function getMaximumPlayers()
    {
        return $this->field(DBField::MAXIMUM_PLAYERS);
    }
}

trait hasGameTypeIdField
{
    /**
     * @return int
     */
    public function getGameTypeId()
    {
        return $this->field(DBField::GAME_TYPE_ID);
    }

    /**
     * @return bool
     */
    public function is_type_offline_game()
    {
        return $this->getGameTypeId() == GamesTypesManager::ID_CLOUD_GAME;
    }
}

trait hasGameCategoryIdField
{
    /**
     * @return int
     */
    public function getGameCategoryId()
    {
        return $this->field(DBField::GAME_CATEGORY_ID);
    }
}

trait hasGameEngineIdField
{
    /**
     * @return int
     */
    public function getGameEngineId()
    {
        return $this->field(DBField::GAME_ENGINE_ID);
    }
}

trait hasLatestGameBuildIdField
{
    /**
     * @return int|null
     */
    public function getLatestGameBuildId()
    {
        return $this->field(DBField::LATEST_GAME_BUILD_ID);
    }
}

trait hasIsWanEnabledField
{
    /**
     * @return int
     */
    public function getIsWanEnabled()
    {
        return $this->field(DBField::IS_WAN_ENABLED);
    }

    /**
     * @return bool
     */
    public function is_wan_enabled()
    {
        return $this->getIsWanEnabled() ? true : false;
    }
}

trait hasIsAggregateGameField
{
    /**
     * @return int
     */
    public function getIsAggregateGame()
    {
        return $this->field(DBField::IS_AGGREGATE_GAME);
    }

    /**
     * @return bool
     */
    public function is_aggregate_game()
    {
        return $this->getIsAggregateGame() ? true : false;
    }
}

trait hasIsDownloadableField
{
    /**
     * @return int
     */
    public function getIsDownloadable()
    {
        return $this->field(DBField::IS_DOWNLOADABLE);
    }

    /**
     * @return bool
     */
    public function is_downloadable()
    {
        return $this->getIsDownloadable() ? true : false;
    }
}

trait hasActiveTestGameBuildIdField
{
    /**
     * @return int|null
     */
    public function getActiveTestGameBuildId()
    {
        return $this->field(DBField::ACTIVE_TEST_GAME_BUILD_ID);
    }
}

trait hasGameBuildVersionField
{
    /**
     * @return string
     */
    public function getGameBuildVersion()
    {
        return $this->field(DBField::GAME_BUILD_VERSION);
    }
}

trait hasGameControllerVersionField
{
    /**
     * @return string
     */
    public function getGameControllerVersion()
    {
        return $this->field(DBField::GAME_CONTROLLER_VERSION);
    }
}

trait hasBuildVersionField
{
    /**
     * @return int
     */
    public function getBuildVersion()
    {
        return $this->field(DBField::BUILD_VERSION);
    }
}
trait hasVersionHashField
{
    /**
     * @return string
     */
    public function getVersionHash()
    {
        return $this->field(DBField::VERSION_HASH);
    }
}

trait hasVirtualParsedDescriptionField
{
    /**
     * @return null|string
     */
    public function getParsedDescription()
    {
        return $this->getVField(VField::PARSED_DESCRIPTION);
    }

}

trait hasVirtualProcessedValuesField
{
    /**
     * @return array
     */
    public function getProcessedValues()
    {
        return $this->getVField(VField::PROCESSED_VALUES);
    }

    /**
     * @param $key
     * @return null
     */
    public function getProcessedValueByKey($key)
    {
        return $this->getProcessedValues()[$key] ?? null;
    }
}

trait hasVirtualHostControllerAssetIdField
{
    /**
     * @return int
     */
    public function getHostControllerAssetId()
    {
        return $this->getVField(VField::HOST_CONTROLLER_ASSET_ID);
    }
}

trait hasVirtualHostBuildAssetIdField
{
    /**
     * @return int
     */
    public function getHostBuildAssetId()
    {
        return $this->getVField(VField::HOST_BUILD_ASSET_ID);
    }
}

trait hasVirtualGameControllerAssetIdField
{
    /**
     * @return int
     */
    public function getGameControllerAssetId()
    {
        return $this->getVField(VField::GAME_CONTROLLER_ASSET_ID);
    }
}

trait hasVirtualGameBuildAssetIdField
{
    /**
     * @return int
     */
    public function getGameBuildAssetId()
    {
        return $this->getVField(VField::GAME_BUILD_ASSET_ID);
    }
}


trait hasVirtualCustomGameAssetIdField
{
    /**
     * @return int
     */
    public function getCustomGameAssetId()
    {
        return $this->getVField(VField::CUSTOM_GAME_ASSET_ID);
    }
}


trait hasVirtualGameActiveCustomAssetField
{
    /**
     * @param GameActiveCustomAssetEntity $gameActiveCustomAsset
     */
    public function setGameActiveCustomAsset(GameActiveCustomAssetEntity $gameActiveCustomAsset)
    {
        $this->dataArray[VField::GAME_ACTIVE_CUSTOM_ASSET] = $gameActiveCustomAsset;
    }

    /**
     * @return GameActiveCustomAssetEntity
     */
    public function getGameActiveCustomAsset()
    {
        return $this->getVField(VField::GAME_ACTIVE_CUSTOM_ASSET);
    }
}


trait hasVirtualGameInstanceLogAssetIdField
{
    /**
     * @return int
     */
    public function getGameInstanceLogAssetId()
    {
        return $this->getVField(VField::GAME_INSTANCE_LOG_ASSET_ID);
    }
}

trait hasContextXGameAssetIdField
{
    /**
     * @return int
     */
    public function getContextXGameAssetId()
    {
        return $this->field(DBField::CONTEXT_X_GAME_ASSET_ID);
    }
}

trait hasVirtualGameControllerTypeField
{
    /**
     * @return GameControllerTypeEntity
     */
    public function getGameControllerType()
    {
        return $this->field(VField::GAME_CONTROLLER_TYPE);
    }

    /**
     * @param GameControllerTypeEntity $gameControllerType
     */
    public function setGameControllerType(GameControllerTypeEntity $gameControllerType)
    {
        $this->updateField(VField::GAME_CONTROLLER_TYPE, $gameControllerType);
    }
}

trait hasGameControllerTypeIdField
{
    public function getGameControllerTypeId()
    {
        return $this->field(DBField::GAME_CONTROLLER_TYPE_ID);
    }
}

trait hasPublicHostNameField
{
    /**
     * @return string
     */
    public function getPublicHostName()
    {
        return $this->field(DBField::PUBLIC_HOST_NAME);
    }
}

trait hasPublicHostDomainField
{
    /**
     * @return string
     */
    public function getPublicHostDomain()
    {
        return $this->field(DBField::PUBLIC_HOST_DOMAIN);
    }
}

trait hasDnsIdField
{
    /**
     * @return string
     */
    public function getDnsId()
    {
        return $this->field(DBField::DNS_ID);
    }
}

trait hasPublicIpAddressField
{
    /**
     * @return string
     */
    public function getPublicIpAddress()
    {
        return $this->field(DBField::PUBLIC_IP_ADDRESS);
    }
}

trait hasLocalIpAddressField
{
    /**
     * @return string
     */
    public function getLocalIpAddress()
    {
        return $this->field(DBField::LOCAL_IP_ADDRESS);
    }
}
trait hasLocalPortField
{
    /**
     * @return int
     */
    public function getLocalPort()
    {
        return $this->field(DBField::LOCAL_PORT);
    }
}


trait hasShortUrlIdField
{
    /**
     * @return int
     */
    public function getShortUrlId()
    {
        return $this->field(DBField::SHORT_URL_ID);
    }
}


/**
 * SMS
 */

trait hasIsSentField {

    /**
     * @return int
     */
    public function getIsSent()
    {
        return $this->field(DBField::IS_SENT);
    }

    /**
     * @return bool
     */
    public function is_sent()
    {
        return $this->getIsSent() ? true : false;
    }
}

trait hasSmsTypeIdField
{
    /**
     * @return int
     */
    public function getSmsTypeId()
    {
        return $this->field(DBField::SMS_TYPE_ID);
    }
}

trait hasScheduleTimeField {

    /**
     * @return string
     */
    public function getScheduleTime()
    {
        return $this->field(DBField::SCHEDULE_TIME);
    }
}

trait hasToNumberField {

    /**
     * @return string
     */
    public function getToNumber()
    {
        return $this->field(DBField::TO_NUMBER);
    }
}

trait hasFromNumberField {

    /**
     * @return string
     */
    public function getFromNumber()
    {
        return $this->field(DBField::FROM_NUMBER);
    }
}

// Game Data

trait hasStartTimeField {

    /**
     * @return string
     */
    public function getStartTime()
    {
        return $this->field(DBField::START_TIME);
    }

    /**
     * @return string
     */
    public function getLocalStartTime()
    {
        return $this->field(VField::LOCAL_START_TIME);
    }

}

trait hasEndTimeField {

    /**
     * @return string
     */
    public function getEndTime()
    {
        return $this->field(DBField::END_TIME);
    }

    /**
     * @return string
     */
    public function getLocalEndTime()
    {
        return $this->field(VField::LOCAL_END_TIME);
    }


    /**
     * @return bool
     */
    public function has_ended()
    {
        return $this->getEndTime() ? true : false;
    }
}

trait hasPlayerRequestIdField
{
    /**
     * @return string
     */
    public function getPlayerRequestId()
    {
        return $this->field(DBField::PLAYER_REQUEST_ID);
    }
}

trait hasPubSubChannelField
{
    /**
     * @return string
     */
    public function getPubSubChannel()
    {
        return $this->field(DBField::PUB_SUB_CHANNEL);
    }
}

trait hasPubSubChannelTypeField
{
    /**
     * @return string
     */
    public function getPubSubChannelType()
    {
        return $this->field(DBField::PUB_SUB_CHANNEL_TYPE);
    }
}

trait hasMessageCountField
{
    /**
     * @return int
     */
    public function getMessageCount()
    {
        return $this->field(DBField::MESSAGE_COUNT);
    }
}

trait hasProcessingTimeField
{
    /**
     * @return int
     */
    public function getProcessingTime()
    {
        return $this->field(DBField::PROCESSING_TIME);
    }
}

trait hasExitStatusField
{
    /**
     * @return string|null
     */
    public function getExitStatus()
    {
        return $this->field(DBField::EXIT_STATUS);
    }
}
trait hasLastPingTimeField
{
    /**
     * @return string|null
     */
    public function getLastPingTime()
    {
        return $this->field(DBField::LAST_PING_TIME);
    }
}

trait hasDnsIsActiveField
{
    /**
     * @return int
     */
    public function getDnsIsActive()
    {
        return $this->field(DBField::DNS_IS_ACTIVE);
    }

    /**
     * @return bool
     */
    public function dns_is_active()
    {
        return $this->getDnsIsActive() ? true : false;
    }
}


// Virtual Fields for Sub Entities


trait hasVirtualHostInstanceTypeField
{
    /**
     * @return HostInstanceTypeEntity
     */
    public function getHostInstanceType()
    {
        return $this->getVField(VField::HOST_INSTANCE_TYPE);
    }

    /**
     * @param HostInstanceTypeEntity $hostInstanceType
     */
    public function setHostInstanceType(HostInstanceTypeEntity $hostInstanceType)
    {
        $this->updateField(VField::HOST_INSTANCE_TYPE, $hostInstanceType);
    }
}

trait hasVirtualHostField
{
    /**
     * @return HostEntity
     */
    public function getHost()
    {
        return $this->getVField(VField::HOST);
    }

    /**
     * @param HostEntity $host
     */
    public function setHost(HostEntity $host)
    {
        $this->updateField(VField::HOST, $host);
    }
}

trait hasVirtualHostsField
{
    /**
     * @return HostEntity
     */
    public function getHosts()
    {
        return $this->getVField(VField::HOST);
    }

    /**
     * @param HostEntity $host
     */
    public function setHost(HostEntity $host)
    {
        $this->dataArray[VField::HOSTS][] = $host;
    }
}

trait hasVirtualLocationField
{
    /**
     * @return LocationEntity
     */
    public function getLocation()
    {
        return $this->getVField(VField::LOCATION);
    }

    /**
     * @param LocationEntity $location
     */
    public function setLocation(LocationEntity $location)
    {
        $this->updateField(VField::LOCATION, $location);
    }
}

trait hasVirtualActivationsField
{
    /**
     * @return ActivationEntity[]
     */
    public function getActivations()
    {
        return $this->getVField(VField::ACTIVATIONS);
    }

    /**
     * @param $activationId
     * @return ActivationEntity|array
     */
    public function getActivationById($activationId)
    {
        foreach ($this->getActivations() as $activation) {
            if ($activation->getPk() == $activationId)
                return $activation;
        }

        return [];
    }

    /**
     * @param ActivationEntity $activation
     */
    public function setActivation(ActivationEntity $activation)
    {
        $this->dataArray[VField::ACTIVATIONS][] = $activation;
    }
}

trait hasVirtualHostVersionField
{
    /**
     * @return HostVersionEntity
     */
    public function getHostVersion()
    {
        return $this->getVField(VField::HOST_VERSION);
    }

    /**
     * @param HostVersionEntity $hostVersion
     */
    public function setHostVersion(HostVersionEntity $hostVersion)
    {
        $this->updateField(VField::HOST_VERSION, $hostVersion);
    }
}

trait hasVirtualPlatformField
{
    /**
     * @return PlatformEntity
     */
    public function getPlatform()
    {
        return $this->getVField(VField::PLATFORM);
    }

    /**
     * @param PlatformEntity $platform
     */
    public function setPlatform(PlatformEntity $platform)
    {
        $this->updateField(VField::PLATFORM, $platform);
    }
}

trait hasVirtualAddressField
{
    /**
     * @return AddressEntity
     */
    public function getAddress()
    {
        return $this->getVField(VField::ADDRESS);
    }

    /**
     * @param AddressEntity $address
     */
    public function setAddress(AddressEntity $address)
    {
        $this->updateField(VField::ADDRESS, $address);
    }
}

trait hasVirtualGameField
{
    /**
     * @param GameEntity $game
     */
    public function setGame(GameEntity $game)
    {
        $this->dataArray[VField::GAME] = $game;
    }

    /**
     * @return GameEntity
     */
    public function getGame()
    {
        return $this->getVField(VField::GAME);
    }
}

trait hasVirtualGameBuildField
{
    /**
     * @param GameBuildEntity $gameBuild
     */
    public function setGameBuild(GameBuildEntity $gameBuild)
    {
        $this->dataArray[VField::GAME_BUILD] = $gameBuild;
    }

    /**
     * @return GameBuildEntity|null
     */
    public function getGameBuild()
    {
        return $this->getVField(VField::GAME_BUILD);
    }

}

trait hasVirtualGameModBuildField
{
    /**
     * @param GameModBuildEntity $gameModBuild
     */
    public function setGameModBuild(GameModBuildEntity $gameModBuild)
    {
        $this->dataArray[VField::GAME_MOD_BUILD] = $gameModBuild;
    }

    /**
     * @return GameModBuildEntity|null
     */
    public function getGameModBuild()
    {
        return $this->getVField(VField::GAME_MOD_BUILD);
    }

}

trait hasVirtualActivityField
{
    /**
     * @param ActivityEntity $activity
     */
    public function setActivity(ActivityEntity $activity)
    {
        $this->dataArray[VField::ACTIVITY] = $activity;
    }

    /**
     * @return ActivityEntity
     */
    public function getActivity()
    {
        return $this->getVField(VField::ACTIVITY);
    }
}

trait hasVirtualActivationField
{
    /**
     * @param ActivationEntity $activation
     */
    public function setActivation(ActivationEntity $activation)
    {
        $this->dataArray[VField::ACTIVATION] = $activation;
    }

    /**
     * @return ActivationEntity
     */
    public function getActivation()
    {
        return $this->getVField(VField::ACTIVATION);
    }
}

trait hasVirtualGameInstanceRoundsField
{
    /**
     * @param GameInstanceRoundEntity $gameInstanceRound
     */
    public function setGameInstanceRound(GameInstanceRoundEntity $gameInstanceRound)
    {
        $this->dataArray[VField::GAME_INSTANCE_ROUNDS][] = $gameInstanceRound;
    }

    /**
     * @return GameInstanceRoundEntity[]
     */
    public function getGameInstanceRounds()
    {
        return $this->getVField(VField::GAME_INSTANCE_ROUNDS);
    }
}

trait hasVirtualGameInstanceLogsField
{
    /**
     * @return GameInstanceLogEntity[]
     */
    public function getGameInstanceLogs()
    {
        return $this->getVField(VField::GAME_INSTANCE_LOGS);
    }

    /**
     * @param GameInstanceLogEntity $gameInstanceLog
     */
    public function setGameInstanceLog(GameInstanceLogEntity $gameInstanceLog)
    {
        $this->dataArray[VField::GAME_INSTANCE_LOGS][$gameInstanceLog->getPubSubChannelType()] = $gameInstanceLog;
    }

    /**
     * @param $pubSubChannelType
     * @return GameInstanceLogEntity
     */
    public function getGameInstanceLogByPubSubChannelType($pubSubChannelType)
    {
        return $this->dataArray[VField::GAME_INSTANCE_LOGS][$pubSubChannelType] ?? [];
    }
}


trait hasVirtualGameModField
{
    /**
     * @param GameModEntity $gameMod
     */
    public function setGameMod(GameModEntity $gameMod)
    {
        $this->dataArray[VField::GAME_MOD] = $gameMod;
    }

    /**
     * @return GameModEntity
     */
    public function getGameMod()
    {
        return $this->getVField(VField::GAME_MOD);
    }
}

trait hasVirtualGameAssetField
{
    /**
     * @param CustomGameAssetEntity $customGameAsset
     */
    public function setGameAsset(CustomGameAssetEntity $customGameAsset)
    {
        $this->dataArray[VField::GAME_ASSET] = $customGameAsset;
    }
    /**
     * @return CustomGameAssetEntity|null
     */
    public function getGameAsset()
    {
        return $this->field(VField::GAME_ASSET);
    }
}


trait hasVirtualGameInstanceLogAssetField
{
    /**
     * @param GameInstanceLogAssetEntity $gameInstanceLogEntity
     */
    public function setGameInstanceLogAsset(GameInstanceLogAssetEntity $gameInstanceLogEntity)
    {
        $this->dataArray[VField::GAME_INSTANCE_LOG_ASSET] = $gameInstanceLogEntity;
    }

    /**
     * @return GameInstanceLogAssetEntity
     */
    public function getGameInstanceLogAsset()
    {
        return $this->getVField(VField::GAME_INSTANCE_LOG_ASSET);
    }

}

trait hasVirtualGameModAssetField
{
    /**
     * @param CustomGameModBuildAssetEntity $customGameAsset
     */
    public function setGameAsset(CustomGameModBuildAssetEntity $customGameAsset)
    {
        $this->dataArray[VField::GAME_ASSET] = $customGameAsset;
    }
    /**
     * @return CustomGameModBuildAssetEntity|null
     */
    public function getGameAsset()
    {
        return $this->field(VField::GAME_ASSET);
    }
}

trait hasVirtualGameModDataSheetsField
{
    /** @var GameModDataSheetEntity[] */
    protected $indexedGameModDataSheets = [];
    /** @var GameModDataSheetEntity[]  */
    protected $indexedGameModDataSheetsById = [];

    /**
     * @param GameModDataSheetEntity $gameModDataSheet
     */
    public function setGameModDataSheet(GameModDataSheetEntity $gameModDataSheet)
    {
        $this->dataArray[VField::GAME_MOD_DATA_SHEETS][] = $gameModDataSheet;
        $this->indexedGameModDataSheets[$gameModDataSheet->getName()] = $gameModDataSheet;
        $this->indexedGameModDataSheetsById[$gameModDataSheet->getPk()] = $gameModDataSheet;
    }

    /**
     * @return GameModDataSheetEntity[]
     */
    public function getGameModDataSheets()
    {
        return $this->getVField(VField::GAME_MOD_DATA_SHEETS);
    }

    /**
     * @return array
     */
    public function getGameModDataSheetNames()
    {
        return array_keys($this->indexedGameModDataSheets);
    }


    /**
     * @param $sheetName
     * @return array|GameModDataSheetEntity
     */
    public function getGameModDataSheetByName($sheetName)
    {
        return $this->indexedGameModDataSheets[$sheetName] ?? [];
    }

    /**
     * @param $gameModDataSheetId
     * @return array|GameModDataSheetEntity
     */
    public function getGameModDataSheetById($gameModDataSheetId)
    {
        return $this->indexedGameModDataSheetsById[$gameModDataSheetId] ?? [];
    }

    /**
     * @return array
     */
    public function getSheetDataArrays()
    {
        $data = [];

        foreach ($this->getGameModDataSheets() as $gameDataSheet) {
            if (!array_key_exists($gameDataSheet->getName(), $data))
                $data[$gameDataSheet->getName()] = [];

            $data[$gameDataSheet->getName()] = $gameDataSheet->getProcessedRows();
        }

        return $data;
    }

}

trait hasVirtualGameModDataSheetRowsField
{
    /** @var GameModDataSheetRowEntity[] */
    protected $indexedGameModDataSheetRows = [];
    protected $indexexGameModDataSheetRowsByOrder = [];

    /**
     * @param GameModDataSheetRowEntity $gameModDataSheetRow
     */
    public function setGameModDataSheetRow(GameModDataSheetRowEntity $gameModDataSheetRow)
    {
        $this->dataArray[VField::GAME_MOD_DATA_SHEET_ROWS][] = $gameModDataSheetRow;
        $this->indexedGameModDataSheetRows[$gameModDataSheetRow->getPk()] = $gameModDataSheetRow;
        $this->indexexGameModDataSheetRowsByOrder[$gameModDataSheetRow->getDisplayOrder()] = $gameModDataSheetRow;
    }

    /**
     * @return GameModDataSheetRowEntity[]
     */
    public function getGameModDataSheetRows()
    {
        return $this->getVField(VField::GAME_MOD_DATA_SHEET_ROWS);
    }

    /**
     * @param $gameModDataSheetRowId
     * @return array|GameModDataSheetRowEntity
     */
    public function getGameModDataSheetRowById($gameModDataSheetRowId)
    {
        return $this->indexedGameModDataSheetRows[$gameModDataSheetRowId] ?? [];
    }

    /**
     * @param $gameModDataSheetRowOrder
     * @return GameModDataSheetRowEntity|array
     */
    public function getGameModDataSheetRowByOrder($gameModDataSheetRowOrder)
    {
        return $this->indexexGameModDataSheetRowsByOrder[$gameModDataSheetRowOrder] ?? [];
    }

    /**
     * @return array
     */
    public function getProcessedRows()
    {
        $data = [];

        foreach ($this->getGameModDataSheetRows() as $gameModDataSheetRow) {
            $rawData = $gameModDataSheetRow->getProcessedValues();
            $rawData[VField::V_PK] = $gameModDataSheetRow->getPk();
            $rawData[VField::V_ENTITY_TYPE_ID] = EntityType::GAME_MOD_DATA_SHEET_ROW;

            $data[] = $rawData;
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getProcessedRowValues()
    {
        $data = [];
        foreach ($this->getGameModDataSheetRows() as $gameDataSheetRow) {
            $data[] = array_values($gameDataSheetRow->getProcessedValues());
        }
        return $data;
    }
}

trait hasVirtualGameDataSheetsField
{
    /** @var GameDataSheetEntity[] $indexedGameDataSheetsByName */
    protected $indexedGameDataSheetsByName = [];
    /** @var GameDataSheetEntity[] $indexedGameDataSheetsById */
    protected $indexedGameDataSheetsById = [];

    /**
     * @param GameDataSheetEntity $gameDataSheet
     */
    public function setGameDataSheet(GameDataSheetEntity $gameDataSheet)
    {
        $this->dataArray[VField::GAME_DATA_SHEETS][] = $gameDataSheet;
        $this->indexedGameDataSheetsByName[$gameDataSheet->getName()] = $gameDataSheet;
        $this->indexedGameDataSheetsById[$gameDataSheet->getPk()] = $gameDataSheet;
    }

    /**
     * @return GameDataSheetEntity[]
     */
    public function getGameDataSheets()
    {
        return $this->getVField(VField::GAME_DATA_SHEETS);
    }

    /**
     * @return array
     */
    public function getGameDataSheetNames()
    {
        return array_keys($this->indexedGameDataSheetsByName);
    }

    /**
     * @param $sheetName
     * @return array|GameDataSheetEntity
     */
    public function getGameDataSheetByName($sheetName)
    {
        return $this->indexedGameDataSheetsByName[$sheetName] ?? [];
    }

    /**
     * @param $sheetName
     * @return bool
     */
    public function hasSheet($sheetName)
    {
        return array_key_exists($sheetName, $this->indexedGameDataSheetsByName);
    }

    /**
     * @param $gameDataSheetId
     * @return array|GameDataSheetEntity
     */
    public function getGameDataSheetById($gameDataSheetId)
    {
        return $this->indexedGameDataSheetsById[$gameDataSheetId] ?? [];
    }

    /**
     * @return array
     */
    public function getSheetDataArrays()
    {
        $data = [];

        foreach ($this->getGameDataSheets() as $gameDataSheet) {
            if (!array_key_exists($gameDataSheet->getName(), $data))
                $data[$gameDataSheet->getName()] = [];

            $data[$gameDataSheet->getName()] = $gameDataSheet->getProcessedRows();
        }

        return $data;
    }
}

trait hasVirtualGameDataSheetColumnsField
{
    /** @var GameDataSheetColumnEntity[] $indexedGameDataSheetColumnsById */
    protected $indexedGameDataSheetColumnsById = [];
    /** @var GameDataSheetColumnEntity[] $indexedGameDataSheetColumnsByName */
    protected $indexedGameDataSheetColumnsByName = [];

    /**
     * @return GameDataSheetColumnEntity[]
     */
    public function getGameDataSheetColumns()
    {
        return $this->getVField(VField::GAME_DATA_SHEET_COLUMNS);
    }

    /**
     * @param GameDataSheetColumnEntity $gameDataSheetColumn
     */
    public function setGameDataSheetColumn(GameDataSheetColumnEntity $gameDataSheetColumn)
    {
        $this->dataArray[VField::GAME_DATA_SHEET_COLUMNS][] = $gameDataSheetColumn;
        $this->indexedGameDataSheetColumnsByName[$gameDataSheetColumn->getName()] = $gameDataSheetColumn;
        $this->indexedGameDataSheetColumnsById[$gameDataSheetColumn->getPk()] = $gameDataSheetColumn;
    }

    /**
     * @param $columnName
     * @return array|GameDataSheetColumnEntity
     */
    public function getGameDataSheetColumnByName($columnName)
    {
        return $this->indexedGameDataSheetColumnsByName[$columnName] ?? [];
    }

    /**
     * @param $columnId
     * @return array|GameDataSheetColumnEntity
     */
    public function getGameDataSheetColumnById($columnId)
    {
        return $this->indexedGameDataSheetColumnsById[$columnId] ?? [];
    }

    /**
     * @return array
     */
    public function getGameDataSheetColumnValues()
    {
        $data = [];
        foreach ($this->getGameDataSheetColumns() as $gameDataSheetColumn) {
            $data[] = $gameDataSheetColumn->getName();
        }

        return $data;
    }

    /**
     * @return array|GameDataSheetColumnEntity
     */
    public function getIndexColumn()
    {
        return $this->getGameDataSheetColumnById($this->getGameDataSheetColumnId());
    }
}

trait hasVirtualGameModDataSheetColumnsField
{
    /** @var GameModDataSheetColumnEntity[] $indexedGameModDataSheetColumnsById */
    protected $indexedGameModDataSheetColumnsById = [];
    /** @var GameModDataSheetColumnEntity[] $indexedGameModDataSheetColumnsByName */
    protected $indexedGameModDataSheetColumnsByName = [];

    /**
     * @return GameModDataSheetColumnEntity[]
     */
    public function getGameModDataSheetColumns()
    {
        return $this->getVField(VField::GAME_MOD_DATA_SHEET_COLUMNS);
    }

    /**
     * @param GameModDataSheetColumnEntity $gameModDataSheetColumn
     */
    public function setGameModDataSheetColumn(GameModDataSheetColumnEntity $gameModDataSheetColumn)
    {
        $this->dataArray[VField::GAME_MOD_DATA_SHEET_COLUMNS][] = $gameModDataSheetColumn;
        $this->indexedGameModDataSheetColumnsByName[$gameModDataSheetColumn->getName()] = $gameModDataSheetColumn;
        $this->indexedGameModDataSheetColumnsById[$gameModDataSheetColumn->getPk()] = $gameModDataSheetColumn;
    }

    /**
     * @param $columnName
     * @return array|GameModDataSheetColumnEntity
     */
    public function getGameModDataSheetColumnByName($columnName)
    {
        return $this->indexedGameModDataSheetColumnsByName[$columnName] ?? [];
    }

    /**
     * @param $columnId
     * @return array|GameModDataSheetColumnEntity
     */
    public function getGameModDataSheetColumnById($columnId)
    {
        return $this->indexedGameModDataSheetColumnsById[$columnId] ?? [];
    }

    /**
     * @return array
     */
    public function getGameModDataSheetColumnValues()
    {
        $data = [];
        foreach ($this->getGameModDataSheetColumns() as $gameDataSheetColumn) {
            $data[] = $gameDataSheetColumn->getName();
        }

        return $data;
    }

    /**
     * @return array|GameModDataSheetColumnEntity
     */
    public function getIndexColumn()
    {
        return $this->getGameModDataSheetColumnById($this->getGameModDataSheetColumnId());
    }

}

trait hasVirtualGameDataSheetRowsField
{
    /** @var GameDataSheetRowEntity[] */
    protected $indexedGameDataSheetRows = [];
    protected $indexedGameDataSheetRowsByOrder = [];

    /**
     * @param GameDataSheetRowEntity $gameDataSheetRow
     */
    public function setGameDataSheetRow(GameDataSheetRowEntity $gameDataSheetRow)
    {
        $this->dataArray[VField::GAME_DATA_SHEET_ROWS][] = $gameDataSheetRow;
        $this->indexedGameDataSheetRows[$gameDataSheetRow->getPk()] = $gameDataSheetRow;
        $this->indexedGameDataSheetRowsByOrder[$gameDataSheetRow->getDisplayOrder()] = $gameDataSheetRow;
    }

    /**
     * @return GameDataSheetRowEntity[]
     */
    public function getGameDataSheetRows()
    {
        return $this->getVField(VField::GAME_DATA_SHEET_ROWS);
    }


    /**
     * @param $gameDataSheetRowId
     * @return array|GameDataSheetRowEntity
     */
    public function getGameDataSheetRowById($gameDataSheetRowId)
    {
        return $this->indexedGameDataSheetRows[$gameDataSheetRowId] ?? [];
    }

    /**
     * @param $order
     * @return GameDataSheetRowEntity
     */
    public function getGameDataSheetRowByOrder($order)
    {
        return $this->indexedGameDataSheetRowsByOrder[$order] ?? [];
    }

    /**
     * @return array
     */
    public function getProcessedRows()
    {
        $data = [];

        foreach ($this->getGameDataSheetRows() as $gameDataSheetRow) {
            $rawData = $gameDataSheetRow->getProcessedValues();
            $rawData[VField::V_PK] = $gameDataSheetRow->getPk();
            $rawData[VField::V_ENTITY_TYPE_ID] = EntityType::GAME_DATA_SHEET_ROW;

            $data[] = $rawData;
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getProcessedRowValues()
    {
        $data = [];
        foreach ($this->getGameDataSheetRows() as $gameDataSheetRow) {
            $data[] = array_values($gameDataSheetRow->getProcessedValues());
        }
        return $data;
    }
}

trait hasVirtualGameSlugField
{
    /**
     * @return string
     */
    public function getGameSlug()
    {
        return $this->getVField(VField::GAME_SLUG);
    }
}

trait hasVirtualOrganizationSlugField
{
    /**
     * @return string
     */
    public function getOrganizationSlug()
    {
        return $this->getVField(VField::ORGANIZATION_SLUG);
    }
}

trait hasVirtualGameModBuildsField
{
    /** @return GameModBuildEntity|array */
    public function getGameModBuildByUpdateChannel($updateChannel)
    {
        return $this->dataArray[VField::GAME_MOD_BUILDS][$updateChannel] ?? [];
    }

    /**
     * @return GameModBuildEntity[]
     */
    public function getGameModBuilds()
    {
        return $this->getVField(VField::GAME_MOD_BUILDS);
    }

    /**
     * @param GameModBuildEntity $gameModBuild
     */
    public function setGameModBuild(GameModBuildEntity $gameModBuild)
    {
        $this->dataArray[VField::GAME_MOD_BUILDS][$gameModBuild->getUpdateChannel()] = $gameModBuild;
    }
}

trait hasVirtualGameControllersField
{
    /**
     * @param GameControllerEntity $gameController
     */
    public function setGameController(GameControllerEntity $gameController)
    {
        $this->dataArray[VField::GAME_CONTROLLERS][] = $gameController;
    }

    /**
     * @return GameControllerEntity[]
     */
    public function getGameControllers()
    {
        return $this->getVField(VField::GAME_CONTROLLERS);
    }

    /**
     * @return GameControllerEntity|array
     */
    public function getPlayerController()
    {
        foreach ($this->getGameControllers() as $gameController) {
            if ($gameController->getGameControllerTypeId() == GamesControllersTypesManager::ID_PLAYER)
                return $gameController;
        }
        return [];
    }

    /**
     * @return array|GameControllerEntity
     */
    public function getAdminController()
    {
        foreach ($this->getGameControllers() as $gameController) {
            if ($gameController->getGameControllerTypeId() == GamesControllersTypesManager::ID_GAME_ADMIN)
                return $gameController;
        }

        return [];
    }

    /**
     * @return array|GameControllerEntity
     */
    public function getJoinController()
    {
        foreach ($this->getGameControllers() as $gameController) {
            if ($gameController->getGameControllerTypeId() == GamesControllersTypesManager::ID_JOIN)
                return $gameController;
        }

        return [];
    }

    /**
     * @return array|GameControllerEntity
     */
    public function getSpectatorController()
    {
        foreach ($this->getGameControllers() as $gameController) {
            if ($gameController->getGameControllerTypeId() == GamesControllersTypesManager::ID_SPECTATOR)
                return $gameController;
        }

        return [];
    }

    /**
     * @return array|GameControllerEntity
     */
    public function getCustomController()
    {
        foreach ($this->getGameControllers() as $gameController) {
            if ($gameController->getGameControllerTypeId() == GamesControllersTypesManager::ID_CUSTOM)
                return $gameController;
        }

        return [];
    }
}

trait hasVirtualUserField
{
    /**
     * @return UserEntity
     */
    public function getUser()
    {
        return $this->getVField(VField::USER);
    }

    /**
     * @param UserEntity $user
     */
    public function setUser(UserEntity $user)
    {
        $this->updateField(VField::USER, $user);
    }
}

trait hasVirtualCountryField
{
    /**
     * @return CountryEntity
     */
    public function getCountry()
    {
        return $this->getVField(VField::COUNTRY);
    }

    /**
     * @param CountryEntity $country
     */
    public function setCountry(CountryEntity $country)
    {
        $this->updateField(VField::COUNTRY, $country);
    }
}

trait hasVirtualHostDeviceField
{
    /**
     * @param HostDeviceEntity $hostDevice
     */
    public function setHostDevice(HostDeviceEntity $hostDevice)
    {
        $this->dataArray[VField::HOST_DEVICE] = $hostDevice;
    }

    /**
     * @return HostDeviceEntity
     */
    public function getHostDevice()
    {
        return $this->field(VField::HOST_DEVICE);
    }
}

trait hasVirtualNetworkField
{
    /**
     * @return NetworkEntity
     */
    public function getNetwork()
    {
        return $this->getVField(VField::NETWORK);
    }

    /**
     * @param NetworkEntity $network
     */
    public function setNetwork(NetworkEntity $network)
    {
        $this->updateField(VField::NETWORK, $network);
    }
}

trait hasVersionField
{
    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->field(DBField::VERSION);
    }
}

trait hasVirtualSdkVersionField
{
    /**
     * @return SdkVersionEntity
     */
    public function getSdkVersion()
    {
        return $this->getVField(VField::SDK_VERSION);
    }

    /**
     * @param SdkVersionEntity $sdkVersion
     */
    public function setSdkVersion(SdkVersionEntity $sdkVersion)
    {
        $this->updateField(VField::SDK_VERSION, $sdkVersion);
    }
}

trait hasSdkBuildIdField
{
    /**
     * @return int
     */
    public function getSdkBuildId()
    {
        return $this->field(DBField::SDK_BUILD_ID);
    }
}
trait hasSdkAssetIdField
{
    /**
     * @return int
     */
    public function getHostAssetId()
    {
        return $this->field(DBField::HOST_ASSET_ID);
    }
}

trait hasSdkVersionIdField
{
    /**
     * @return int
     */
    public function getSdkVersionId()
    {
        return $this->field(DBField::SDK_VERSION_ID);
    }
}

trait hasMinSdkVersionIdField
{
    /**
     * @return int
     */
    public function getMinSdkVersionId()
    {
        return $this->field(DBField::MIN_SDK_VERSION_ID);
    }
}

trait hasSdkUpdateChannelField
{
    /**
     * @return string
     */
    public function getSdkUpdateChannel()
    {
        return $this->field(DBField::SDK_UPDATE_CHANNEL);
    }
}

trait hasVirtualSdkBuildAssetIdField
{
    /**
     * @return int
     */
    public function getSdkBuildAssetId()
    {
        return $this->getVField(VField::SDK_BUILD_ASSET_ID);
    }
}

trait hasHostAssetIdField
{
    /**
     * @return int
     */
    public function getHostAssetId()
    {
        return $this->field(DBField::HOST_ASSET_ID);
    }
}
trait hasHostVersionIdField
{
    /**
     * @return int
     */
    public function getHostVersionId()
    {
        return $this->field(DBField::HOST_VERSION_ID);
    }
}

trait hasMinHostVersionIdField
{
    /**
     * @return int
     */
    public function getMinHostVersionId()
    {
        return $this->field(DBField::MIN_HOST_VERSION_ID);
    }
}

trait hasHostUpdateChannelField
{
    /**
     * @return string
     */
    public function getHostUpdateChannel()
    {
        return $this->field(DBField::HOST_UPDATE_CHANNEL);
    }
}

trait hasUpdateChannelField
{
    /**
     * @return string
     */
    public function getUpdateChannel()
    {
        return $this->field(DBField::UPDATE_CHANNEL);
    }
}

trait hasIsDeprecatedField
{
    /**
     * @return int
     */
    public function getIsDeprecated()
    {
        return $this->field(DBField::IS_DEPRECATED);
    }

    /**
     * @return bool
     */
    public function is_deprecated()
    {
        return $this->getIsDeprecated() ? true : false;
    }
}




/**
 * Orders Payments
 */



/*
 *
 * Orders
 *
 */

trait hasOrderStatusIdField {

    /**
     * @return int
     */
    public function getOrderStatusId()
    {
        return $this->field(DBField::ORDER_STATUS_ID);
    }
}

trait hasOrderItemStatusIdField {

    /**
     * @return int
     */
    public function getOrderItemStatusId()
    {
        return $this->field(DBField::ORDER_ITEM_STATUS_ID);
    }
}

trait hasOrderItemIdField {

    /**
     * @return int
     */
    public function getOrderItemId()
    {
        return $this->field(DBField::ORDER_ITEM_ID);
    }
}

trait hasOrderItemTypeIdField {

    /**
     * @return int
     */
    public function getOrderItemTypeId()
    {
        return $this->field(DBField::ORDER_ITEM_TYPE_ID);
    }
}

trait hasOrderItemQuantumIdField {

    /**
     * @return int
     */
    public function getOrderItemQuantumId()
    {
        return $this->field(DBField::ORDER_ITEM_QUANTUM_ID);
    }
}

trait hasOrderIdField {

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->field(DBField::ORDER_ID);
    }
}

trait hasNoteField {

    /**
     * @return string
     */
    public function getNote()
    {
        return $this->field(DBField::NOTE);
    }
}


trait hasMarkupOwnerTypeIdField {

    /**
     * @return int
     */
    public function getMarkupOwnerTypeId()
    {
        return $this->field(DBField::MARKUP_OWNER_TYPE_ID);
    }
}

trait hasMarkupOwnerIdField {

    /**
     * @return int
     */
    public function getMarkupOwnerId()
    {
        return $this->field(DBField::MARKUP_OWNER_ID);
    }

}

trait hasNetPriceField {

    /**
     * @return float
     */
    public function getNetPrice()
    {
        return (float)$this->field(DBField::NET_PRICE);
    }
}

trait hasDiscountAmountField {

    /**
     * @return float
     */
    public function getDiscountAmount()
    {
        return $this->field(DBField::DISCOUNT_AMOUNT);
    }
}

trait hasIncentiveIdField
{
    /**
     * @return int
     */
    public function getIncentiveId()
    {
        return $this->field(DBField::INCENTIVE_ID);
    }
}

trait hasIncentiveTypeIdField
{
    /**
     * @return int
     */
    public function getIncentiveTypeId()
    {
        return $this->field(DBField::INCENTIVE_TYPE_ID);
    }
}

trait hasVirtualIncentiveField
{
    /**
     * @return IncentiveEntity
     */
    public function getIncentive()
    {
        return $this->getVField(VField::INCENTIVE);
    }

    /**
     * @param IncentiveEntity $incentive
     */
    public function setIncentive(IncentiveEntity $incentive)
    {
        $this->dataArray[VField::INCENTIVE] = $incentive;
    }
}

trait hasMaxAmountField
{
    /**
     * @return float|null
     */
    public function getMaxAmount()
    {
        return $this->field(DBField::MAX_AMOUNT);
    }
}

trait hasAmountField
{
    /**
     * @return float|null
     */
    public function getAmount()
    {
        return $this->field(DBField::AMOUNT);
    }
}

trait hasDiscountPercentageField {

    /**
     * @return float|null
     */
    public function getDiscountPercentage()
    {
        return (float)$this->field(DBField::DISCOUNT_PERCENTAGE);
    }
}


trait hasNetMarkupField {

    /**
     * @return float
     */
    public function getNetMarkup()
    {
        return (float)$this->field(DBField::NET_MARKUP);
    }

    /**
     * @return bool
     */
    public function has_markup()
    {
        return (float)$this->getNetMarkup() > 0.0000;
    }
}

trait hasVirtualDiscountField
{
    /**
     * @return float|int|null
     */
    public function getDiscount()
    {
        return $this->field(VField::DISCOUNT);
    }
}

trait hasTaxRateField {

    /**
     * @return float
     */
    public function getTaxRate()
    {
        return (float)$this->field(DBField::TAX_RATE);
    }

    public function has_tax()
    {
        return $this->getTaxRate() > 0.0000;
    }
}

trait hasQuantityField {

    /**
     * @return int
     */
    public function getQuantity()
    {
        return $this->field(DBField::QUANTITY);
    }
}


/*
 *
 * Payments
 *
 */

trait hasPaymentIdField {

    /**
     * @return int
     */
    public function getPaymentId()
    {
        return $this->field(DBField::PAYMENT_ID);
    }
}

trait hasPaymentStatusIdField {

    /**
     * @return int
     */
    public function getPaymentStatusId()
    {
        return $this->field(DBField::PAYMENT_STATUS_ID);
    }
}

trait hasPaymentServiceIdField {

    /**
     * @return int
     */
    public function getPaymentServiceId()
    {
        return $this->field(DBField::PAYMENT_SERVICE_ID);
    }
}

trait hasPaymentInvoiceIdField {

    /**
     * @return int
     */
    public function getPaymentInvoiceId()
    {
        return $this->field(DBField::PAYMENT_INVOICE_ID);
    }
}

trait hasInvoiceTransactionTypeIdField {

    /**
     * @return int
     */
    public function getInvoiceTransactionTypeId()
    {
        return $this->field(DBField::INVOICE_TRANSACTION_TYPE_ID);
    }
}

trait hasPaymentFeeInvoiceIdField {

    /**
     * @return int
     */
    public function getPaymentFeeInvoiceId()
    {
        return $this->field(DBField::PAYMENT_FEE_INVOICE_ID);
    }
}

trait hasPaymentMessageField {

    /**
     * @return int
     */
    public function getPaymentMessage()
    {
        return $this->field(DBField::PAYMENT_MESSAGE);
    }
}

trait hasPaymentDateField {

    /**
     * @return int
     */
    public function getPaymentDate()
    {
        return $this->field(DBField::PAYMENT_DATE);
    }
}

trait hasPaymentAmountField {

    /**
     * @return int
     */
    public function getPaymentAmount()
    {
        return $this->field(DBField::PAYMENT_AMOUNT);
    }
}

trait hasPaymentServiceCustomerKeyField {

    /**
     * @return int
     */
    public function getPaymentServiceCustomerKey()
    {
        return $this->field(DBField::PAYMENT_SERVICE_CUSTOMER_KEY);
    }
}

trait hasTransactionFeeField {

    /**
     * @return float
     */
    public function getTransactionFee()
    {
        return $this->field(DBField::TRANSACTION_FEE);
    }
}

trait hasTransactionIdField {

    /**
     * @return string
     */
    public function getTransactionId()
    {
        return $this->field(DBField::TRANSACTION_ID);
    }
}

trait hasTransactionNumberField {

    /**
     * @return int
     */
    public function getTransactionNumber()
    {
        return $this->field(DBField::TRANSACTION_NUMBER);
    }
}


trait hasAuthorizationIdField {

    /**
     * @return string
     */
    public function getAuthorizationId()
    {
        return $this->field(DBField::AUTHORIZATION_ID);
    }
}

trait hasResponseField {

    /**
     * @return string
     */
    public function getResponse()
    {
        return $this->field(DBField::RESPONSE);
    }
}

trait hasErrorField {

    /**
     * @return string
     */
    public function getError()
    {
        return $this->field(DBField::ERROR);
    }
}

trait hasDebitCreditField {

    /**
     * @return string
     */
    public function getDebitCredit()
    {
        return $this->field(DBField::DEBIT_CREDIT);
    }
}

trait hasLineTypeField {

    /**
     * @return string
     */
    public function getLineType()
    {
        return $this->field(DBField::LINE_TYPE);
    }
}

trait hasNetAmountField {

    /**
     * @return float
     */
    public function getNetAmount()
    {
        return $this->field(DBField::NET_AMOUNT);
    }
}

trait hasCodeField {

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->field(DBField::CODE);
    }
}

trait hasDim1Field {

    /**
     * @return string
     */
    public function getDim1()
    {
        return $this->field(DBField::DIM_1);
    }
}

trait hasDim2Field {

    /**
     * @return string
     */
    public function getDim2()
    {
        return $this->field(DBField::DIM_2);
    }
}

trait hasInvoiceNoField {

    /**
     * @return string
     */
    public function getInvoiceNo()
    {
        return $this->field(DBField::INVOICE_NO);
    }
}

trait hasAccountingStatusIdField {

    /**
     * @return int
     */
    public function getAccountingStatusId()
    {
        return $this->field(DBField::ACCOUNTING_STATUS_ID);
    }
}

trait hasIsSuccessfulField {

    /**
     * @return int
     */
    public function getIsSuccessful()
    {
        return $this->field(DBField::IS_SUCCESSFUL);
    }
}

trait hasDirectionField {

    /**
     * @return string
     */
    public function getDirection()
    {
        return $this->field(DBField::DIRECTION);
    }
}


trait hasOwnerPaymentServiceIdField {

    /**
     * @return int
     */
    public function getOwnerPaymentServiceId()
    {
        return $this->field(DBField::OWNER_PAYMENT_SERVICE_ID);
    }
}

trait hasOwnerPaymentServiceTokenIdField {

    /**
     * @return int
     */
    public function getOwnerPaymentServiceTokenId()
    {
        return $this->field(DBField::OWNER_PAYMENT_SERVICE_TOKEN_ID);
    }
}


trait hasFingerprintField {

    /**
     * @return mixed
     */
    public function getFingerprint()
    {
        return $this->field(DBField::FINGERPRINT);
    }
}

trait hasClientSecretField {

    /**
     * @return mixed
     */
    public function getClientSecret()
    {
        return $this->field(DBField::CLIENT_SECRET);
    }
}

trait hasRawMetaField {

    /**
     * @return string
     */
    public function getRawMeta()
    {
        return $this->field(DBField::RAW_META);
    }
}

trait hasPaymentServiceVirtualField {

    /**
     * @param PaymentServiceEntity $paymentService
     * @return $this
     */
    public function setPaymentService(PaymentServiceEntity $paymentService)
    {
        return $this->updateField(VField::PAYMENT_SERVICE, $paymentService);
    }

    /**
     * @return PaymentServiceEntity
     */
    public function getPaymentService()
    {
        return $this->field(VField::PAYMENT_SERVICE);
    }
}

trait hasOwnerPaymentServiceTokenVirtualField {

    /**
     * @param OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken
     * @return $this
     */
    public function setOwnerPaymentServiceToken(OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken)
    {
        return $this->updateField(VField::OWNER_PAYMENT_SERVICE_TOKEN, $ownerPaymentServiceToken);
    }

    /**
     * @return OwnerPaymentServiceTokenEntity
     */
    public function getUserPaymentServiceToken()
    {
        return $this->field(VField::OWNER_PAYMENT_SERVICE_TOKEN);
    }

}

trait hasVirtualAdminCaptureUrlField
{
    /**
     * @return string
     */
    public function getAdminCaptureUrl()
    {
        return $this->getVField(VField::ADMIN_CAPTURE_URL);
    }
}

trait hasMetaVirtualField {

    /**
     * @param string $metaField
     * @return mixed
     */
    public function getMeta($metaField = '')
    {
        if ($metaField)
            return isset($this->dataArray[VField::META][$metaField]) ? $this->dataArray[VField::META][$metaField] : null;
        else
            return $this->dataArray[VField::META];
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function setMetaKeyValue($key, $value)
    {
        if (!$meta = json_decode($this->getRawMeta(), true)) {
            $meta = [];
        }

        $meta[$key] = $value;

        $this->dataArray[VField::META] = $meta;
        $this->dataArray[DBField::RAW_META] = json_encode($meta);

        return $this;
    }

}


/*
 *
 * Payouts
 *
 */


trait hasPayoutIdField {

    /**
     * @return int
     */
    public function getPayoutId()
    {
        return $this->field(DBField::PAYOUT_ID);
    }
}

trait hasPayoutStatusIdField {

    /**
     * @return int
     */
    public function getPayoutStatusId()
    {
        return $this->field(DBField::PAYOUT_STATUS_ID);
    }
}

trait hasPaypalEmailField {

    /**
     * @return string
     */
    public function getPaypalEmail()
    {
        return $this->field(DBField::PAYPAL_EMAIL);
    }
}

trait hasPayoutServiceIdField {

    /**
     * @return int
     */
    public function getPayoutServiceId()
    {
        return $this->field(DBField::PAYOUT_SERVICE_ID);
    }
}

trait hasMinimumPayoutAmountField {

    /**
     * @return int
     */
    public function getMinimumPayoutAmount()
    {
        return $this->field(DBField::MINIMUM_PAYOUT_AMOUNT);
    }
}

trait hasPayoutInvoiceIdField {

    /**
     * @return int
     */
    public function getPayoutInvoiceId()
    {
        return $this->field(DBField::PAYOUT_INVOICE_ID);
    }
}

trait hasPayoutFeeInvoiceIdField {

    /**
     * @return int
     */
    public function getPayoutFeeInvoiceId()
    {
        return $this->field(DBField::PAYOUT_FEE_INVOICE_ID);
    }
}

trait hasPayoutMessageField {

    /**
     * @return int
     */
    public function getPayoutMessage()
    {
        return $this->field(DBField::PAYOUT_MESSAGE);
    }
}

trait hasPayoutDateField {

    /**
     * @return int
     */
    public function getPayoutDate($format = null)
    {
        $payoutDate = $this->field(DBField::PAYOUT_DATE);

        if ($format) {
            $df = new DateTime($payoutDate);
            return $df->format($format);
        } else {
            return $payoutDate;
        }
    }
}

trait hasPayoutAmountField {

    /**
     * @return int
     */
    public function getPayoutAmount()
    {
        return $this->field(DBField::PAYOUT_AMOUNT);
    }
}


trait hasPayoutServiceVirtualField {

    /**
     * @param PayoutServiceEntity $payoutService
     */
    public function setPayoutService(PayoutServiceEntity $payoutService)
    {
        $this->updateField(VField::PAYOUT_SERVICE, $payoutService);
    }

    /**
     * @return PayoutServiceEntity
     */
    public function getPayoutService()
    {
        return $this->field(VField::PAYOUT_SERVICE);
    }

}



/*
 * Images
 */

trait hasWidthField
{
    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->field(DBField::WIDTH);
    }
}

trait hasHeightField
{
    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->field(DBField::HEIGHT);
    }
}

trait hasQualityField
{
    /**
     * @return float
     */
    public function getQuality()
    {
        return $this->field(DBField::QUALITY);
    }
}

trait hasAspectXField
{
    /**
     * @return float
     */
    public function getAspectX()
    {
        return $this->field(DBField::ASPECT_X);
    }
}

trait hasDpiXField
{
    /**
     * @return int
     */
    public function getDpiX()
    {
        return $this->field(DBField::DPI_X);
    }
}

trait hasDpiYField
{
    /**
     * @return int
     */
    public function getDpiY()
    {
        return $this->field(DBField::DPI_Y);
    }
}

trait hasImageAssetIdField
{
    /**
     * @return int
     */
    public function getImageAssetId()
    {
        return $this->field(DBField::IMAGE_ASSET_ID);
    }
}

trait hasImageTypeIdField
{
    /**
     * @return int
     */
    public function getImageTypeId()
    {
        return $this->field(DBField::IMAGE_TYPE_ID);
    }
}



/*
 *
 * Income
 *
 */

trait hasIncomeIdField {

    /**
     * @return int
     */
    public function getIncomeId()
    {
        return $this->field(DBField::INCOME_ID);
    }
}

trait hasIncomeTypeIdField {

    /**
     * @return int
     */
    public function getIncomeTypeId()
    {
        return $this->field(DBField::INCOME_TYPE_ID);
    }
}

trait hasIncomeStatusIdField {

    /**
     * @return int
     */
    public function getIncomeStatusId()
    {
        return $this->field(DBField::INCOME_STATUS_ID);
    }
}

trait hasPayoutServiceTokenIdField {

    /**
     * @return int
     */
    public function getPayoutServiceTokenId()
    {
        return $this->field(DBField::PAYOUT_SERVICE_TOKEN_ID);
    }
}

trait hasHostDeviceIdField
{
    /**
     * @return int
     */
    public function getHostDeviceId()
    {
        return $this->field(DBField::HOST_DEVICE_ID);
    }
}

/*
 * Host Instance Invitations
 */

trait hasHostInstanceDeviceIdField
{
    /**
     * @return int
     */
    public function getHostInstanceDeviceId()
    {
        return $this->field(DBField::HOST_INSTANCE_DEVICE_ID);
    }
}

trait hasHostInstanceInviteTypeIdField
{
    /**
     * @return int
     */
    public function getHostInstanceInviteTypeId()
    {
        return $this->field(DBField::HOST_INSTANCE_INVITE_TYPE_ID);
    }
}

trait hasHostInstanceTypeIdField
{
    /**
     * @return int
     */
    public function getHostInstanceTypeId()
    {
        return $this->field(DBField::HOST_INSTANCE_TYPE_ID);
    }
}

trait hasInviteHashField
{
    /**
     * @return string
     */
    public function getInviteHash()
    {
        return $this->field(DBField::INVITE_HASH);
    }
}

trait hasSmsIdField
{
    /**
     * @return int
     */
    public function getSmsId()
    {
        return $this->field(DBField::SMS_ID);
    }
}

trait hasInviteRecipientField
{
    /**
     * @return string
     */
    public function getInviteRecipient()
    {
        return $this->field(DBField::INVITE_RECIPIENT);
    }
}

// Payment Options


trait hasServiceAccessTokenIdField
{
    /**
     * @return int
     */
    public function getServiceAccessTokenId()
    {
        return $this->field(DBField::SERVICE_ACCESS_TOKEN_ID);
    }
}

trait hasServiceAccessTokenInstanceIdField
{
    /**
     * @return int
     */
    public function getServiceAccessTokenInstanceId()
    {
        return $this->field(DBField::SERVICE_ACCESS_TOKEN_INSTANCE_ID);
    }
}

trait hasServiceAccessTokenTypeIdField
{
    /**
     * @return int
     */
    public function getServiceAccessTokenTypeId()
    {
        return $this->field(DBField::SERVICE_ACCESS_TOKEN_TYPE_ID);
    }
}

trait hasServiceAccessTokenTypeCategoryIdField
{
    /**
     * @return int
     */
    public function getServiceAccessTokenTypeCategoryId()
    {
        return $this->field(DBField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY_ID);
    }
}


trait hasServiceAccessTokenTypeGroupIdField
{
    /**
     * @return int
     */
    public function getServiceAccessTokenTypeGroupId()
    {
        return $this->field(DBField::SERVICE_ACCESS_TOKEN_TYPE_GROUP_ID);
    }
}

trait hasVirtualServiceAccessTokenTypeCategoryField
{
    /**
     * @param ServiceAccessTokenTypeCategoryEntity $serviceAccessTokenTypeCategory
     */
    public function setServiceAccessTokenTypeCategory(ServiceAccessTokenTypeCategoryEntity $serviceAccessTokenTypeCategory)
    {
        $this->updateField(VField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY, $serviceAccessTokenTypeCategory);
    }

    /**
     * @return ServiceAccessTokenTypeEntity|array
     */
    public function getServiceAccessTokenTypeCategory()
    {
        return $this->getVField(VField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY);
    }
}

trait hasMaxSeatsField
{
    /**
     * @return int
     */
    public function getMaxSeats()
    {
        return $this->field(DBField::MAX_SEATS);
    }

}

trait hasIsBuyableField
{
    /**
     * @return int
     */
    public function getIsBuyable()
    {
        return $this->field(DBField::IS_BUYABLE);
    }

    /**
     * @return bool
     */
    public function is_buyable()
    {
        return $this->getIsBuyable() ? true : false;
    }
}
trait hasIsOrganizationCreatableField
{

    /**
     * @return int
     */
    public function getIsOrganizationCreatable()
    {
        return $this->field(DBField::IS_ORGANIZATION_CREATABLE);
    }

    /**
     * @return bool
     */
    public function is_organization_creatable()
    {
        return $this->getIsOrganizationCreatable() ? true : false;
    }
}


trait hasDurationField
{
    /**
     * @return string
     */
    public function getDuration()
    {
        return $this->field(DBField::DURATION);
    }
}

trait hasOriginalUsesField
{
    /**
     * @return int
     */
    public function getOriginalUses()
    {
        return $this->field(DBField::ORIGINAL_USES);
    }
}

trait hasRemainingUsesField
{
    /**
     * @return int
     */
    public function getRemainingUses()
    {
        return $this->field(DBField::REMAINING_USES);
    }
}


/*
 * Stats
 */


trait hasSummaryFieldField
{
    public function getSummaryField()
    {
        return $this->field(DBField::SUMMARY_FIELD);
    }
}

trait hasDisplayInDashboardField
{
    /**
     * @return int
     */
    public function getDisplayInDashboard()
    {
        return $this->field(DBField::DISPLAY_IN_DASHBOARD);
    }

    /**
     * @return bool
     */
    public function display_in_dashboard()
    {
        return $this->getDisplayInDashboard() ? true : false;
    }
}