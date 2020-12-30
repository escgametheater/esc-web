<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/2/18
 * Time: 10:37 PM
 */

class DeviceTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasDisplayOrderField,
        hasCreatedByField,
        hasModifiedByField;
}

class DeviceEntity extends DBManagerEntity
{
    use
        hasDeviceTypeIdField,
        hasDisplayNameField,
        hasAcqMediumField,
        hasAcqSourceField,
        hasAcqCampaignField,
        hasAcqTermField,
        hasCreatedByField,
        hasModifiedByField;
}

class RequestEntity extends DBManagerEntity
{
    use
        hasSessionIdField,
        hasGuestIdField,
        hasUserIdField,
        hasApplicationUserAccessTokenIdField,
        hasCreateTimeField,
        hasSchemeField,
        hasMethodField,
        hasHostField,
        hasAppField,
        hasUriField,
        hasParamsField,
        hasReferrerField,
        hasAcqMediumField,
        hasAcqSourceField,
        hasAcqCampaignField,
        hasAcqTermField,
        hasIpAddressField,
        hasResponseTimeField,
        hasResponseCodeField;
}

class ApiLogEntity extends DBManagerEntity
{

}