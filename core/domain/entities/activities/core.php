<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/2/18
 * Time: 10:05 PM
 */


class ActivityEntity extends BaseDeviceTrackingEntity {

    protected $owner_field = DBField::OWNER_USER_ID;

    use
        hasActivityTypeIdField,
        hasCreatorUserIdField,
        hasObjectIdField,
        hasObjectSourceIdField,
        hasObjectLangIdField,
        hasOwnerUserIdField,
        hasContextEntityIdField,
        hasEntityIdField,
        hasEtIdField,
        hasAcqMediumField,
        hasAcqSourceField,
        hasAcqCampaignField,
        hasAcqTermField,
        hasCreateTimeField,
        hasClickedTimeField,

        hasVirtualCreatorUserField,
        hasVirtualOwnerUserField,
        hasVirtualContextField,
        hasVirtualEntityField;
}

class ActivityTypeEntity extends DBManagerEntity {
    use
        hasDisplayNameField;
}
