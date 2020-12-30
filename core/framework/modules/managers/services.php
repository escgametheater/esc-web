<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 12/26/15
 * Time: 12:46 AM
 */


abstract class ServiceLocator {}



abstract class BaseDBQueryFiltersLocator {

    /**
     * @var BaseEntityManager
     */
    protected $manager;

    /** @var Q $q */
    public $q;

    public function __construct(BaseEntityManager $manager)
    {
        $this->manager = $manager;
        $this->q = new Q();
    }

    /**
     * @param BaseEntityManager $foreign_manager
     * @param ...$on_filters
     * @return AndFilter|EqFilter
     * @throws BaseManagerEntityException
     */
    public function join(BaseEntityManager $foreign_manager, ...$on_filters)
    {
        $localField = $this->manager->getJoinForeignManagerField($foreign_manager);
        $foreignManagerFilter = $foreign_manager->filters->byPk($localField);

        if (count($on_filters) >= 1) {
            if (is_bool($on_filters[0]) && $active = array_shift($on_filters)) {
                return $this->And_($foreignManagerFilter, $foreign_manager->filters->isActive(), ...$on_filters);
            } else {
                return $this->And_($foreignManagerFilter, ...$on_filters);
            }

        } else {
            return $foreignManagerFilter;
        }
    }

    /**
     * @param BaseEntityManager $foreign_manager
     * @param ...$on_filters
     * @return AndFilter
     */
    public function joinWithoutPk(BaseEntityManager $foreign_manager, ...$on_filters)
    {
        if (count($on_filters) >= 1) {
            if (is_bool($on_filters[0]) && $active = array_shift($on_filters)) {
                return $this->And_($foreign_manager->filters->isActive(), ...$on_filters);
            }
        }
        return $this->And_(...$on_filters);
    }

    /**
     * @param $field
     * @return AliasedDBField|DBField
     */
    public function instField($field, $field_alias = null)
    {
        if (!$field instanceof DBField)
            $field = $this->manager->field($field, $field_alias);

        return $field;
    }

    /**
     * @param DBField|string $field
     * @param string|array $value
     * @return EqFilter|InFilter
     */
    public function Eq($field, $value = null)
    {
        if ($value === null)
            return null;

        return is_array($value)
            ? $this->q->In($this->instField($field), $value)
            : $this->q->Eq($this->instField($field), $value);
    }

    /**
     * @param $field
     * @param null $value
     * @return BitAndFilter|null
     */
    public function BitAnd($field, $bitValue, $otherValue = null)
    {
        if (!$otherValue)
            $otherValue = $bitValue;

        return $this->q->BitAnd($field, $bitValue, $otherValue);
    }

    /**
     * @param $condition
     * @return NotFilter
     */
    public function Not($condition)
    {
        return $this->q->Not($condition);
    }

    /**
     * @param $field
     * @param null $value
     * @return NotEqFilter|NotInFilter|null
     */
    public function NotEq($field, $value = null)
    {
        if ($value === null)
            return null;

        return is_array($value)
            ? $this->q->NotIn($this->instField($field), $value)
            : $this->q->NotEq($this->instField($field), $value);
    }

    /**
     * @param $field
     * @return NotEqFilter
     */
    public function IsNotNull($field)
    {
        return $this->q->NotEq($this->instField($field), null);
    }

    /**
     * @return FalseFilter
     */
    public function False()
    {
        return new FalseFilter();
    }

    /**
     * @param $field
     * @param null $value
     * @return GtFilter|null
     */
    public function Gt($field, $value = null)
    {
        if ($value === null)
            return null;

        return $this->q->Gt($this->instField($field), $value);
    }

    /**
     * @param $field
     * @param null $value
     * @return GteFilter|null
     */
    public function Gte($field, $value = null)
    {
        if ($value === null)
            return null;

        return $this->q->Gte($this->instField($field), $value);
    }

    /**
     * @param $field
     * @param null $value
     * @return LteFilter|null
     */
    public function Lte($field, $value = null)
    {
        if ($value === null)
            return null;

        return $this->q->Lte($this->instField($field), $value);
    }

    /**
     * @param $field
     * @param null $value
     * @return LtFilter|null
     */
    public function Lt($field, $value = null)
    {
        if ($value === null)
            return null;

        return $this->q->Lt($this->instField($field), $value);
    }


    /**
     * @param DBField|string $field
     * @param $value
     * @return RegexpFilter
     */
    public function Regex($field, $value = null)
    {
        if ($value === null)
            return null;

        return $this->q->RegExp($this->instField($field), $value);
    }

    /**
     * @param DBField|string $field
     * @param $value
     * @return LikeFilter
     */
    public function Like($field, $value = null)
    {
        if ($value === null)
            return null;

        return $this->q->Like($this->instField($field), $value);
    }

    /**
     * @param $field
     * @param null $value
     * @return null|StartsWithFilter
     */
    public function StartsWith($field, $value = null)
    {
        if ($value === null)
            return null;

        return $this->q->StartsWith($this->instField($field), $value);
    }

    /**
     * @param $field
     * @param $value
     * @return null|EndsWithFilter
     */
    public function EndsWith($field, $value)
    {
        if (!$value)
            return null;

        return $this->q->EndsWith($field, $value);
    }

    /**
     * @param DBField|string $field
     * @return EqFilter
     */
    public function IsNull($field)
    {
        return $this->q->IsNull($this->instField($field));
    }

    /**
     * @param ...$args
     * @return AndFilter
     */
    public function And_(...$args)
    {
        return $this->q->And_(...$args);
    }

    /**
     * @param ...$args
     * @return OrFilter
     */
    public function Or_(...$args)
    {
        return $this->q->Or_(...$args);
    }

    /**
     * @param $id
     * @return EqFilter|InFilter
     */
    public function byPk($id)
    {
        return $this->Eq($this->manager->getPkField(), $id);
    }
}

class DBQueryFilters extends BaseDBQueryFiltersLocator {

    /**
     * @param $slug
     * @return EqFilter
     */
    public function bySlug($slug)
    {
        return $this->Eq($this->manager->getSlugField(), $this->manager->db->convertSlug($slug));
    }

    /**
     * @param $name
     * @return EqFilter|InFilter
     */
    public function byName($name)
    {
        return $this->Eq(DBField::NAME, $name);
    }

    /**
     * @param $token
     * @return EqFilter|InFilter
     */
    public function byToken($token)
    {
        return $this->Eq(DBField::TOKEN, $token);
    }

    /**
     * @param $body
     * @return EqFilter|InFilter
     */
    public function byBody($body)
    {
        return $this->Eq(DBField::BODY, $body);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isDeleted()
    {
        return $this->Eq(DBField::IS_DELETED, 1);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isDownloadable()
    {
        return $this->Eq(DBField::IS_DOWNLOADABLE, 1);
    }

    /**
     * @return EqFilter
     */
    public function isNotDeleted()
    {
        return $this->Eq(DBField::IS_DELETED, 0);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isPrimary()
    {
        return $this->Eq(DBField::IS_PRIMARY, 1);
    }


    /**
     * @return EqFilter
     */
    public function isPublic()
    {
        return $this->Eq(DBField::IS_PUBLIC, 1);
    }

    /**
     * @param $email
     * @return EqFilter
     */
    public function byEmail($email)
    {
        return $this->Eq(DBField::EMAIL, strtolower($email));
    }


    /**
     * @param $exitStatus
     * @return EqFilter|InFilter
     */
    public function byNullExitStatus($exitStatus)
    {
        return $this->Eq(DBField::EXIT_STATUS, $exitStatus);
    }

    /**
     * @param $sessionHash
     * @return EqFilter|InFilter
     */
    public function bySessionHash($sessionHash)
    {
        return $this->Eq(DBField::SESSION_HASH, $sessionHash);
    }

    /**
     * @param $sessionId
     * @return EqFilter|InFilter
     */
    public function bySessionId($sessionId)
    {
        return $this->Eq(DBField::SESSION_ID, $sessionId);
    }


    /**
     * @param $emailAddress
     * @return EqFilter|InFilter
     */
    public function byEmailAddress($emailAddress)
    {
        return $this->Eq(DBField::EMAIL_ADDRESS, strtolower($emailAddress));
    }

    /**
     * @param $userName
     * @return EqFilter|InFilter
     */
    public function byUserName($userName)
    {
        return $this->Eq(DBField::USERNAME, $userName);
    }

    /**
     * @param $phoneNumber
     * @return EqFilter|InFilter
     */
    public function byPhoneNumber($phoneNumber)
    {
        return $this->Eq(DBField::PHONE_NUMBER, $phoneNumber);
    }

    /**
     * @param $emailTypeId
     * @return EqFilter|InFilter
     */
    public function byEmailTypeId($emailTypeId)
    {
        return $this->Eq(DBField::EMAIL_TYPE_ID, $emailTypeId);
    }

    /**
     * @param $etId
     * @return EqFilter|InFilter
     */
    public function byEmailTrackingId($etId)
    {
        return $this->Eq(DBField::EMAIL_TRACKING_ID, $etId);
    }

    /**
     * @param $checksum
     * @return EqFilter
     */
    public function byChecksum($checksum)
    {
        return $this->Eq(DBField::CHECKSUM, $checksum);
    }

    /**
     * @param $entityId
     * @return EqFilter
     */
    public function byEntityId($entityId)
    {
        return $this->Eq(DBField::ENTITY_ID, $entityId);
    }

    /**
     * @param $contextXGameAssetId
     * @return EqFilter|InFilter
     */
    public function byContextXGameAssetId($contextXGameAssetId)
    {
        return $this->Eq(DBField::CONTEXT_X_GAME_ASSET_ID, $contextXGameAssetId);
    }

    /**
     * @param $contextEntityId
     * @return EqFilter
     */
    public function byContextEntityId($contextEntityId)
    {
        return $this->Eq(DBField::CONTEXT_ENTITY_ID, $contextEntityId);
    }

    /**
     * @param $contextEntityTypeId
     * @return EqFilter|InFilter
     */
    public function byContextEntityTypeId($contextEntityTypeId)
    {
        return $this->Eq(DBField::CONTEXT_ENTITY_TYPE_ID, $contextEntityTypeId);
    }

    /**
     * @param $countryId
     * @return EqFilter|InFilter
     */
    public function byCountryId($countryId)
    {
        return $this->Eq(DBField::COUNTRY_ID, $countryId);
    }

    /**
     * @param $state
     * @return EqFilter|InFilter
     */
    public function byState($state)
    {
        return $this->Eq(DBField::STATE, $state);
    }

    /**
     * @param $zipCode
     * @return EqFilter|InFilter
     */
    public function byZipCode($zipCode)
    {
        return $this->Eq(DBField::ZIP_CODE, $zipCode);
    }

    /**
     * @param $zip
     * @return EqFilter|InFilter
     */
    public function byZip($zip)
    {
        return $this->Eq(DBField::ZIP, $zip);
    }

    /**
     * @param $addressLine1
     * @return EqFilter|InFilter
     */
    public function byAddressLine1($addressLine1)
    {
        return $this->Eq(DBField::ADDRESS_LINE1, $addressLine1);
    }

    /**
     * @param $city
     * @return EqFilter|InFilter
     */
    public function byCity($city)
    {
        return $this->Eq(DBField::CITY, $city);
    }

    /**
     * @param $publicIpAddress
     * @return EqFilter|InFilter
     */
    public function byPublicIpAddress($publicIpAddress)
    {
        return $this->Eq(DBField::PUBLIC_IP_ADDRESS, $publicIpAddress);
    }

    /**
     * @param $ip
     * @return EqFilter
     */
    public function byIp($ip)
    {
        return $this->Eq(DBField::IP, $ip);
    }

    /**
     * @param $intIp
     * @return EqFilter|InFilter
     */
    public function byIpFrom($intIp)
    {
        return $this->Eq(DBField::IP_FROM, $intIp);
    }

    /**
     * @param $intIp
     * @return EqFilter|InFilter
     */
    public function byIpTo($intIp)
    {
        return $this->Eq(DBField::IP_TO, $intIp);
    }


    /**
     * @param $setting_group_id
     * @return EqFilter
     */
    public function bySettingGroupId($setting_group_id)
    {
        return $this->Eq(DBField::SETTING_GROUP_ID, $setting_group_id);
    }

    /**
     * @param $id
     * @return EqFilter
     */
    public function byGeoRegionId($id)
    {
        return $this->Eq(DBField::GEO_REGION_ID, $id);
    }

    /**
     * @param $value
     * @return EqFilter|InFilter
     */
    public function byValue($value)
    {
        return $this->Eq(DBField::VALUE, $value);
    }

    /**
     * @param $version
     * @return EqFilter|InFilter
     */
    public function byVersion($version)
    {
        return $this->Eq(DBField::VERSION, $version);
    }

    /**
     * @param $versionHash
     * @return EqFilter|InFilter
     */
    public function byVersionHash($versionHash)
    {
        return $this->Eq(DBField::VERSION_HASH, $versionHash);
    }


    /**
     * @return EqFilter
     */
    public function isVisible()
    {
        return $this->Eq(DBField::IS_VISIBLE, 1);
    }

    /**
     * @return EqFilter
     */
    public function isNotVisible()
    {
        return $this->Eq(DBField::IS_VISIBLE, 0);
    }

    /**
     * @return NotEqFilter
     */
    public function isPublished()
    {
        return $this->IsNotNull(DBField::PUBLISHED_TIME);
    }

    /**
     * @return EqFilter
     */
    public function isNotPublished()
    {
        return $this->IsNull(DBField::PUBLISHED_TIME);
    }


    /**
     * @return EqFilter
     */
    public function isActive()
    {
        return $this->Eq(DBField::IS_ACTIVE, 1);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isNotDeprecated()
    {
        return $this->Eq(DBField::IS_DEPRECATED, 0);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isDeprecated()
    {
        return $this->Eq(DBField::IS_DEPRECATED, 1);
    }


    /**
     * @return EqFilter
     */
    public function isNotModerated()
    {
        return $this->Eq(DBField::IS_MODERATED, 0);
    }

    /**
     * @return EqFilter
     */
    public function isNotAcknowledged()
    {
        return $this->IsNull(DBField::CLICKED_TIME);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function hasCustomSlug()
    {
        return $this->Eq(DBField::HAS_CUSTOM_SLUG, 1);
    }


    /**
     * @param string $field
     * @return EqFilter|InFilter
     */
    public function hasPicture()
    {
        return $this->Eq(DBField::HAS_PICTURE, 1);
    }


    /**
     * @param $md5
     * @return EqFilter|InFilter
     */
    public function byMd5($md5)
    {
        return $this->Eq(DBField::MD5, $md5);
    }

    /**
     * @param $guestHash
     * @return EqFilter
     */
    public function byGuestHash($guestHash)
    {
        return $this->Eq(DBField::GUEST_HASH, $guestHash);
    }

    /**
     * @param $guestId
     * @return EqFilter
     */
    public function byGuestId($guestId)
    {
        return $this->Eq(DBField::GUEST_ID, $guestId);
    }

    /**
     * @param $guestCoinId
     * @return EqFilter|InFilter
     */
    public function byGuestCoinId($guestCoinId)
    {
        return $this->Eq(DBField::GUEST_COIN_ID, $guestCoinId);
    }


    /**
     * @param $userId
     * @return EqFilter
     */
    public function byUserId($userId)
    {
        return $this->Eq(DBField::USER_ID, $userId);
    }

    /**
     * @param $uuid
     * @return EqFilter|InFilter
     */
    public function byUuid($uuid)
    {
        return $this->Eq(DBField::UUID, $uuid);
    }

    /**
     * @param $userGroupId
     * @return EqFilter|InFilter
     */
    public function byUserGroupId($userGroupId)
    {
        return $this->Eq(DBField::USERGROUP_ID, $userGroupId);
    }

    /**
     * @param $rightId
     * @return EqFilter|InFilter
     */
    public function byRightId($rightId)
    {
        return $this->Eq(DBField::RIGHT_ID, $rightId);
    }

    /**
     * @param $rightGroupId
     * @return EqFilter|InFilter
     */
    public function byRightGroupId($rightGroupId)
    {
        return $this->Eq(DBField::RIGHT_GROUP_ID, $rightGroupId);
    }

    /**
     * @param $langId
     * @return EqFilter|InFilter
     */
    public function byLanguageId($langId)
    {
        return $this->Eq(DBField::LANGUAGE_ID, $langId);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isI18nActive()
    {
        return $this->Eq(DBField::I18N_ACTIVE, 1);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isI18nPublic()
    {
        return $this->Eq(DBField::I18N_PUBLIC, 1);
    }



    /**
     * @param $date_string
     * @param bool $lte
     * @param string $createField
     * @return LteFilter|LtFilter|null
     */
    public function createdAfter($date_string, $lte = true, $createField = DBField::CREATE_TIME)
    {
        return $lte ? $this->Lte($createField, $date_string) : $this->Lt($createField, $date_string);
    }


    /**
     * @param $date_string
     * @param bool $gte
     * @param string $createField
     * @return GteFilter|GtFilter|null
     */
    public function createdBefore($date_string, $gte = true, $createField = DBField::CREATE_TIME)
    {
        return $gte ? $this->Gte($createField, $date_string) : $this->Gt($createField, $date_string);
    }


    /**
     * @param $activityTypeId
     * @return EqFilter
     */
    public function byActivityTypeId($activityTypeId)
    {
        return $this->Eq(DBField::ACTIVITY_TYPE_ID, $activityTypeId);
    }

    /**
     * @param $activity_id
     * @return EqFilter
     */
    public function byActivityId($activity_id)
    {
        return $this->Eq(DBField::ACTIVITY_ID, $activity_id);
    }

    /**
     * @param $applicationId
     * @return EqFilter|InFilter
     */
    public function byApplicationId($applicationId)
    {
        return $this->Eq(DBField::APPLICATION_ID, $applicationId);
    }

    /**
     * @param $apiKey
     * @return EqFilter|InFilter
     */
    public function byApiKey($apiKey)
    {
        return $this->Eq(DBField::API_KEY, $apiKey);
    }


    /**
     * @param $object_id
     * @return EqFilter
     */
    public function byObjectId($object_id)
    {
        return $this->Eq(DBField::OBJECT_ID, $object_id);
    }

    /**
     * @param $object_type
     * @return EqFilter
     */
    public function byObjectType($object_type)
    {
        return $this->Eq(DBField::OBJECT_TYPE, $object_type);
    }
    /**
     * @param $object_source_id
     * @return EqFilter
     */
    public function byObjectSourceId($object_source_id)
    {
        return $this->Eq(DBField::OBJECT_SOURCE_ID, $object_source_id);
    }

    /**
     * @param $owner_user_id
     * @return EqFilter
     */
    public function byOwnerUserId($owner_user_id)
    {
        return $this->Eq(DBField::OWNER_USER_ID, $owner_user_id);
    }

    /**
     * @param $ownerId
     * @return EqFilter|InFilter
     */
    public function byOwnerId($ownerId)
    {
        return $this->Eq(DBField::OWNER_ID, $ownerId);
    }

    /**
     * @param $ownerTypeId
     * @return EqFilter|InFilter
     */
    public function byOwnerTypeId($ownerTypeId)
    {
        return $this->Eq(DBField::OWNER_TYPE_ID, $ownerTypeId);
    }


    /**
     * @param $creator_id
     * @return EqFilter
     */
    public function byCreatorId($creator_id)
    {
        return $this->Eq(DBField::CREATOR_ID, $creator_id);
    }

    /**
     * @param $creator_id
     * @return EqFilter
     */
    public function byCreatorUserId($creator_id)
    {
        return $this->Eq(DBField::CREATOR_USER_ID, $creator_id);
    }

    /**
     * @param $source_field
     * @param string $count_field
     * @return CountDBField
     */
    public function countGroupField($source_field, $count_field = VField::COUNT)
    {
        return new CountDBField($count_field, $source_field);
    }

    /**
     * @param $name
     * @return LikeFilter
     */
    public function nameLike($name)
    {
        return $this->Like(DBField::NAME, $name);
    }

    /**
     * @param $type
     * @return EqFilter|InFilter
     */
    public function byType($type)
    {
        return $this->Eq(DBField::TYPE, $type);
    }


    /**
     * @return EqFilter|InFilter
     */
    public function isOpen()
    {
        return $this->Eq(DBField::IS_OPEN, 1);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isNotOpen()
    {
        return $this->Eq(DBField::IS_OPEN, 0);
    }

    /**
     * @param $addressId
     * @return EqFilter|InFilter
     */
    public function byAddressId($addressId)
    {
        return $this->Eq(DBField::ADDRESS_ID, $addressId);
    }

    /**
     * @param $addressTypeId
     * @return EqFilter|InFilter
     */
    public function byAddressTypeId($addressTypeId)
    {
        return $this->Eq(DBField::ADDRESS_TYPE_ID, $addressTypeId);
    }

    /**
     * @param $hostId
     * @return EqFilter|InFilter
     */
    public function byHostId($hostId)
    {
        return $this->Eq(DBField::HOST_ID, $hostId);
    }

    /**
     * @param $hostDeviceId
     * @return EqFilter|InFilter
     */
    public function byHostDeviceId($hostDeviceId)
    {
        return $this->Eq(DBField::HOST_DEVICE_ID, $hostDeviceId);
    }


    /**
     * @param $activationTypeId
     * @return EqFilter|InFilter
     */
    public function byActivationTypeId($activationTypeId)
    {
        return $this->Eq(DBField::ACTIVATION_TYPE_ID, $activationTypeId);
    }

    /**
     * @param $activationGroupId
     * @return EqFilter|InFilter
     */
    public function byActivationGroupId($activationGroupId)
    {
        return $this->Eq(DBField::ACTIVATION_GROUP_ID, $activationGroupId);
    }

    /**
     * @param $activationStatusId
     * @return EqFilter|InFilter
     */
    public function byActivationStatusId($activationStatusId)
    {
        return $this->Eq(DBField::ACTIVATION_STATUS_ID, $activationStatusId);
    }

    /**
     * @param $gamePlayerStatTypeId
     * @return EqFilter|InFilter
     */
    public function byGamePlayerStatTypeId($gamePlayerStatTypeId)
    {
        return $this->Eq(DBField::GAME_PLAYER_STAT_TYPE_ID, $gamePlayerStatTypeId);
    }

    /**
     * @param $hostVersionId
     * @return EqFilter|InFilter
     */
    public function byHostVersionId($hostVersionId)
    {
        return $this->Eq(DBField::HOST_VERSION_ID, $hostVersionId);
    }

    /**
     * @param $hostBuildId
     * @return EqFilter|InFilter
     */
    public function byHostBuildId($hostBuildId)
    {
        return $this->Eq(DBField::HOST_BUILD_ID, $hostBuildId);
    }


    /**
     * @param $hostUpdateChannel
     * @return EqFilter|InFilter
     */
    public function byHostUpdateChannel($hostUpdateChannel)
    {
        return $this->Eq(DBField::HOST_UPDATE_CHANNEL, $hostUpdateChannel);
    }


    /**
     * @param $sdkAssetId
     * @return EqFilter|InFilter
     */
    public function bySdkAssetId($sdkAssetId)
    {
        return $this->Eq(DBField::SDK_ASSET_ID, $sdkAssetId);
    }

    /**
     * @param $sdkVersionId
     * @return EqFilter|InFilter
     */
    public function bySdkVersionId($sdkVersionId)
    {
        return $this->Eq(DBField::SDK_VERSION_ID, $sdkVersionId);
    }
    /**
     * @param $sdkBuildId
     * @return EqFilter|InFilter
     */
    public function bySdkBuildId($sdkBuildId)
    {
        return $this->Eq(DBField::SDK_BUILD_ID, $sdkBuildId);
    }


    /**
     * @param $sdkUpdateChannel
     * @return EqFilter|InFilter
     */
    public function bySdkUpdateChannel($sdkUpdateChannel)
    {
        return $this->Eq(DBField::SDK_UPDATE_CHANNEL, $sdkUpdateChannel);
    }

    /**
     * @param $updateChannel
     * @return EqFilter|InFilter
     */
    public function byUpdateChannel($updateChannel)
    {
        return $this->Eq(DBField::UPDATE_CHANNEL, $updateChannel);
    }

    /**
     * @param $pubSubChannel
     * @return EqFilter|InFilter
     */
    public function byPubSubChannel($pubSubChannel)
    {
        return $this->Eq(DBField::PUB_SUB_CHANNEL, $pubSubChannel);
    }

    /**
     * @param $pubSubChannelType
     * @return EqFilter|InFilter
     */
    public function byPubSubChannelType($pubSubChannelType)
    {
        return $this->Eq(DBField::PUB_SUB_CHANNEL_TYPE, $pubSubChannelType);
    }

    /**
     * @param $platformId
     * @return EqFilter|InFilter
     */
    public function byPlatformId($platformId)
    {
        return $this->Eq(DBField::PLATFORM_ID, $platformId);
    }

    /**
    /**
     * @param $sdkPlatformId
     * @return EqFilter|InFilter
     */
    public function bySdkPlatformId($sdkPlatformId)
    {
        return $this->Eq(DBField::SDK_PLATFORM_ID, $sdkPlatformId);
    }

    /**
     * @param $organizationId
     * @return EqFilter|InFilter
     */
    public function byOrganizationId($organizationId)
    {
        return $this->Eq(DBField::ORGANIZATION_ID, $organizationId);
    }

    /**
     * @param $organizationUserStatusId
     * @return EqFilter|InFilter
     */
    public function byOrganizationUserStatusId($organizationUserStatusId)
    {
        return $this->Eq(DBField::ORGANIZATION_USER_STATUS_ID, $organizationUserStatusId);
    }


    /**
     * @param $organizationRoleId
     * @return EqFilter|InFilter
     */
    public function byOrganizationRoleId($organizationRoleId)
    {
        return $this->Eq(DBField::ORGANIZATION_ROLE_ID, $organizationRoleId);
    }

    /**
     * @param $organizationBaseRoleId
     * @return EqFilter|InFilter
     */
    public function byOrganizationBaseRoleId($organizationBaseRoleId)
    {
        return $this->Eq(DBField::ORGANIZATION_BASE_ROLE_ID, $organizationBaseRoleId);
    }

    /**
     * @param $organizationUserId
     * @return EqFilter|InFilter
     */
    public function byOrganizationUserId($organizationUserId)
    {
        return $this->Eq(DBField::ORGANIZATION_USER_ID, $organizationUserId);
    }

    /**
     * @param $organizationRightId
     * @return EqFilter|InFilter
     */
    public function byOrganizationRightId($organizationRightId)
    {
        return $this->Eq(DBField::ORGANIZATION_RIGHT_ID, $organizationRightId);
    }

    /**
     * @param $organizationBaseRightId
     * @return EqFilter|InFilter
     */
    public function byOrganizationBaseRightId($organizationBaseRightId)
    {
        return $this->Eq(DBField::ORGANIZATION_BASE_RIGHT_ID, $organizationBaseRightId);
    }

    /**
     * @param $screenId
     * @return EqFilter|InFilter
     */
    public function byScreenId($screenId)
    {
        return $this->Eq(DBField::SCREEN_ID, $screenId);
    }

    /**
     * @param $locationId
     * @return EqFilter|InFilter
     */
    public function byLocationId($locationId)
    {
        return $this->Eq(DBField::LOCATION_ID, $locationId);
    }

    /**
     * @param $latitude
     * @return EqFilter|InFilter
     */
    public function byLatitude($latitude)
    {
        return $this->Eq(DBField::LATITUDE, $latitude);
    }

    /**
     * @param $longitude
     * @return EqFilter|InFilter
     */
    public function byLongitude($longitude)
    {
        return $this->Eq(DBField::LONGITUDE, $longitude);
    }

    /**
     * @param $companyId
     * @return EqFilter|InFilter
     */
    public function byCompanyId($companyId)
    {
        return $this->Eq(DBField::ORGANIZATION_ID, $companyId);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function canMod()
    {
        return $this->Eq(DBField::CAN_MOD, 1);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function canNotMod()
    {
        return $this->Eq(DBField::CAN_MOD, 0);
    }


    /**
     * @param $gameId
     * @return EqFilter|InFilter
     */
    public function byGameId($gameId)
    {
        return $this->Eq(DBField::GAME_ID, $gameId);
    }

    /**
     * @param $gameTypeId
     * @return EqFilter|InFilter
     */
    public function byGameTypeId($gameTypeId)
    {
        return $this->Eq(DBField::GAME_TYPE_ID, $gameTypeId);
    }

    /**
     * @param $gameCategoryId
     * @return EqFilter|InFilter
     */
    public function byGameCategoryId($gameCategoryId)
    {
        return $this->Eq(DBField::GAME_CATEGORY_ID, $gameCategoryId);
    }

    /**
     * @param $gameModId
     * @return EqFilter|InFilter
     */
    public function byGameModId($gameModId)
    {
        return $this->Eq(DBField::GAME_MOD_ID, $gameModId);
    }

    /**
     * @param $gameModBuildId
     * @return EqFilter|InFilter
     */
    public function byGameModBuildId($gameModBuildId)
    {
        return $this->Eq(DBField::GAME_MOD_BUILD_ID, $gameModBuildId);
    }

    /**
     * @param $gameModDataId
     * @return EqFilter|InFilter
     */
    public function byGameModDataId($gameModDataId)
    {
        return $this->Eq(DBField::GAME_MOD_DATA_ID, $gameModDataId);
    }

    /**
     * @param $gameModDataSheetId
     * @return EqFilter|InFilter
     */
    public function byGameModDataSheetId($gameModDataSheetId)
    {
        return $this->Eq(DBField::GAME_MOD_DATA_SHEET_ID, $gameModDataSheetId);
    }

    /**
     * @param $gameDataId
     * @return EqFilter|InFilter
     */
    public function byGameDataId($gameDataId)
    {
        return $this->Eq(DBField::GAME_DATA_ID, $gameDataId);
    }

    /**
     * @param $gameDataSheetId
     * @return EqFilter|InFilter
     */
    public function byGameDataSheetId($gameDataSheetId)
    {
        return $this->Eq(DBField::GAME_DATA_SHEET_ID, $gameDataSheetId);
    }


    /**
     * @param $gameBuildId
     * @return EqFilter|InFilter
     */
    public function byGameBuildId($gameBuildId)
    {
        return $this->Eq(DBField::GAME_BUILD_ID, $gameBuildId);
    }

    /**
     * @param $gameBuildVersion
     * @return EqFilter|InFilter
     */
    public function byGameBuildVersion($gameBuildVersion)
    {
        return $this->Eq(DBField::GAME_BUILD_VERSION, $gameBuildVersion);
    }

    /**
     * @param $gameControllerVersion
     * @return EqFilter|InFilter
     */
    public function byGameControllerVersion($gameControllerVersion)
    {
        return $this->Eq(DBField::GAME_CONTROLLER_VERSION, $gameControllerVersion);
    }

    /**
     * @param $gameControllerId
     * @return EqFilter|InFilter
     */
    public function byGameControllerId($gameControllerId)
    {
        return $this->Eq(DBField::GAME_CONTROLLER_ID, $gameControllerId);
    }

    /**
     * @param $gameControllerTypeId
     * @return EqFilter|InFilter
     */
    public function byGameControllerTypeId($gameControllerTypeId)
    {
        return $this->Eq(DBField::GAME_CONTROLLER_TYPE_ID, $gameControllerTypeId);
    }

    /**
     * @param $gameAssetId
     * @return EqFilter|InFilter
     */
    public function byGameAssetId($gameAssetId)
    {
        return $this->Eq(DBField::GAME_ASSET_ID, $gameAssetId);
    }

    /**
     * @param $gameInstanceId
     * @return EqFilter|InFilter
     */
    public function byGameInstanceId($gameInstanceId)
    {
        return $this->Eq(DBField::GAME_INSTANCE_ID, $gameInstanceId);
    }

    /**
     * @param $gameInstanceRoundId
     * @return EqFilter|InFilter
     */
    public function byGameInstanceRoundId($gameInstanceRoundId)
    {
        return $this->Eq(DBField::GAME_INSTANCE_ROUND_ID, $gameInstanceRoundId);
    }

    /**
     * @param $hostAssetId
     * @return EqFilter|InFilter
     */
    public function byHostAssetId($hostAssetId)
    {
        return $this->Eq(DBField::HOST_ASSET_ID, $hostAssetId);
    }

    /**
     * @param $hostInstanceId
     * @return EqFilter|InFilter
     */
    public function byHostInstanceId($hostInstanceId)
    {
        return $this->Eq(DBField::HOST_INSTANCE_ID, $hostInstanceId);
    }

    /**
     * @param $hostInstanceTypeId
     * @return EqFilter|InFilter
     */
    public function byHostInstanceTypeId($hostInstanceTypeId)
    {
        return $this->Eq(DBField::HOST_INSTANCE_TYPE_ID, $hostInstanceTypeId);
    }

    /**
     * @param $inviteHash
     * @return EqFilter|InFilter
     */
    public function byInviteHash($inviteHash)
    {
        return $this->Eq(DBField::INVITE_HASH, $inviteHash);
    }

    /**
     * @param $scheduleTime
     * @return LteFilter|null
     */
    public function byScheduleTimeLte($scheduleTime)
    {
        return $this->Lte(DBField::SCHEDULE_TIME, $scheduleTime);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isNotSent()
    {
        return $this->Eq(DBField::IS_SENT, 0);
    }

    /**
     * @param $dateRangeTypeId
     * @return EqFilter|InFilter
     */
    public function byDateRangeTypeId($dateRangeTypeId)
    {
        return $this->Eq(DBField::DATE_RANGE_TYPE_ID, $dateRangeTypeId);
    }

    /**
     * @param $kpiSummaryTypeId
     * @return EqFilter|InFilter
     */
    public function byKpiSummaryTypeId($kpiSummaryTypeId)
    {
        return $this->Eq(DBField::KPI_SUMMARY_TYPE_ID, $kpiSummaryTypeId);
    }

    /**
     * @param $startTime
     * @return LteFilter|null
     */
    public function startTimeLte($startTime)
    {
        return $this->Lte(DBField::START_TIME, $startTime);
    }

    /**
     * @param $endTime
     * @return GtFilter|null
     */
    public function endTimeGt($endTime)
    {
        return $this->Gt(DBField::END_TIME, $endTime);
    }

    /**
     * @param $key
     * @return EqFilter|InFilter
     */
    public function byKey($key)
    {
        return $this->Eq(DBField::KEY, $key);
    }

    /**
     * @param $ssoServiceId
     * @return EqFilter|InFilter
     */
    public function bySsoServiceId($ssoServiceId)
    {
        return $this->Eq(DBField::SSO_SERVICE_ID, $ssoServiceId);
    }

    /**
     * @param $ssoAccountId
     * @return EqFilter|InFilter
     */
    public function bySsoAccountId($ssoAccountId)
    {
        return $this->Eq(DBField::SSO_ACCOUNT_ID, $ssoAccountId);
    }


    /*
     * Orders
     */

    /**
     * @param $orderId
     * @return EqFilter|InFilter
     */
    public function byOrderId($orderId)
    {
        return $this->Eq(DBField::ORDER_ID, $orderId);
    }

    /**
     * @param $orderStatusId
     * @return EqFilter|InFilter
     */
    public function byOrderStatusId($orderStatusId)
    {
        return $this->Eq(DBField::ORDER_STATUS_ID, $orderStatusId);
    }

    /**
     * @param $orderStatusId
     * @return NotEqFilter|NotInFilter|null
     */
    public function notByOrderStatusId($orderStatusId)
    {
        return $this->NotEq(DBField::ORDER_STATUS_ID, $orderStatusId);
    }

    /**
     * @param $orderItemId
     * @return EqFilter|InFilter
     */
    public function byOrderItemId($orderItemId)
    {
        return $this->Eq(DBField::ORDER_ITEM_ID, $orderItemId);
    }

    /**
     * @param $orderItemQuantumId
     * @return EqFilter|InFilter
     */
    public function byOrderItemQuantumId($orderItemQuantumId)
    {
        return $this->Eq(DBField::ORDER_ITEM_QUANTUM_ID, $orderItemQuantumId);
    }

    /*
     * Payments
     */

    /**
     * @param $paymentId
     * @return EqFilter|InFilter
     */
    public function byPaymentId($paymentId)
    {
        return $this->Eq(DBField::PAYMENT_ID, $paymentId);
    }

    /**
     * @param $paymentInvoiceId
     * @return EqFilter|InFilter
     */
    public function byPaymentInvoiceId($paymentInvoiceId)
    {
        return $this->Eq(DBField::PAYMENT_INVOICE_ID, $paymentInvoiceId);
    }

    /**
     * @param $paymentFeeInvoiceId
     * @return EqFilter|InFilter
     */
    public function byPaymentFeeInvoiceId($paymentFeeInvoiceId)
    {
        return $this->Eq(DBField::PAYMENT_FEE_INVOICE_ID, $paymentFeeInvoiceId);
    }

    /**
     * @param $paymentServiceId
     * @return EqFilter|InFilter
     */
    public function byPaymentServiceId($paymentServiceId)
    {
        return $this->Eq(DBField::PAYMENT_SERVICE_ID, $paymentServiceId);
    }

    /**
     * @param $ownerPaymentServiceId
     * @return EqFilter|InFilter
     */
    public function byOwnerPaymentServiceId($ownerPaymentServiceId)
    {
        return $this->Eq(DBField::OWNER_PAYMENT_SERVICE_ID, $ownerPaymentServiceId);
    }

    /**
     * @param $ownerPaymentServiceTokenId
     * @return EqFilter|InFilter
     */
    public function byOwnerPaymentServiceTokenId($ownerPaymentServiceTokenId)
    {
        return $this->Eq(DBField::OWNER_PAYMENT_SERVICE_TOKEN_ID, $ownerPaymentServiceTokenId);
    }

    /**
     * @param $fingerprint
     * @return EqFilter|InFilter
     */
    public function byFingerprint($fingerprint)
    {
        return $this->Eq(DBField::FINGERPRINT, $fingerprint);
    }

    /*
     * Payouts
     */

    /**
     * @param $incomeStatusId
     * @return EqFilter|InFilter
     */
    public function byIncomeStatusId($incomeStatusId)
    {
        return $this->Eq(DBField::INCOME_STATUS_ID, $incomeStatusId);
    }

    /**
     * @param $incomeTypeId
     * @return EqFilter|InFilter
     */
    public function byIncomeTypeId($incomeTypeId)
    {
        return $this->Eq(DBField::INCOME_TYPE_ID, $incomeTypeId);
    }

    /**
     * @param $incomeContentSummaryId
     * @return EqFilter|InFilter
     */
    public function byIncomeContentSummaryId($incomeContentSummaryId)
    {
        return $this->Eq(DBField::INCOME_CONTENT_SUMMARY_ID, $incomeContentSummaryId);
    }

    /**
     * @param $payoutId
     * @return EqFilter|InFilter
     */
    public function byPayoutId($payoutId)
    {
        return $this->Eq(DBField::PAYOUT_ID, $payoutId);
    }

    /**
     * @param $payoutFeeInvoiceId
     * @return EqFilter|InFilter
     */
    public function byPayoutFeeInvoiceId($payoutFeeInvoiceId)
    {
        return $this->Eq(DBField::PAYOUT_FEE_INVOICE_ID, $payoutFeeInvoiceId);
    }

    /**
     * @param $payoutStatusId
     * @return NotEqFilter|NotInFilter|null
     */
    public function notByPayoutStatusId($payoutStatusId)
    {
        return $this->NotEq(DBField::PAYOUT_STATUS_ID, $payoutStatusId);
    }

    /**
     * @param $payoutStatusId
     * @return EqFilter|InFilter
     */
    public function byPayoutStatusId($payoutStatusId)
    {
        return $this->Eq(DBField::PAYOUT_STATUS_ID, $payoutStatusId);
    }


    /**
     * @param $payoutInvoiceId
     * @return EqFilter|InFilter
     */
    public function byPayoutInvoiceId($payoutInvoiceId)
    {
        return $this->Eq(DBField::PAYOUT_INVOICE_ID, $payoutInvoiceId);
    }

    /**
     * @param $payoutServiceId
     * @return EqFilter|InFilter
     */
    public function byPayoutServiceId($payoutServiceId)
    {
        return $this->Eq(DBField::PAYOUT_SERVICE_ID, $payoutServiceId);
    }

    /**
     * @param $serviceAccessTokenId
     * @return EqFilter|InFilter
     */
    public function byServiceAccessTokenId($serviceAccessTokenId)
    {
        return $this->Eq(DBField::SERVICE_ACCESS_TOKEN_ID, $serviceAccessTokenId);
    }

    /**
     * @param $serviceAccessTokenTypeId
     * @return EqFilter|InFilter
     */
    public function byServiceAccessTokenTypeId($serviceAccessTokenTypeId)
    {
        return $this->Eq(DBField::SERVICE_ACCESS_TOKEN_TYPE_ID, $serviceAccessTokenTypeId);
    }

    /**
     * @param $serviceAccessTokenTypeCategoryId
     * @return EqFilter|InFilter
     */
    public function byServiceAccessTokenTypeCategoryId($serviceAccessTokenTypeCategoryId)
    {
        return $this->Eq(DBField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY_ID, $serviceAccessTokenTypeCategoryId);
    }

    /**
     * @param $serviceAccessTokenTypeGroupId
     * @return EqFilter|InFilter
     */
    public function byServiceAccessTokenTypeGroupId($serviceAccessTokenTypeGroupId)
    {
        return $this->Eq(DBField::SERVICE_ACCESS_TOKEN_TYPE_GROUP_ID, $serviceAccessTokenTypeGroupId);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isBuyable()
    {
        return $this->Eq(DBField::IS_BUYABLE, 1);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isNotBuyable()
    {
        return $this->Eq(DBField::IS_BUYABLE, 0);
    }


    /**
     * @return EqFilter|InFilter
     */
    public function isOrganizationCreatable()
    {
        return $this->Eq(DBField::IS_ORGANIZATION_CREATABLE, 1);
    }

    /**
     * @return EqFilter|InFilter
     */
    public function isNotOrganizationCreatable()
    {
        return $this->Eq(DBField::IS_ORGANIZATION_CREATABLE, 0);
    }



    /**
     * Images
     */

    /**
     * @param $imageTypeId
     * @return EqFilter|InFilter
     */
    public function byImageTypeId($imageTypeId)
    {
        return $this->Eq(DBField::IMAGE_TYPE_ID, $imageTypeId);
    }

    /**
     * @param $imageAssetId
     * @return EqFilter|InFilter
     */
    public function byImageAssetId($imageAssetId)
    {
        return $this->Eq(DBField::IMAGE_ASSET_ID, $imageAssetId);
    }
}
