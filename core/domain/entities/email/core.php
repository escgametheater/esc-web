<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 11/29/15
 * Time: 4:40 AM
 */


class EmailTrackingEntity extends DBManagerEntity {

    use
        hasEmailAddressField,
        hasEmailTypeIdField,
        hasChecksumField,
        hasLanguageIdField,
        hasUserIdField,
        hasIsOpenedField,
        hasIsClickedField,

        hasAcqSourceField,
        hasAcqCampaignField,

        hasActivityIdField,
        hasSessionIdField,
        hasEntityIdField,
        hasContextEntityIdField,

        hasClickedTimeField,
        hasOpenedTimeField,
        hasSentTimeField,
        hasCreateTimeField;
}

class EmailRecordEntity extends DBManagerEntity {

    use
        hasEmailTrackingIdField,
        hasEmailAddressField,
        hasTitleField,
        hasBodyField,
        hasSenderField,
        hasCreateTimeField;
}


class EmailSettingEntity extends DBManagerEntity {

    use
        hasUserIdField,
        hasEmailSettingGroupIdField,
        hasValueField,
        hasCreateTimeField;

    /** @var EmailSettingsGroupEntity */
    protected $setting_group;

    /**
     * @param Request $request
     * @return EmailSettingsGroupEntity|null
     */
    public function getSettingGroup(Request $request)
    {
        $emailSettingsGroupsManager = $request->managers->emailSettingsGroups();

        return !empty($this->setting_group) ? $this->setting_group : $this->setting_group = $emailSettingsGroupsManager->getSettingGroupById(
            $request,
            $this->getEmailSettingGroupId()
        );
    }

    /**
     * @param Request $request
     * @return array|EmailSettingsGroupEntity
     */

    public function getActiveSettingGroup(Request $request)
    {
        $setting_group = $this->getSettingGroup($request);

        return $setting_group && $setting_group->is_active() ? $setting_group : [];

    }

    /**
     * @return string
     */
    public function getDynamicFormFieldId()
    {
        return FormField::createDynamicFieldName(FormField::SETTING_GROUP_ID, $this->getEmailSettingGroupId());
    }

}

class EmailSettingHistoryEntity {

    use
        hasUserIdField,
        hasEmailSettingGroupIdField,
        hasActivityIdField,
        hasCreateTimeField;

}

class EmailTypeEntity extends DBManagerEntity {

    use
        hasDisplayNameField,
        hasEmailSettingGroupIdField;
}

class EmailSettingsGroupEntity extends DBManagerEntity {

    use
        hasDisplayNameField,
        hasIsActiveField,
        hasDefaultSettingField;
}
