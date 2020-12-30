<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 11/29/15
 * Time: 4:06 AM
 */

class UserEntity extends BaseIdentityDBEntity {

    use
        // DB Fields
        hasUsernameField,
        hasEmailAddressField,
        hasPhoneNumberField,
        hasFullnameField,
        hasFirstNameField,
        hasLastNameField,
        hasDisplayNameField,
        hasDescriptionField,
        hasUserGroupIdField,
        hasBetaAccessField,
        hasCountryIdField,
        hasFirstSessionIdField,
        hasUiLanguageIdField,
        hasGeoRegionIdField,
        hasJoinDateField,
        hasLastLoginField,
        hasGenderField,
        hasSaltField,

        // Virtual Fields
        hasVirtualUrlField,
        hasVirtualEditUrlField,
        hasVirtualAdminEditUrlField;

    protected $email_settings = [];

    protected $avatarImage;

    /**
     * @param ImageEntity $image
     */
    public function setAvatarImage(ImageEntity $image)
    {
        foreach ($image->getImageType()->getImageTypeSizes() as $imageTypeSize) {
            $this->dataArray[VField::AVATAR][$imageTypeSize->generateUrlField()] = $image->field($imageTypeSize->generateUrlField());
            if ($imageTypeSize->getSlug() == 'medium') {
                $this->dataArray[VField::AVATAR_URL] = $image->field($imageTypeSize->generateUrlField());
            } else {
                $this->dataArray[VField::AVATAR."_{$imageTypeSize->generateUrlField()}"] = $image->field($imageTypeSize->generateUrlField());
            }
        }
        $this->avatarImage = $image;
        $this->updateField(VField::HAS_AVATAR, true);
    }

    /**
     * @return bool
     */
    public function has_avatar()
    {
        return $this->getVField(VField::HAS_AVATAR);
    }

    /**
     * @param Request $request
     * @return bool
     * @throws Exception
     */
    public function can_edit(Request $request)
    {
        return $this->pkIs($request->user->id) || $request->user->permissions->has(RightsManager::RIGHT_USERS, Rights::MODERATE);
    }

    /**
     * @param Request $request
     * @return $this
     */
    public function getOwner(Request $request)
    {
        return $this;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (in_array($offset, array_merge(UsersManager::$fields, UserProfilesManager::$fields))) {
            if (array_key_exists($offset, $this->dataArray) && $this->dataArray[$offset] !== $value)
                $this->originalData[$offset] = $this->dataArray[$offset];

            $this->dataArray[$offset] = $value;

        } else {
            $this->dataArray[$offset] = $value;
        }
    }

    /**
     * @param string $content
     * @param int $code
     */
    public function sendFlashMessage($content = '', $code = MSG_INFO, $options = [])
    {
        FlashMessagesManager::sendFlashMessage($this->getPk(), $content, $code, $options);
    }

    /**
     * @return bool
     */
    public function is_verified()
    {
        return $this->dataArray[DBField::IS_VERIFIED] ? true : false;
    }

    /**
     * @return bool
     */
    public function display_profile_roadblock()
    {
        if (!$this->getEmailAddress() || !$this->getPhoneNumber() ) // || !$this->getUsername()
            return true;
        else
            return false;
    }


    /**
     * @param null $manager_name
     * @return UsersManager
     * @throws EntityManagerClassMissingException
     * @throws EntityManagerUndefinedException
     */
    public function getManager($manager_name = null)
    {
        return parent::getManager($manager_name);
    }

    /**
     * @param Request $request
     * @param null $setting_group_id
     * @return EmailSettingEntity[]|EmailSettingEntity|array
     */
    public function getEmailSettings(Request $request, $setting_group_id = null)
    {
        if (empty($this->email_settings)) {
            $settings = $request->managers->emailSettings()->getAllEmailSettingsForUser($request, $this);

            foreach ($settings as $setting) {
                $this->email_settings[$setting->getEmailSettingGroupId()] = $setting;
            }
        }
        if ($setting_group_id)
            return !empty($this->email_settings[$setting_group_id]) ? $this->email_settings[$setting_group_id] : [];
        else
            return $this->email_settings;
    }

    /**
     * @param int $emailTypeId
     * @param Request $request
     * @return bool
     */
    public function checkIsEmailSubscribed(Request $request, $emailTypeId)
    {
        $emailSettingsManager = $request->managers->emailSettings();
        $emailTypesManager = $request->managers->emailTypes();
        $emailSettingsGroupsManager = $request->managers->emailSettingsGroups();

        // First fetch user's mention email preference
        $emailSetting = $emailSettingsManager->getEmailSettingByEmailTypeIdForUser($request, $this->getPk(), $emailTypeId);

        if (!$emailSetting) {
            $emailType = $emailTypesManager->getEmailTypeById($request, $emailTypeId);
            $emailSettingGroup = $emailSettingsGroupsManager->getSettingGroupById($request, $emailType->getEmailSettingGroupId());

            $emailSetting = $emailSettingsManager->initializeEmailSetting($request, $this, $emailSettingGroup);
        }

        // If user subscribes to mentions emails and has verified their account, finally we should handle email notifications.
        return $emailSettingsManager->checkShouldEmail($request, $emailSetting, $this);
    }

    /**
     * @return bool
     */
    public function is_superadmin()
    {
        return in_array($this->getPk(), Rights::getSuperAdminIds());
    }

    /**
     * @return string|null
     */
    public function getFirstSessionHash()
    {
        return $this->field(DBField::FIRST_SESSION_HASH);
    }
}

class UserMetaEntity extends DBManagerEntity
{
    use
        hasUserIdField,
        hasKeyField,
        hasValueField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

/**
 * SETTINGS
 */


class UserGroupEntity extends DBManagerEntity {

    use
        hasDisplayNameField,
        hasDescriptionField,
        hasIsPrimaryField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualParsedDescriptionField,
        hasVirtualEditUrlField,
        hasVirtualEditPermissionsUrlField,
        hasVirtualDeleteUrlField;
}

class UserUserGroupEntity extends DBManagerEntity {

    use
        hasUserIdField,
        hasUserGroupIdField,
        hasCreateTimeField,
        hasCreatorIdField;
}

class LoginAttemptEntity extends DBManagerEntity {

}

class PasswordResetAttemptEntity extends DBManagerEntity {

}