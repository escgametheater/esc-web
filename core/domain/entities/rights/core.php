<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 10/1/16
 * Time: 12:12 PM
 */

class RightEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasRightGroupIdField,
        hasDescriptionField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualParsedDescriptionField,
        hasVirtualEditUrlField,
        hasVirtualDeleteUrlField;

    /**
     * @return string
     */
    public function getRightGroupName()
    {
        return $this->getVField(VField::RIGHT_GROUP_NAME);
    }
}

class RightGroupEntity extends DBManagerEntity {

    use
        hasDisplayNameField,
        hasDisplayOrderField,
        hasDescriptionField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualParsedDescriptionField,
        hasVirtualEditUrlField,
        hasVirtualDeleteUrlField;
}

class UserGroupRightsSettingEntity extends DBManagerEntity {

    use
        hasUserGroupIdField,
        hasRightIdField,
        hasAccessLevelField;
}