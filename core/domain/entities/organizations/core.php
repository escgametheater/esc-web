<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/6/18
 * Time: 12:23 AM
 */

class OrganizationEntity extends DBManagerEntity
{
    /** @var OrganizationPermissions|null */
    public $permissions;

    /** @var OrganizationsManager $manager */
    protected $manager;

    protected $owner_field = DBField::CREATOR_USER_ID;

    use
        hasOrganizationTypeIdField,
        hasCreatorUserIdField,
        hasSlugField,
        hasDisplayNameField,
        hasBetaAccessField,

        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUrlField,
        hasVirtualAvatarsField,
        hasVirtualAdminEditUrlField,
        hasVirtualOrganizationRightsField,
        hasVirtualOrganizationUsersField,
        hasVirtualOrganizationRolesField,
        hasVirtualOrganizationPermissionsField,
        hasVirtualCreatorUserField;

    /**
     * @param $userId
     * @return bool
     */
    public function isMember($userId)
    {
        if ($this->getCreatorUserId() == $userId)
            return true;

        foreach ($this->getOrganizationUsers() as $organizationUser) {
            if ($organizationUser->is_active() && $organizationUser->getUserId() == $userId)
                return true;
        }
        return false;
    }

    /**
     * @param Request $request
     * @return null|OrganizationPermissions
     */
    public function instantiatePermissionsHelper(Request $request)
    {
        if (!$this->permissions) {
            $this->permissions = new OrganizationPermissions(
                $request,
                $this->getCreatorUserId(),
                $this->getOrganizationUsers(),
                $this->getOrganizationRoles(),
                $this->getOrganizationRights(),
                $this->getOrganizationPermissions()
            );
        }

        return $this->permissions;
    }

    /**
     * @param Request $request
     */
    public function expandOrganization(Request $request)
    {
        $this->manager->postProcessOrganizations($request, $this, true, true);
    }

    /**
     * @return null|OrganizationPermissions
     */
    public function getPermissions()
    {
        if ($this->permissions)
            return $this->permissions;
        else
            throw new Exception("Organization ID {$this->getPk()} has no permissions defined.");
    }

    /**
     * Todo: remove country id hack
     * @return string
     */
    public function getCountryId()
    {
        return CountriesManager::ID_UNITED_STATES;
    }


}

class OrganizationTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class OrganizationBaseRoleEntity extends DBManagerEntity
{
    use
        hasIsPrimaryField,
        hasDisplayNameField,
        hasDescriptionField,
        hasIsPrimaryField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualEditUrlField;

    /**
     * @return bool
     */
    public function is_admin()
    {
        return $this->getPk() == OrganizationsBaseRolesManager::ID_ADMINISTRATOR;
    }

}

class OrganizationBaseRightEntity extends DBManagerEntity
{
    use
        hasNameField,
        hasDisplayNameField,
        hasDescriptionField,
        hasRightIdField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualEditUrlField,
        hasVirtualRightField;
}

class OrganizationBasePermissionEntity extends DBManagerEntity
{
    use
        hasOrganizationBaseRoleIdField,
        hasOrganizationBaseRightIdField,
        hasAccessLevelField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualEditUrlField,
        hasVirtualOrganizationBaseRoleField,
        hasVirtualOrganizationBaseRightField;

}

class OrganizationRoleEntity extends DBManagerEntity
{
    use
        hasOrganizationIdField,
        hasOrganizationBaseRoleIdField,
        hasCreatorUserIdField,
        hasDisplayNameField,

        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualOrganizationSlugField,
        hasVirtualOrganizationBaseRoleField,
        hasVirtualEditUrlField;

}

class OrganizationRightEntity extends DBManagerEntity
{
    use
        hasOrganizationIdField,
        hasOrganizationBaseRightIdField,
        hasNameField,
        hasDisplayNameField,
        hasDescriptionField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualOrganizationBaseRightField;
}

class OrganizationPermissionEntity extends DBManagerEntity
{
    use
        hasOrganizationIdField,
        hasOrganizationRightIdField,
        hasOrganizationRoleIdField,
        hasAccessLevelField,

        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class OrganizationUserEntity extends DBManagerEntity
{
    use
        hasOrganizationIdField,
        hasOrganizationRoleIdField,
        hasOrganizationUserStatusIdField,
        hasUserIdField,
        hasDisplayNameField,

        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualUserField,
        hasVirtualEditUrlField;

    /**
     * @param UserEntity $user
     */
    public function setUser(UserEntity $user)
    {
        $this->updateField(VField::USER, $user);
    }

    /**
     * @return UserEntity
     */
    public function getUser()
    {
        return $this->getVField(VField::USER);
    }

    /**
     * @param OrganizationRoleEntity $organizationRole
     */
    public function setOrganizationRole(OrganizationRoleEntity $organizationRole)
    {
        $this->updateField(VField::ORGANIZATION_ROLE, $organizationRole);
    }

    /**
     * @return OrganizationRoleEntity
     */
    public function getOrganizationRole()
    {
        return $this->getVField(VField::ORGANIZATION_ROLE);
    }

    /**
     * @return OrganizationUserStatusEntity
     */
    public function getOrganizationUserStatus()
    {
        return $this->getVField(VField::ORGANIZATION_USER_STATUS);
    }

    /**
     * @param OrganizationUserStatusEntity $organizationUserStatus
     */
    public function setOrganizationUserStatus(OrganizationUserStatusEntity $organizationUserStatus)
    {
        $this->dataArray[VField::ORGANIZATION_USER_STATUS] = $organizationUserStatus;
    }
}

class OrganizationGameLicenseEntity extends DBManagerEntity
{
    use
        hasOrganizationIdField,
        hasGameIdField,
        hasStartTimeField,
        hasEndTimeField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualGameField,
        hasVirtualOrganizationField;
}

class OrganizationGameModLicenseEntity extends DBManagerEntity
{
    use
        hasOrganizationIdField,
        hasGameModIdField,
        hasStartTimeField,
        hasEndTimeField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualGameModField,
        hasVirtualOrganizationField;
}

class OrganizationUserStatusEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class OrganizationUserInviteEntity extends DBManagerEntity
{
    use
        hasOrganizationUserIdField,
        hasEmailAddressField,
        hasDisplayNameField,
        hasInviteHashField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @return bool
     */
    public function has_expired()
    {
        if ((strtotime((new DateTime('now'))->format(SQL_DATETIME)) - strtotime($this->getCreateTime())) > ONE_DAY)
            return true;
        else
            return false;
    }
}

class OrganizationMetaEntity extends DBManagerEntity
{
    use
        hasOrganizationIdField,
        hasKeyField,
        hasValueField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}


class OrganizationActivityEntity extends DBManagerEntity
{
    use
        hasActivityIdField,
        hasOrganizationIdField,
        hasOrganizationUserIdField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualActivityField,
        hasVirtualOrganizationUserField;

}