<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 12/11/15
 * Time: 3:15 AM
 */

class UserAvatarUploadForm extends ImageUploadForm {

    public function __construct(Request $request, UserEntity $user, $data = null, $files = null, $user_id = null)
    {
        // Set Form Settings for this entity/field.
        $this->formField = FormField::PICTURE;
        $this->form_field_display_name = $request->translations['Avatar'];

        // Set Activity Type ID so we track this upload.
        $this->activityTypeId = ActivityTypesManager::ACTIVITY_TYPE_USER_AVATAR_UPLOAD;

        // Set Manager to handle DB Updates here.
        $user->setManager($request->managers->usersProfiles());

        // Set Croppie Settings
        $this->settings[self::SETTING_CROPPIE_SETTINGS] = 'userAvatar';
        $this->settings[self::SETTING_CROPPIE_WIDTH] = UserProfilesManager::AVATAR_LARGE_WIDTH;
        $this->settings[self::SETTING_CROPPIE_HEIGHT] = UserProfilesManager::AVATAR_LARGE_HEIGHT;

        // Run Parent Constructor Always.
        parent::__construct($request, $user, $data, $files, $user_id);
    }
}
