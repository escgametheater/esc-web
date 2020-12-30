<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/13/19
 * Time: 6:08 PM
 */

class OrganizationPermissions
{
    /** @var int $requestUserId */
    protected $requestUserId = 0;
    protected $creatorUserId;

    /** @var Permissions $platformPermissions */
    protected $platformPermissions;

    /** @var OrganizationPermissionEntity[] */
    protected $rawPermissions = [];

    /** @var array  */
    protected $rolePermissions = [];

    /** @var OrganizationRightEntity[] */
    protected $organizationRights = [];

    /** @var OrganizationUserEntity[]  */
    protected $organizationUsersByUserId = [];

    /** @var OrganizationUserEntity[]  */
    protected $organizationUsers = [];

    /** @var OrganizationRoleEntity[] */
    protected $organizationRoles = [];

    /**
     * OrganizationPermissions constructor.
     * @param Request $request
     * @param OrganizationUserEntity[] $organizationUsers
     * @param OrganizationRoleEntity[] $organizationRoles
     * @param OrganizationRightEntity[] $organizationRights
     * @param OrganizationPermissionEntity[] $organizationPermissions
     */
    public function __construct(Request $request, $creatorUserId, $organizationUsers, $organizationRoles, $organizationRights, $organizationPermissions)
    {
        $this->platformPermissions = $request->user->permissions;
        $this->requestUserId = $request->user->id;
        $this->creatorUserId = $creatorUserId;

        $this->organizationRights = $organizationRights;
        $this->organizationUsersByUserId = $organizationUsers;

        foreach ($organizationRoles as $organizationRole) {
            $this->organizationRoles[$organizationRole->getPk()] = $organizationRole;
        }

        foreach ($organizationRights as $organizationRight) {
            $this->organizationRights[$organizationRight->getName()] = $organizationRight;
        }

        foreach ($organizationUsers as $organizationUser) {
            if ($organizationUser->is_active()) {
                $this->organizationUsersByUserId[$organizationUser->getUserId()] = $organizationUser;
                $this->organizationUsers[$organizationUser->getPk()] = $organizationUser;
            }
        }

        $this->rawPermissions = $organizationPermissions;
        foreach ($organizationPermissions as $organizationPermission) {
            $organizationRight = $organizationRights[$organizationPermission->getOrganizationRightId()];
            $this->rolePermissions[$organizationRight->getName()][$organizationPermission->getOrganizationRoleId()] = $organizationPermission->getAccessLevel();
        }
    }

    /**
     * @param $organizationRightName
     * @return null|string
     */
    public function getSystemRightNameByOrganizationRightName($organizationRightName)
    {
        if (!array_key_exists($organizationRightName, $this->organizationRights))
            return false;

        return $this->organizationRights[$organizationRightName]
            ->getOrganizationBaseRight()
            ->getRight()
            ->getName();
    }

    /**
     * @param $userId
     * @return null|OrganizationUserEntity
     */
    public function memberFromUserId($userId)
    {
        return array_key_exists($userId, $this->organizationUsersByUserId)
            ? $this->organizationUsersByUserId[$userId]
            : null;
    }

    /**
     * @param $name
     * @param $accessRequested
     * @param $organizationUserId
     * @return bool
     */
    public function memberHas($name, $accessRequested, $organizationUserId)
    {
        if (!array_key_exists($organizationUserId, $this->organizationUsers))
            return false;

        $userId = $this->organizationUsers[$organizationUserId]->getUserId();

        return $this->has($name, $accessRequested, $userId);
    }

    /**
     * @param $name
     * @param $accessRequested
     * @param null $userId
     * @return bool
     */
    public function has($name, $accessRequested, $userId = null)
    {
        // If the right name has no length, this is an erroneous request.
        if (strlen($accessRequested) == 0)
            throw new PermissionsException("Access requested is an empty string for {$name}");

        // If the requesting user was not explicitly specified, let's check requesting user platform permissions.
        if (!$userId) {

            // If the requesting user has the designated system permission super-seeding the org level, they have this right.
            $systemRightName = $this->getSystemRightNameByOrganizationRightName("{$name}");
            if ($systemRightName && $this->platformPermissions->has($systemRightName, $accessRequested)) {
                return true;
            }

            // Set the user ID for further checks shared across everyone
            $userId = $this->requestUserId;
        }

        // The Founder / Creator (and shimmed orgs) always have all permissions.
        if ($this->creatorUserId === (int)$userId)
            return true;

        // If the userID does not exist in the org users, the user does not have any permissions.
        if (!$organizationUser = $this->memberFromUserId($userId))
            return false;

        return $this->roleHas($name, $accessRequested, $organizationUser->getOrganizationRoleId());
    }

    /**
     * @param $name
     * @param $accessRequested
     * @param $organizationRoleId
     * @return bool
     */
    public function roleHas($name, $accessRequested, $organizationRoleId)
    {
        // If this roleId does not exist, the access level is false.
        if (!array_key_exists($organizationRoleId, $this->organizationRoles))
            return false;

        // If this right does not exist, the permission is false.
        if (!array_key_exists($name, $this->rolePermissions))
            return false;

        /** @var OrganizationPermissionEntity $rolePermission */
        $rolePermission = $this->rolePermissions[$name][$organizationRoleId];

        $accessLevel = Rights::getAccessLevel($accessRequested);

        return ($rolePermission & $accessLevel) == $accessLevel;
    }

}