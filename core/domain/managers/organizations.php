<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/6/18
 * Time: 12:22 AM
 */

Entities::uses('organizations');

require "organizations-helpers.php";

class OrganizationsManager extends BaseEntityManager {

    // converts to % of shared sales that organizations keep for game subs.
    const DEFAULT_REV_SHARE = 0.75;

    const ID_ESC = 1;
    const ID_ESC_EXTERNAL = 2;

    protected $entityClass = OrganizationEntity::class;
    protected $table = Table::Organization;
    protected $table_alias = TableAlias::Organization;
    protected $pk = DBField::ORGANIZATION_ID;

    public static $fields = [
        DBField::ORGANIZATION_ID,
        DBField::CREATOR_USER_ID,
        DBField::SLUG,
        DBField::DISPLAY_NAME,

        DBField::HAS_BETA_ACCESS,

        DBField::IS_ACTIVE,

        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    public $removed_json_fields = [
        VField::CREATOR_USER,
        VField::ORGANIZATION_ROLES,
        VField::ORGANIZATION_USERS,
    ];

    /**
     * @param OrganizationEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $imageTypeSizesManager = $request->managers->imagesTypesSizes();

        if (!$data->hasField(VField::ORGANIZATION_ROLES))
            $data->updateField(VField::ORGANIZATION_ROLES, []);

        if (!$data->hasField(VField::ORGANIZATION_USERS))
            $data->updateField(VField::ORGANIZATION_USERS, []);

        if (!$data->hasField(VField::CREATOR_USER))
            $data->updateField(VField::CREATOR_USER, []);

        $adminEditUrl = $request->getWwwUrl("/admin/organizations/edit/{$data->getPk()}/");
        $data->updateField(VField::ADMIN_EDIT_URL, $adminEditUrl);

        $url = $request->getDevelopUrl("/teams/{$data->getSlug()}");
        $data->updateField(VField::URL, $url);

        $imageTypeSizes = $imageTypeSizesManager->getAllImageTypeSizesByImageTypeId($request, ImagesTypesManager::ID_ORGANIZATION_AVATAR);
        foreach ($imageTypeSizes as $imageTypeSize) {
            $avatars[$imageTypeSize->generateUrlField()] = $request->getWwwUrl("/static/images/avatars/no-avatar.jpg?b=1");
        }
        $data->updateField(VField::AVATAR, $avatars);
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getFormFields(Request $request)
    {
        $fields = [
            new CharField(DBField::DISPLAY_NAME, 'Organization Name', 64, true, 'This is the public name of the organization.'),
            new SlugField(DBField::SLUG, 'Slug', 32, false, '(Optional) The slug is used for identifying the organization in URLs, and is auto-generated if not filled out.'),
            new BooleanField(DBField::HAS_BETA_ACCESS, 'Is Pilot Program Member', false, 'If checked, flags this organization as part of the pilot program.')
        ];

        return $fields;
    }

    /**
     * @param $slug
     * @return bool
     */
    public function checkSlugExists($slug)
    {
        return $this->query()
            ->filter($this->filters->bySlug($slug))
            ->exists();
    }

    /**
     * @param Request $request
     * @param $creatorUserId
     * @param $displayName
     * @param null $slug
     * @return OrganizationEntity
     */
    public function createNewOrganization(Request $request, UserEntity $user, $displayName, $slug = null,
                                          $organizationTypeId = OrganizationsTypesManager::ID_TEAM, $baseRoleIds = [],
                                          $hasBetaAccess = 0, $productionHostSlug = null, $testHostSlug = null)
    {
        $organizationsBaseRolesManager = $request->managers->organizationsBaseRoles();
        $organizationsBaseRightsManager = $request->managers->organizationsBaseRights();
        $organizationsBasePermissionsManager = $request->managers->organizationsBasePermissions();

        $organizationBaseRoles = $organizationsBaseRolesManager->getAllActivePrimaryOriganizationBaseRoles($request);
        $organizationBaseRights = $organizationsBaseRightsManager->getAllActiveOrganizationBaseRights($request);
        $organizationBasePermissions = $organizationsBasePermissionsManager->getAllPrimaryOrganizationBasePermissions($request);

        $organizationsRolesManager = $request->managers->organizationsRoles();
        $organizationsRightsManager = $request->managers->organizationsRights();
        $organizationsPermissionsManager = $request->managers->organizationsPermissions();
        $organizationsUsersManager = $request->managers->organizationsUsers();

        $hostsManager = $request->managers->hosts();

        if (!$baseRoleIds)
            $baseRoleIds = array_keys($organizationBaseRoles);
        else
            if (!in_array(OrganizationsBaseRolesManager::ID_ADMINISTRATOR, $baseRoleIds))
                array_unshift($baseRoleIds, OrganizationsBaseRolesManager::ID_ADMINISTRATOR);

        if ($organizationTypeId == OrganizationsTypesManager::ID_PRIVATE) {
            $slug = uuidV4HostName();
        } else {
            if (!$slug)
                $slug = slugify($displayName);

            $origSlug = $slug;

            $slugExists = $this->checkSlugExists($slug);

            $i = 1;

            while ($slugExists) {
                $slug = "{$origSlug}-{$i}";
                $slugExists = $this->checkSlugExists($slugExists);
                $i++;
            }
        }

        $data = [
            DBField::ORGANIZATION_TYPE_ID => $organizationTypeId,
            DBField::CREATOR_USER_ID => $user->getPk(),
            DBField::HAS_BETA_ACCESS => $hasBetaAccess,
            DBField::SLUG => $slug,
            DBField::DISPLAY_NAME => $displayName,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId,
        ];

        /** @var OrganizationEntity $organization */
        $organization = $this->query($request->db)->createNewEntity($request, $data, false);

        $organization->setCreatorUser($user);

        foreach ($organizationBaseRights as $organizationBaseRight) {
            $organizationRight = $organizationsRightsManager->createNewOrganizationRight($request, $organization, $organizationBaseRight);
            $organization->setOrganizationRight($organizationRight);
        }

        foreach ($organizationBaseRoles as $organizationBaseRole) {

            if (in_array($organizationBaseRole->getPk(), $baseRoleIds)) {
                $organizationRole = $organizationsRolesManager->createNewOrganizationRole(
                    $request,
                    $organizationBaseRole,
                    $organization->getPk(),
                    null,
                    $organization->getSlug()
                );

                if ($organizationRole->is_admin()) {

                    $organizationUser = $organizationsUsersManager->createNewOrganizationUser(
                        $request,
                        $user,
                        $user->getDisplayName(),
                        $organization->getPk(),
                        $organizationRole->getPk(),
                        OrganizationsUsersStatusesManager::ID_ACTIVE
                    );

                    $organizationUser->setUser($user);

                    $organizationUser->setOrganizationRole($organizationRole);
                    $organization->setOrganizationUser($organizationUser);
                }

                $organization->setOrganizationRole($organizationRole);
            }
        }

        foreach ($organizationBasePermissions as $organizationBasePermission) {

            if (in_array($organizationBasePermission->getOrganizationBaseRoleId(), $baseRoleIds)) {

                $organizationRight = $organization->getOrganizationRightByBaseRightId($organizationBasePermission->getOrganizationBaseRightId());
                $organizationRole = $organization->getOrganizationRoleByOrganizationBaseRoleId($organizationBasePermission->getOrganizationBaseRoleId());

                $organizationPermission = $organizationsPermissionsManager->createNewOrganizationPermission(
                    $request,
                    $organization->getPk(),
                    $organizationRight->getPk(),
                    $organizationRole->getPk(),
                    $organizationBasePermission->getPk(),
                    $organizationBasePermission->getAccessLevel(),
                    $organizationBasePermission->getIsActive()
                );

                $organization->setOrganizationPermission($organizationPermission);
            }
        }

//        if ($productionHostSlug) {
//            $organizationProductionHost = $hostsManager->createNewHost(
//                $request,
//                EntityType::ORGANIZATION,
//                $organization->getPk(),
//                'Production',
//                $productionHostSlug,
//                1
//            );
//        }
//
//        if ($testHostSlug) {
//            $organizationSandboxHost = $hostsManager->createNewHost(
//                $request,
//                EntityType::ORGANIZATION,
//                $organization->getPk(),
//                'Test',
//                $testHostSlug,
//                0
//            );
//        }

        $organization->instantiatePermissionsHelper($request);

        return $organization;
    }

    /**
     * @param Request $request
     * @param OrganizationEntity[]|OrganizationEntity $organizations
     */
    public function postProcessOrganizations(Request $request, $organizations = [], $expand = false, $instantiatePermissions = false)
    {
        $organizationsRolesManager = $request->managers->organizationsRoles();
        $organizationsUsersManager = $request->managers->organizationsUsers();
        $organizationsRightsManager = $request->managers->organizationsRights();
        $organizationsPermissionsManager = $request->managers->organizationsPermissions();
        $imagesManager = $request->managers->images();

        $usersManager = $request->managers->users();

        if ($organizations) {

            if ($organizations instanceof OrganizationEntity)
                $organizations = [$organizations];

            /** @var OrganizationEntity[] $organizations */
            $organizations = array_index($organizations, $this->getPkField());
            $organizationIds = array_keys($organizations);

            // Permissions Requested
            if ($instantiatePermissions) {

                // Get and populate organization roles
                $organizationsRoles = $organizationsRolesManager->getOrganizationRolesByOrganizationId($request, $organizationIds);
                /** @var OrganizationRoleEntity[] $organizationsRoles */
                $organizationsRoles = array_index($organizationsRoles, $organizationsRolesManager->getPkField());
                foreach ($organizationsRoles as $organizationsRole) {
                    $organizations[$organizationsRole->getOrganizationId()]->setOrganizationRole($organizationsRole);
                }

                // Get and populate organization users with the roles
                $organizationsUsers = $organizationsUsersManager->getActiveOrganizationUsersByOrganizationId($request, $organizationIds, $expand);
                foreach ($organizationsUsers as $organizationsUser) {
                    $organizationsUser->setOrganizationRole($organizationsRoles[$organizationsUser->getOrganizationRoleId()]);
                    $organizations[$organizationsUser->getOrganizationId()]->setOrganizationUser($organizationsUser);
                }

                // Get and set Organization Rights
                $organizationsRights = $organizationsRightsManager->getAllOrganizationRightsByOrganizationId($request, $organizationIds, false);
                foreach ($organizationsRights as $organizationRight) {
                    $organizations[$organizationRight->getOrganizationId()]->setOrganizationRight($organizationRight);
                }

                // Get and set Organization Permissions
                $organizationPermissions = $organizationsPermissionsManager->getAllOrganizationPermissionsForOrganizationIds($request, $organizationIds);
                foreach ($organizationPermissions as $organizationPermission) {
                    $organizations[$organizationPermission->getOrganizationId()]->setOrganizationPermission($organizationPermission);
                }
            }

            if ($expand) {
                // Get Creator Users for the organizations
                $creatorUserIds = unique_array_extract(DBField::CREATOR_USER_ID, $organizations);
                $creatorUsers = $usersManager->getUsersByIds($request, $creatorUserIds);
            }

            // Get/set game thumbnail images
            $avatarImages = $imagesManager->getActiveOrganizationAvatarImagesByOrganizationIds($request, $organizationIds);
            foreach ($avatarImages as $avatarImage) {
                $organizations[$avatarImage->getContextEntityId()]->setAvatarImageUrls($avatarImage);
            }

            // Set OrganizationSpecific Contexts
            foreach ($organizations as $organization) {

                // Permissions Requested
                if ($instantiatePermissions)
                    $organization->instantiatePermissionsHelper($request);

                if ($expand)
                    $organization->setCreatorUser($creatorUsers[$organization->getCreatorUserId()]);
            }
        }
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param bool $expand
     * @param bool $instantiatePermissions
     * @return OrganizationEntity
     * @throws ObjectNotFound
     */
    public function getOrganizationById(Request $request, $organizationId, $expand = false, $instantiatePermissions = false)
    {
        /** @var OrganizationEntity $organization */
        $organization = $this->query($request->db)
            ->filter($this->filters->byPk($organizationId))
            ->get_entity($request);

        $this->postProcessOrganizations($request, $organization, $expand, $instantiatePermissions);

        return $organization;
    }

    /**
     * @param Request $request
     * @param $organizationIds
     * @param bool $expand
     * @return OrganizationEntity[]
     */
    public function getOrganizationsByIds(Request $request, $organizationIds, $expand = false, $instantiatePermissions = false, $sortByField = null)
    {
        /** @var OrganizationEntity[] $organizations */
        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->byPk($organizationIds));

        if ($sortByField)
            $queryBuilder->sort_asc(DBField::DISPLAY_NAME);

        $organizations = $queryBuilder->get_entities($request);

        $this->postProcessOrganizations($request, $organizations, $expand, $instantiatePermissions);

        return $organizations ? $this->index($organizations) : [];
    }

    /**
     * @param Request $request
     * @param $userId
     * @param bool $expand
     * @return OrganizationEntity[]
     */
    public function getOrganizationsByUserId(Request $request, $userId, $expand = false, $instantiatePermissions = false)
    {
        $organizationsUsersManager = $request->managers->organizationsUsers();

        $joinOrganizationsUsersFilter = $organizationsUsersManager->filters->And_(
            $organizationsUsersManager->filters->byOrganizationId($this->createPkField()),
            $organizationsUsersManager->filters->byUserId($userId)
        );

        /** @var OrganizationEntity[] $organizations */
        $organizations = $this->query($request->db)
            ->inner_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->filter($organizationsUsersManager->filters->isActive())
            ->filter($this->filters->isActive())
            ->get_entities($request);

        $this->postProcessOrganizations($request, $organizations, $expand, $instantiatePermissions);

        return $organizations;
    }

    /**
     * @param Request $request
     * @param $organizationSlug
     * @param $userId
     * @param bool $expand
     * @param bool $instantiatePermissions
     * @return OrganizationEntity
     */
    public function getOrganizationBySlugAndUserId(Request $request, $organizationSlug, $userId, $expand = false, $instantiatePermissions = false)
    {
        $organizationsUsersManager = $request->managers->organizationsUsers();

        $joinOrganizationsUsersFilter = $organizationsUsersManager->filters->And_(
            $organizationsUsersManager->filters->byOrganizationId($this->createPkField()),
            $organizationsUsersManager->filters->byUserId($userId)
        );

        /** @var OrganizationEntity $organization */
        $organization = $this->query($request->db)
            ->inner_join($organizationsUsersManager, $joinOrganizationsUsersFilter)
            ->filter($this->filters->bySlug($organizationSlug))
            ->filter($this->filters->isActive())
            ->filter($organizationsUsersManager->filters->isActive())
            ->get_entity($request);

        $this->postProcessOrganizations($request, $organization, $expand, $instantiatePermissions);

        return $organization;
    }

    /**
     * @param Request $request
     * @param $organizationSlug
     * @param bool $expand
     * @param bool $instantiatePermissions
     * @return OrganizationEntity
     */
    public function getOrganizationBySlug(Request $request, $organizationSlug, $expand = false, $instantiatePermissions = false)
    {
        /** @var OrganizationEntity $organization */
        $organization = $this->query($request->db)
            ->filter($this->filters->bySlug($organizationSlug))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        $this->postProcessOrganizations($request, $organization, $expand, $instantiatePermissions);

        return $organization;
    }

    /**
     * @param Request $request
     * @param int $page
     * @param int $perPage
     * @param bool $expand
     * @return OrganizationEntity[]
     */
    public function getOrganizations(Request $request, $page = 1, $perPage = DEFAULT_PERPAGE, $expand = false, $instantiatePermissions = false)
    {
        /** @var OrganizationEntity[] $organizations */
        $organizations = $this->query($request->db)
            ->filter($this->filters->isActive())
            ->paging($page, $perPage)
            ->get_entities($request);

        $this->postProcessOrganizations($request, $organizations, $expand, $instantiatePermissions);

        return $organizations;
    }

    /**
     * @param Request $request
     * @param $query
     * @return OrganizationEntity[]
     */
    public function searchAutoCompleteOrganizations(Request $request, $query, $count = 5, $userId = null, $isAdmin = false)
    {
        $organizationsUsersManager = $request->managers->organizationsUsers();

        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->isActive())
            ->group_by($this->createPkField())
            ->limit($count);

        if (Validators::int($userId) && $userId && !$isAdmin) {
            $joinOrgUsersFilter = $this->filters->And_(
                $organizationsUsersManager->filters->byOrganizationId($this->createPkField()),
                $organizationsUsersManager->filters->byOrganizationUserStatusId(OrganizationsUsersStatusesManager::ID_ACTIVE),
                $organizationsUsersManager->filters->byUserId($userId),
                $organizationsUsersManager->filters->isActive()
            );
            $queryBuilder->inner_join($organizationsUsersManager, $joinOrgUsersFilter);
        }

        if ($query) {
            $queryBuilder->filter($this->filters->Like(DBField::DISPLAY_NAME, $query));
        }

        $organizations = $queryBuilder->get_entities($request);

        $this->postProcessOrganizations($request, $organizations);

        return $organizations;
    }

}

class OrganizationsTypesManager extends BaseEntityManager
{
    const ID_PRIVATE = 1;
    const ID_TEAM = 2;

    protected $entityClass = OrganizationTypeEntity::class;
    protected $table = Table::OrganizationType;
    protected $table_alias = TableAlias::OrganizationType;
    protected $pk = DBField::ORGANIZATION_TYPE_ID;

    const GNS_KEY_PREFIX = GNS_ROOT.'.organization-types';

    public static $fields = [
        DBField::ORGANIZATION_TYPE_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];
}

class OrganizationsBaseRolesManager extends BaseEntityManager
{
    const ID_ADMINISTRATOR = 1;
    const ID_BILLING_ADMIN = 2;
    const ID_DEVELOPER = 3;
    const ID_ANALYST = 4;
    const ID_GUEST = 5;
    const ID_CUSTOM = 6;
    const ID_EVENT_OPERATOR = 7;

    protected $entityClass = OrganizationBaseRoleEntity::class;
    protected $table = Table::OrganizationBaseRole;
    protected $table_alias = TableAlias::OrganizationBaseRole;
    protected $pk = DBField::ORGANIZATION_BASE_ROLE_ID;

    const GNS_KEY_PREFIX = GNS_ROOT.'.organizations-base-roles';

    public static $fields = [
        DBField::ORGANIZATION_BASE_ROLE_ID,
        DBField::DISPLAY_NAME,
        DBField::DESCRIPTION,
        DBField::IS_PRIMARY,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /** @var OrganizationBaseRoleEntity[] */
    protected $organizationBaseRoles = [];

    /**
     * @param OrganizationBaseRoleEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::EDIT_URL, $request->getWwwUrl("/admin/organizations/base-roles/edit/{$data->getPk()}"));
    }

    /**
     * @return string
     */
    public function generateCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return OrganizationBaseRoleEntity[]|array
     */
    public function getAllOrganizationBaseRoles(Request $request)
    {
        if (!$this->organizationBaseRoles) {
            /** @var OrganizationBaseRoleEntity[] $organizationsBaseRoles */
            $organizationsBaseRoles = $this->query($request->db)
                ->cache($this->generateCacheKey(), ONE_WEEK)
                ->get_entities($request);

            if ($organizationsBaseRoles)
                $organizationsBaseRoles = array_index($organizationsBaseRoles, $this->getPkField());

            $this->organizationBaseRoles = $organizationsBaseRoles;
        }

        return $this->organizationBaseRoles;
    }

    /**
     * @param Request $request
     * @return OrganizationBaseRoleEntity[]
     */
    public function getAllActiveBaseOrganizationRoles(Request $request)
    {
        $activeOrganizationBaseRoles = [];

        foreach ($this->getAllOrganizationBaseRoles($request) as $organizationBaseRole) {
            if ($organizationBaseRole->is_active())
                $activeOrganizationBaseRoles[$organizationBaseRole->getPk()] = $organizationBaseRole;
        }

        return $activeOrganizationBaseRoles;
    }

    /**
     * @param Request $request
     * @return OrganizationBaseRoleEntity[]
     */
    public function getAllActivePrimaryOriganizationBaseRoles(Request $request)
    {
        $activePrimaryBaseRoles = [];

        foreach ($this->getAllOrganizationBaseRoles($request) as $organizationBaseRole) {
            if ($organizationBaseRole->is_active() && $organizationBaseRole->is_primary())
                $activePrimaryBaseRoles[$organizationBaseRole->getPk()] = $organizationBaseRole;
        }

        return $activePrimaryBaseRoles;
    }

    /**
     * @param Request $request
     * @param $organizationBaseRoleId
     * @return OrganizationBaseRoleEntity
     */
    public function getOrganizationBaseRoleById(Request $request, $organizationBaseRoleId)
    {
        return $this->getAllActiveBaseOrganizationRoles($request)[$organizationBaseRoleId];
    }
}

class OrganizationsBaseRightsManager extends BaseEntityManager
{
    protected $entityClass = OrganizationBaseRightEntity::class;
    protected $table = Table::OrganizationBaseRight;
    protected $table_alias = TableAlias::OrganizationBaseRight;
    protected $pk = DBField::ORGANIZATION_BASE_RIGHT_ID;

    /** @var OrganizationBaseRightEntity[] */
    protected $organizationBaseRights = [];

    const GNS_KEY_PREFIX = GNS_ROOT . '.organizations-base-rights';

    const RIGHT_ORG_ROLES = 'organization.roles';
    const RIGHT_ORG_PERMISSIONS = 'organization.permissions';
    const RIGHT_ORG_PROFILE = 'organization.profile';
    const RIGHT_ORG_MEMBERS = 'organization.members';
    const RIGHT_ORG_MEMBERS_ROLES = 'organization.members.roles';
    const RIGHT_ORG_BILLING_PAYMENTS = 'billing.payments';
    const RIGHT_ORG_BILLING_PAYOUTS = 'billing.payouts';
    const RIGHT_ORG_MODS_PROFILE = 'mods.profile';
    const RIGHT_ORG_MODS_BUILDS_LIVE = 'mods.channels.live';
    const RIGHT_ORG_MODS_BUILDS_DEV = 'mods.channels.dev';

    const RIGHT_ORG_GAMES_PROFILE = 'games.profile';
    const RIGHT_ORG_GAMES_ANALYTICS = 'games.analytics';
    const RIGHT_ORG_GAMES_CHANNELS_LIVE = 'games.channels.live';
    const RIGHT_ORG_GAMES_CHANNELS_DEV = 'games.channels.dev';
    const RIGHT_ORG_HOSTS = 'hosts';
    const RIGHT_ORG_HOSTS_INSTANCES_PROD = 'hosts.instances.prod';
    const RIGHT_ORG_HOSTS_INSTANCES_TEST = 'hosts.instances.test';

    public static $fields = [
        DBField::ORGANIZATION_BASE_RIGHT_ID,
        DBField::NAME,
        DBField::DISPLAY_NAME,
        DBField::DESCRIPTION,
        DBField::RIGHT_ID,
        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        RightsManager::class => DBField::RIGHT_ID
    ];

    /**
     * @param OrganizationBaseRightEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::EDIT_URL, $request->getWwwUrl("/admin/organizations/base-rights/edit/{$data->getPk()}"));
    }

    /**
     * @param Request $request
     * @param OrganizationBaseRightEntity $organizationBaseRights
     */
    public function postProcessOrganizationBaseRights(Request $request, $organizationBaseRights)
    {
        $rightsManager = $request->managers->rights();

        if ($organizationBaseRights) {
            if ($organizationBaseRights instanceof OrganizationBaseRightEntity)
                $organizationBaseRights = [$organizationBaseRights];

            /** @var OrganizationBaseRightEntity[] $organizationBaseRights */

            foreach ($organizationBaseRights as $organizationBaseRight) {
                $right = $rightsManager->getRightById($request, $organizationBaseRight->getRightId());
                $organizationBaseRight->setRight($right);
            }
        }
    }


    /**
     * @param Request $request
     * @return array|OrganizationBaseRightEntity[]
     */
    public function getAllOrganizationBaseRights(Request $request)
    {
        $rightsManager = $request->managers->rights();

        if (!$this->organizationBaseRights) {
            /** @var OrganizationBaseRightEntity[] $organizationBaseRights */
            $organizationBaseRights = $this->query($request->db)
                ->fields($this->selectAliasedManagerFields($rightsManager))
                ->left_join($rightsManager)
                ->get_entities($request);

            if ($organizationBaseRights)
                $organizationBaseRights = array_index($organizationBaseRights, $this->getPkField());

            $this->organizationBaseRights = $organizationBaseRights;
        }

        return $this->organizationBaseRights;
    }

    /**
     * @param Request $request
     * @return OrganizationBaseRightEntity[]
     */
    public function getAllActiveOrganizationBaseRights(Request $request)
    {
        $activeOrganizationBaseRights = [];
        foreach ($this->getAllOrganizationBaseRights($request) as $organizationBaseRight) {
            if ($organizationBaseRight->is_active())
                $activeOrganizationBaseRights[$organizationBaseRight->getPk()] = $organizationBaseRight;
        }
        return $activeOrganizationBaseRights;
    }

    /**
     * @param Request $request
     * @param $organizationBaseRightId
     * @return OrganizationBaseRightEntity
     */
    public function getOrganizationBaseRightById(Request $request, $organizationBaseRightId)
    {
        if (!$this->organizationBaseRights)
            $this->getAllOrganizationBaseRights($request);

        return $this->organizationBaseRights[$organizationBaseRightId];
    }
}

class OrganizationsBasePermissionsManager extends BaseEntityManager
{
    protected $entityClass = OrganizationBasePermissionEntity::class;
    protected $table = Table::OrganizationBasePermission;
    protected $table_alias = TableAlias::OrganizationBasePermission;
    protected $pk = DBField::ORGANIZATION_BASE_PERMISSION_ID;

    const GNS_KEY_PREFIX = GNS_ROOT . '.organizations-base-permissions';

    public static $fields = [
        DBField::ORGANIZATION_BASE_PERMISSION_ID,
        DBField::ORGANIZATION_BASE_ROLE_ID,
        DBField::ORGANIZATION_BASE_RIGHT_ID,
        DBField::ACCESS_LEVEL,
        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        OrganizationsBaseRolesManager::class => DBField::ORGANIZATION_BASE_ROLE_ID,
        OrganizationsBaseRightsManager::class => DBField::ORGANIZATION_BASE_RIGHT_ID,
    ];

    /**
     * @param Request $request
     * @return SqlQuery
     */
    public function queryJoinBaseRoles(Request $request, $primaryOnly = true)
    {
        $organizationBaseRolesManager = $request->managers->organizationsBaseRoles();

        $queryBuilder = $this->query($request->db)
            ->inner_join($organizationBaseRolesManager)
            ->filter($organizationBaseRolesManager->filters->isActive());

        if ($primaryOnly)
            $queryBuilder->filter($organizationBaseRolesManager->filters->isPrimary());

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @return SqlQuery
     */
    public function queryJoinBaseRights(Request $request)
    {
        $organizationBaseRightsManager = $request->managers->organizationsBaseRights();

        $queryBuilder = $this->query($request->db)
            ->inner_join($organizationBaseRightsManager)
            ->filter($organizationBaseRightsManager->filters->isActive());

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @return OrganizationBasePermissionEntity[]
     */
    public function getAllOrganizationBasePermissions(Request $request)
    {
        $organizationsBaseRolesManager = $request->managers->organizationsBaseRoles();
        $organizationsBaseRightsManager = $request->managers->organizationsBaseRights();

        /** @var OrganizationBasePermissionEntity[] $organizationBasePermissions */
        $organizationBasePermissions = $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($organizationsBaseRolesManager, $organizationsBaseRightsManager))
            ->inner_join($organizationsBaseRightsManager)
            ->inner_join($organizationsBaseRolesManager)
            ->get_entities($request);

        return $organizationBasePermissions;
    }

    /**
     * @param Request $request
     * @return OrganizationBasePermissionEntity[]
     */
    public function getAllPrimaryOrganizationBasePermissions(Request $request)
    {
        $organizationsBaseRolesManager = $request->managers->organizationsBaseRoles();
        $organizationsBaseRightsManager = $request->managers->organizationsBaseRights();

        /** @var OrganizationBasePermissionEntity[] $organizationBasePermissions */
        $organizationBasePermissions = $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($organizationsBaseRolesManager, $organizationsBaseRightsManager))
            ->inner_join($organizationsBaseRightsManager)
            ->inner_join($organizationsBaseRolesManager)
            ->filter($this->filters->NotEq(DBField::ORGANIZATION_BASE_ROLE_ID, OrganizationsBaseRolesManager::ID_CUSTOM))
            ->get_entities($request);

        return $organizationBasePermissions;
    }


    /**
     * @param Request $request
     * @param OrganizationBaseRightEntity[]|OrganizationBaseRightEntity $organizationBaseRights
     */
    public function postProcessPermissionsForOrganizationBaseRights(Request $request, $organizationBaseRights)
    {
        if ($organizationBaseRights) {

            if ($organizationBaseRights instanceof OrganizationBaseRightEntity)
                $organizationBaseRights = [$organizationBaseRights];

            $organizationBaseRightIds = extract_pks($organizationBaseRights);

            $data = [];

            /** @var OrganizationBasePermissionEntity[] $adminPermissions */
            $adminPermissions = $this->queryJoinBaseRoles($request)
                ->filter($this->filters->byOrganizationBaseRightId($organizationBaseRightIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::ADMINISTER)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($adminPermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationBaseRightId(), $data))
                    $data[$permission->getOrganizationBaseRightId()] = [];

                $data[$permission->getOrganizationBaseRightId()][VField::A_RIGHTS][$permission->getOrganizationBaseRoleId()] = $permission;
            }

            /** @var OrganizationBasePermissionEntity[] $moderatePermissions */
            $moderatePermissions = $this->queryJoinBaseRoles($request)
                ->filter($this->filters->byOrganizationBaseRightId($organizationBaseRightIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::MODERATE)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($moderatePermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationBaseRightId(), $data))
                    $data[$permission->getOrganizationBaseRightId()] = [];

                $data[$permission->getOrganizationBaseRightId()][VField::M_RIGHTS][$permission->getOrganizationBaseRoleId()] = $permission;
            }

            /** @var OrganizationBasePermissionEntity[] $usePermissions */
            $usePermissions = $this->queryJoinBaseRoles($request)
                ->filter($this->filters->byOrganizationBaseRightId($organizationBaseRightIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::USE)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($usePermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationBaseRightId(), $data))
                    $data[$permission->getOrganizationBaseRightId()] = [];

                $data[$permission->getOrganizationBaseRightId()][VField::U_RIGHTS][$permission->getOrganizationBaseRoleId()] = $permission;
            }

            foreach ($organizationBaseRights as $organizationBaseRight) {
                if (array_key_exists($organizationBaseRight->getPk(), $data))
                    $permissions = $data[$organizationBaseRight->getPk()];
                else
                    $permissions = [];

                $organizationBaseRight->updateField(VField::PERMISSIONS, $permissions);
            }

        }
    }

    /**
     * @param Request $request
     * @param OrganizationBaseRoleEntity|OrganizationBaseRoleEntity[] $organizationBaseRoles
     */
    public function postProcessPermissionsForOrganizationBaseRoles(Request $request, $organizationBaseRoles)
    {
        if ($organizationBaseRoles) {

            if ($organizationBaseRoles instanceof OrganizationBaseRoleEntity)
                $organizationBaseRoles = [$organizationBaseRoles];

            $organizationBaseRoleIds = extract_pks($organizationBaseRoles);

            $data = [];

            /** @var OrganizationBasePermissionEntity[] $adminPermissions */
            $adminPermissions = $this->queryJoinBaseRights($request)
                ->filter($this->filters->byOrganizationBaseRoleId($organizationBaseRoleIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::ADMINISTER)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($adminPermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationBaseRoleId(), $data))
                    $data[$permission->getOrganizationBaseRoleId()] = [];

                $data[$permission->getOrganizationBaseRoleId()][VField::A_RIGHTS][$permission->getOrganizationBaseRightId()] = $permission;
            }

            /** @var OrganizationBasePermissionEntity[] $moderatePermissions */
            $moderatePermissions = $this->queryJoinBaseRoles($request)
                ->filter($this->filters->byOrganizationBaseRoleId($organizationBaseRoleIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::MODERATE)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($moderatePermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationBaseRoleId(), $data))
                    $data[$permission->getOrganizationBaseRoleId()] = [];

                $data[$permission->getOrganizationBaseRoleId()][VField::M_RIGHTS][$permission->getOrganizationBaseRightId()] = $permission;
            }

            /** @var OrganizationBasePermissionEntity[] $usePermissions */
            $usePermissions = $this->queryJoinBaseRoles($request)
                ->filter($this->filters->byOrganizationBaseRoleId($organizationBaseRoleIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::USE)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($usePermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationBaseRoleId(), $data))
                    $data[$permission->getOrganizationBaseRoleId()] = [];

                $data[$permission->getOrganizationBaseRoleId()][VField::U_RIGHTS][$permission->getOrganizationBaseRightId()] = $permission;
            }

            foreach ($organizationBaseRoles as $organizationBaseRole) {
                if (array_key_exists($organizationBaseRole->getPk(), $data))
                    $permissions = $data[$organizationBaseRole->getPk()];
                else
                    $permissions = [];

                $organizationBaseRole->updateField(VField::PERMISSIONS, $permissions);
            }
        }
    }
}


class OrganizationsRolesManager extends BaseEntityManager {

    protected $entityClass = OrganizationRoleEntity::class;
    protected $table = Table::OrganizationRole;
    protected $table_alias = TableAlias::OrganizationRole;
    protected $pk = DBField::ORGANIZATION_ROLE_ID;

    public static $fields = [
        DBField::ORGANIZATION_ROLE_ID,
        DBField::ORGANIZATION_ID,
        DBField::ORGANIZATION_BASE_ROLE_ID,
        DBField::DISPLAY_NAME,
        DBField::DESCRIPTION,

        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    public $foreign_managers = [
        OrganizationsManager::class => DBField::ORGANIZATION_ID,
        OrganizationsBaseRolesManager::class => DBField::ORGANIZATION_BASE_ROLE_ID,
    ];

    /**
     * @param OrganizationRoleEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $url = $request->getDevelopUrl("/teams/{$data->getOrganizationSlug()}/role/{$data->getPk()}?organization_id={$data->getOrganizationId()}");
        $data->updateField(VField::EDIT_URL, $url);
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    protected function queryJoinOrganizations(Request $request)
    {
        $organizationsManager = $request->managers->organizations();

        $fields = $this->createDBFields();
        $fields[] = $organizationsManager->aliasField(DBField::SLUG, VField::ORGANIZATION_SLUG);

        return $this->query($request->db)
            ->fields($fields)
            ->inner_join($organizationsManager);
    }

    /**
     * @param Request $request
     * @param OrganizationBaseRoleEntity $organizationBaseRole
     * @param $organizationId
     * @return OrganizationRoleEntity
     */
    public function createNewOrganizationRole(Request $request, OrganizationBaseRoleEntity $organizationBaseRole,
                                              $organizationId, $description = null, $organizationSlug = null)
    {
        $data = [
            DBField::ORGANIZATION_ID => $organizationId,
            DBField::ORGANIZATION_BASE_ROLE_ID => $organizationBaseRole->getPk(),
            DBField::DISPLAY_NAME => $organizationBaseRole->getDisplayName(),
            DBField::DESCRIPTION => $description,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId,
            VField::ORGANIZATION_SLUG => $organizationSlug
        ];

        /** @var OrganizationRoleEntity $organizationRole */
        $organizationRole = $this->query($request->db)->createNewEntity($request, $data, false);

        $organizationRole->setOrganizationBaseRole($organizationBaseRole);

        return $organizationRole;
    }

    /**
     * @param Request $request
     * @param OrganizationRoleEntity[]|OrganizationRoleEntity $organizationRoles
     */
    protected function postProcessOrganizationRoles(Request $request, $organizationRoles = [])
    {
        $organizationBaseRolesManager = $request->managers->organizationsBaseRoles();

        if ($organizationRoles) {
            if ($organizationRoles instanceof OrganizationRoleEntity)
                $organizationRoles = [$organizationRoles];

            /** @var OrganizationRoleEntity[] $organizationRoles */
            $organizationRoles = array_index($organizationRoles, $this->getPkField());

            $organizationBaseRoles = $organizationBaseRolesManager->getAllOrganizationBaseRoles($request);

            foreach ($organizationRoles as $organizationRole) {
                $organizationBaseRole = $organizationBaseRoles[$organizationRole->getOrganizationBaseRoleId()];

                $organizationRole->setOrganizationBaseRole($organizationBaseRole);
            }
        }
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return OrganizationRoleEntity[]
     */
    public function getOrganizationRolesByOrganizationId(Request $request, $organizationId)
    {
        $organizationRoles = $this->queryJoinOrganizations($request)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->sort_asc($this->field(DBField::ORGANIZATION_BASE_ROLE_ID))
            ->get_entities($request);

        $this->postProcessOrganizationRoles($request, $organizationRoles);

        return $organizationRoles;
    }
}


class OrganizationsRightsManager extends BaseEntityManager {

    protected $entityClass = OrganizationRightEntity::class;
    protected $table = Table::OrganizationRight;
    protected $table_alias = TableAlias::OrganizationRight;
    protected $pk = DBField::ORGANIZATION_RIGHT_ID;

    public static $fields = [
        DBField::ORGANIZATION_RIGHT_ID,
        DBField::ORGANIZATION_ID,
        DBField::ORGANIZATION_BASE_RIGHT_ID,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        OrganizationsManager::class => DBField::ORGANIZATION_ID,
        OrganizationsBaseRightsManager::class => DBField::ORGANIZATION_BASE_RIGHT_ID
    ];

    /**
     * @param OrganizationRightEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if ($data->hasField(DBField::DESCRIPTION))
            $data->updateField(VField::PARSED_DESCRIPTION, parse_post($data->getDescription()));

        $url = $request->getDevelopUrl("/account/edit-right/{$data->getPk()}?organization_id={$data->getOrganizationId()}");
        $data->updateField(VField::EDIT_URL, $url);
    }

    /**
     * @param Request $request
     * @return SqlQuery
     */
    protected function queryJoinBaseRights(Request $request)
    {
        $organizationsBaseRightsManager = $request->managers->organizationsBaseRights();

        $fields = array_merge($this->createDBFields(),
            [
                $organizationsBaseRightsManager->field(DBField::NAME),
                $organizationsBaseRightsManager->field(DBField::DISPLAY_NAME),
                $organizationsBaseRightsManager->field(DBField::DESCRIPTION)
            ]
        );

        return $this->query($request->db)
            ->fields($fields)
            ->inner_join($organizationsBaseRightsManager)
            ->filter($organizationsBaseRightsManager->filters->isActive());
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param $organizationBaseRightId
     * @return OrganizationRightEntity
     */
    public function createNewOrganizationRight(Request $request, OrganizationEntity $organization, OrganizationBaseRightEntity $organizationBaseRight)
    {
        $data = [
            DBField::ORGANIZATION_ID => $organization->getPk(),
            DBField::ORGANIZATION_BASE_RIGHT_ID => $organizationBaseRight->getPk(),
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var OrganizationRightEntity $organizationRight */
        $organizationRight = $this->query($request->db)->createNewEntity($request, $data, false);

        $organizationRight->setOrganizationBaseRight($organizationBaseRight);

        $extraFields = [
            DBField::NAME => $organizationBaseRight->getName(),
            DBField::DISPLAY_NAME => $organizationBaseRight->getDisplayName(),
            DBField::DESCRIPTION => $organizationBaseRight->getDescription()
        ];

        $organizationRight->assign($extraFields);

        return $organizationRight;
    }


    /**
     * @param Request $request
     * @param $organizationId
     * @param bool $inlucePermissions
     * @return OrganizationRightEntity[]
     */
    public function getAllOrganizationRightsByOrganizationId(Request $request, $organizationId, $inlucePermissions = false)
    {
        $organizationsBaseRightsManager = $request->managers->organizationsBaseRights();
        $organizationsPermissionsManager = $request->managers->organizationsPermissions();

        /** @var OrganizationRightEntity[] $organizationRights */
        $organizationRights = $this->queryJoinBaseRights($request)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->get_entities($request);

        foreach ($organizationRights as $organizationRight) {
            $organizationBaseRight = $organizationsBaseRightsManager->getOrganizationBaseRightById($request, $organizationRight->getOrganizationBaseRightId());
            $organizationRight->setOrganizationBaseRight($organizationBaseRight);
        }

        if ($inlucePermissions)
            $organizationsPermissionsManager->postProcessPermissionsForOrganizationRights($request, $organizationRights);

        return $organizationRights;
    }

    /**
     * @param Request $request
     * @param $organizationRightId
     * @return OrganizationRightEntity
     */
    public function getOrganizationRightById(Request $request, $organizationRightId)
    {
        /** @var OrganizationRightEntity $organizationRights */
        $organizationRights = $this->queryJoinBaseRights($request)
            ->filter($this->filters->byPk($organizationRightId))
            ->get_entity($request);

        return $organizationRights;
    }
}

class OrganizationsPermissionsManager extends BaseEntityManager {

    protected $entityClass = OrganizationPermissionEntity::class;
    protected $table = Table::OrganizationPermission;
    protected $table_alias = TableAlias::OrganizationPermission;
    protected $pk = DBField::ORGANIZATION_PERMISSION_ID;

    public static $fields = [
        DBField::ORGANIZATION_PERMISSION_ID,
        DBField::ORGANIZATION_ID,
        DBField::ORGANIZATION_RIGHT_ID,
        DBField::ORGANIZATION_ROLE_ID,
        DBField::ORGANIZATION_BASE_PERMISSION_ID,
        DBField::ACCESS_LEVEL,

        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        OrganizationsManager::class => DBField::ORGANIZATION_ID,
        OrganizationsRightsManager::class => DBField::ORGANIZATION_RIGHT_ID,
        OrganizationsRolesManager::class => DBField::ORGANIZATION_ROLE_ID,
        OrganizationsBasePermissionsManager::class => DBField::ORGANIZATION_BASE_PERMISSION_ID,
    ];

    /**
     * @param OrganizationPermissionEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {

    }


    /**
     * @param Request $request
     * @return SqlQuery
     */
    public function queryJoinRoles(Request $request)
    {
        $organizationRolesManager = $request->managers->organizationsRoles();

        $queryBuilder = $this->query($request->db)
            ->inner_join($organizationRolesManager)
            ->filter($organizationRolesManager->filters->isActive());

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param $organizationRightId
     * @param $organizationRoleId
     * @param $organizationBasePermissionId
     * @param $accessLevel
     * @param int $isActive
     * @return OrganizationPermissionEntity
     */
    public function createNewOrganizationPermission(Request $request, $organizationId, $organizationRightId, $organizationRoleId,
                                                    $organizationBasePermissionId, $accessLevel, $isActive = 1)
    {
        $data = [
            DBField::ORGANIZATION_ID => $organizationId,
            DBField::ORGANIZATION_RIGHT_ID => $organizationRightId,
            DBField::ORGANIZATION_ROLE_ID => $organizationRoleId,
            DBField::ORGANIZATION_BASE_PERMISSION_ID => $organizationBasePermissionId,
            DBField::ACCESS_LEVEL => $accessLevel,
            DBField::IS_ACTIVE => $isActive,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::CREATED_BY => $request->requestId,
        ];

        /** @var OrganizationPermissionEntity $organizationPermission */
        $organizationPermission = $this->query($request->db)->createNewEntity($request, $data);

        return $organizationPermission;
    }

    /**
     * @param Request $request
     * @return SqlQuery
     */
    public function queryJoinRights(Request $request)
    {
        $organizationRightsManager = $request->managers->organizationsRights();

        $queryBuilder = $this->query($request->db)
            ->inner_join($organizationRightsManager)
            ->filter($organizationRightsManager->filters->isActive());

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param $organizationIds
     * @return OrganizationPermissionEntity[]
     */
    public function getAllOrganizationPermissionsForOrganizationIds(Request $request, $organizationIds)
    {
        /** @var OrganizationPermissionEntity[] $organizationPermissions */
        $organizationPermissions = $this->query($request->db)
            ->filter($this->filters->byOrganizationId($organizationIds))
            ->sort_asc($this->field(DBField::ORGANIZATION_ID))
            ->sort_asc($this->field(DBField::ORGANIZATION_ROLE_ID))
            ->get_entities($request);

        return $organizationPermissions;
    }


    /**
     * @param Request $request
     * @param OrganizationRightEntity[]|OrganizationRightEntity $organizationRights
     */
    public function postProcessPermissionsForOrganizationRights(Request $request, $organizationRights)
    {
        if ($organizationRights) {

            if ($organizationRights instanceof OrganizationRightEntity)
                $organizationRights = [$organizationRights];

            $organizationBaseRightIds = extract_pks($organizationRights);

            $data = [];

            /** @var OrganizationPermissionEntity[] $adminPermissions */
            $adminPermissions = $this->queryJoinRoles($request)
                ->filter($this->filters->byOrganizationRightId($organizationBaseRightIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::ADMINISTER)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($adminPermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationRightId(), $data))
                    $data[$permission->getOrganizationRightId()] = [];

                $data[$permission->getOrganizationRightId()][VField::A_RIGHTS][$permission->getOrganizationRoleId()] = $permission;
            }

            /** @var OrganizationPermissionEntity[] $moderatePermissions */
            $moderatePermissions = $this->queryJoinRoles($request)
                ->filter($this->filters->byOrganizationRightId($organizationBaseRightIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::MODERATE)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($moderatePermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationRightId(), $data))
                    $data[$permission->getOrganizationRightId()] = [];

                $data[$permission->getOrganizationRightId()][VField::M_RIGHTS][$permission->getOrganizationRoleId()] = $permission;
            }

            /** @var OrganizationPermissionEntity[] $usePermissions */
            $usePermissions = $this->queryJoinRoles($request)
                ->filter($this->filters->byOrganizationRightId($organizationBaseRightIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::USE)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($usePermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationRightId(), $data))
                    $data[$permission->getOrganizationRightId()] = [];

                $data[$permission->getOrganizationRightId()][VField::U_RIGHTS][$permission->getOrganizationRoleId()] = $permission;
            }

            foreach ($organizationRights as $organizationRight) {
                if (array_key_exists($organizationRight->getPk(), $data))
                    $permissions = $data[$organizationRight->getPk()];
                else
                    $permissions = [];

                $organizationRight->updateField(VField::PERMISSIONS, $permissions);
            }

        }
    }

    /**
     * @param Request $request
     * @param OrganizationRoleEntity|OrganizationRoleEntity[] $organizationRoles
     */
    public function postProcessPermissionsForOrganizationRoles(Request $request, $organizationRoles)
    {
        if ($organizationRoles) {

            if ($organizationRoles instanceof OrganizationBaseRoleEntity)
                $organizationRoles = [$organizationRoles];

            $organizationRoleIds = extract_pks($organizationRoles);

            $data = [];

            /** @var OrganizationPermissionEntity[] $adminPermissions */
            $adminPermissions = $this->queryJoinRights($request)
                ->filter($this->filters->byOrganizationBaseRoleId($organizationRoleIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::ADMINISTER)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($adminPermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationRoleId(), $data))
                    $data[$permission->getOrganizationRoleId()] = [];

                $data[$permission->getOrganizationRoleId()][VField::A_RIGHTS][$permission->getOrganizationRightId()] = $permission;
            }

            /** @var OrganizationPermissionEntity[] $moderatePermissions */
            $moderatePermissions = $this->queryJoinRoles($request)
                ->filter($this->filters->byOrganizationBaseRoleId($organizationRoleIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::MODERATE)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($moderatePermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationRoleId(), $data))
                    $data[$permission->getOrganizationRoleId()] = [];

                $data[$permission->getOrganizationRoleId()][VField::M_RIGHTS][$permission->getOrganizationRightId()] = $permission;
            }

            /** @var OrganizationPermissionEntity[] $usePermissions */
            $usePermissions = $this->queryJoinRoles($request)
                ->filter($this->filters->byOrganizationBaseRoleId($organizationRoleIds))
                ->filter($this->filters->BitAnd(DBField::ACCESS_LEVEL, Rights::getAccessLevel(Rights::USE)))
                ->filter($this->filters->isActive())
                ->get_entities($request);

            foreach ($usePermissions as $permission) {
                if (!array_key_exists($permission->getOrganizationRoleId(), $data))
                    $data[$permission->getOrganizationRoleId()] = [];

                $data[$permission->getOrganizationRoleId()][VField::U_RIGHTS][$permission->getOrganizationRightId()] = $permission;
            }

            foreach ($organizationRoles as $organizationRole) {
                if (array_key_exists($organizationRole->getPk(), $data))
                    $permissions = $data[$organizationRole->getPk()];
                else
                    $permissions = [];

                $organizationRole->updateField(VField::PERMISSIONS, $permissions);
            }
        }
    }
}


class OrganizationsUsersManager extends BaseEntityManager {

    protected $entityClass = OrganizationUserEntity::class;
    protected $table = Table::OrganizationUser;
    protected $table_alias = TableAlias::OrganizationUser;
    protected $pk = DBField::ORGANIZATION_USER_ID;

    public static $fields = [
        DBField::ORGANIZATION_USER_ID,
        DBField::ORGANIZATION_ID,
        DBField::ORGANIZATION_ROLE_ID,
        DBField::ORGANIZATION_USER_STATUS_ID,
        DBField::USER_ID,
        DBField::DISPLAY_NAME,

        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param OrganizationUserEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::USER))
            $data->updateField(VField::USER, []);

        $editUrl = $request->getDevelopUrl("/teams/edit-member/{$data->getPk()}?organization_id={$data->getOrganizationId()}");
        $data->updateField(VField::EDIT_URL, $editUrl);
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param $organizationId
     * @return OrganizationUserEntity
     */
    public function createNewOrganizationUser(Request $request, UserEntity $user = null, $displayName = null, $organizationId, $organizationRoleId, $organizationUserStatusId = OrganizationsUsersStatusesManager::ID_INVITED)
    {
        $data = [
            DBField::ORGANIZATION_ID => $organizationId,
            DBField::ORGANIZATION_ROLE_ID => $organizationRoleId,
            DBField::ORGANIZATION_USER_STATUS_ID => $organizationUserStatusId,
            DBField::USER_ID => $user ? $user->getPk() : null,
            DBField::DISPLAY_NAME => $displayName,
            DBField::IS_ACTIVE => 1,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var OrganizationUserEntity $organizationUser */
        $organizationUser = $this->query($request->db)->createNewEntity($request, $data, false);

        if ($user)
            $organizationUser->setUser($user);

        return $organizationUser;
    }

    /**
     * @param Request $request
     * @param OrganizationUserEntity[]|OrganizationUserEntity $organizationUsers
     */
    protected function postProcessOrganizationUsers(Request $request, $organizationUsers = [])
    {
        $usersManager = $request->managers->users();
        $organizationsUsersStatusesManager = $request->managers->organizationsUsersStatuses();

        if ($organizationUsers) {

            if ($organizationUsers instanceof OrganizationUserEntity)
                $organizationUsers = [$organizationUsers];

            /** @var OrganizationUserEntity[] $organizationUsers */
            $organizationUsers = array_index($organizationUsers, $this->getPkField());

            $organizationUserStatuses = $organizationsUsersStatusesManager->getAllOrganizationUserStatuses($request);

            $userIds = unique_array_extract(DBField::USER_ID, $organizationUsers);
            $users = $usersManager->getUsersByIds($request, $userIds);

            foreach($organizationUsers as $organizationUser) {

                $organizationUser->setOrganizationUserStatus($organizationUserStatuses[$organizationUser->getOrganizationUserStatusId()]);

                if ($organizationUser->getUserId())
                    $organizationUser->setUser($users[$organizationUser->getUserId()]);
            }
        }
    }

    /**
     * @param Request $request
     * @param $organizationUserId
     * @return array|OrganizationUserEntity
     */
    public function getActiveOrganzationUserById(Request $request, $organizationUserId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($organizationUserId))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return OrganizationUserEntity[]
     */
    public function getInvitedOrganizationUsersByOrganizationId(Request $request, $organizationId)
    {
        $organizationsUsersInvitesManager = $request->managers->organizationsUsersInvites();
        $joinLimitDt = new DateTime($request->getCurrentSqlTime());
        $joinOrganizationsUsersInvitesFilter = $this->filters->And_(
            $organizationsUsersInvitesManager->filters->byOrganizationUserId($this->createPkField()),
            $organizationsUsersInvitesManager->filters->isActive(),
            $organizationsUsersInvitesManager->filters->createdBefore($joinLimitDt->modify("-1 day")->format(SQL_DATETIME))
        );

        $fields = $this->createDBFields();
        $fields[] = $organizationsUsersInvitesManager->aliasField(DBField::DISPLAY_NAME, 'invite_display_name');
        $fields[] = $organizationsUsersInvitesManager->aliasField(DBField::EMAIL_ADDRESS, 'invite_email_address');

        /** @var OrganizationUserEntity[] $organizationUsers */
        $organizationUsers = $this->query($request->db)
            ->fields($fields)
            ->inner_join($organizationsUsersInvitesManager, $joinOrganizationsUsersInvitesFilter)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($this->filters->byOrganizationUserStatusId(OrganizationsUsersStatusesManager::ID_INVITED))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        $this->postProcessOrganizationUsers($request, $organizationUsers);

        return $organizationUsers;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return OrganizationUserEntity[]
     */
    public function getOrganizationUsersByOrganizationId(Request $request, $organizationId, $expand = false)
    {
        $organizationUserStatusIds = [
            OrganizationsUsersStatusesManager::ID_ACTIVE,
            OrganizationsUsersStatusesManager::ID_INVITED
        ];
        /** @var OrganizationUserEntity[] $organizationUsers */
        $organizationUsers = $this->query($request->db)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($this->filters->byOrganizationUserStatusId($organizationUserStatusIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessOrganizationUsers($request, $organizationUsers);

        return $organizationUsers;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param bool $expand
     * @return OrganizationUserEntity[]
     */
    public function getActiveOrganizationUsersByOrganizationId(Request $request, $organizationId, $expand = false)
    {

        $organizationUserStatusIds = [
            OrganizationsUsersStatusesManager::ID_ACTIVE,
            //OrganizationsUsersStatusesManager::ID_INVITED
        ];
        /** @var OrganizationUserEntity[] $organizationUsers */
        $organizationUsers = $this->query($request->db)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($this->filters->byOrganizationUserStatusId($organizationUserStatusIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($expand)
            $this->postProcessOrganizationUsers($request, $organizationUsers);

        return $organizationUsers;
    }

    /**
     * @param Request $request
     * @param $organizationUserIds
     * @param bool $expand
     * @return OrganizationUserEntity[]
     */
    public function getOrganizationUsersByIds(Request $request, $organizationUserIds, $expand = false)
    {
        /** @var OrganizationUserEntity[] $organizationUsers */
        $organizationUsers = $this->query($request)
            ->filter($this->filters->byPk($organizationUserIds))
            ->get_entities($request);

        if ($expand)
            $this->postProcessOrganizationUsers($request, $organizationUsers);

        return $organizationUsers;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param $userId
     * @param bool $expand
     * @return OrganizationUserEntity
     */
    public function getActiveOrganizationUserByUserId(Request $request, $organizationId, $userId, $expand = false)
    {
        /** @var OrganizationUserEntity $organizationUser */
        $organizationUser = $this->query($request->db)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($this->filters->byUserId($userId))
            ->filter($this->filters->isActive())
            ->get_entity($request);

        if ($expand)
            $this->postProcessOrganizationUsers($request, $organizationUser);

        return $organizationUser;
    }

    /**
     * @param Request $request
     * @param OrganizationEntity $organization
     * @param UserEntity $adminUser
     * @param $emailAddress
     * @param $roleId
     */
    public function inviteUserToTeam(Request $request, OrganizationEntity $organization, UserEntity $adminUser, $emailAddress, $roleId, $displayName = null)
    {
        $usersManager = $request->managers->users();
        $activityManager = $request->managers->activity();
        $organizationsUsersInvitesManager = $request->managers->organizationsUsersInvites();
        $emailTrackingManager = $request->managers->emailTracking();

        $inviteUser = $usersManager->getUserByEmailAddress($request, $emailAddress);

        if (!$displayName)
            $displayName = $inviteUser ? $inviteUser->getDisplayName() : null;

        $organizationUser = $this->createNewOrganizationUser(
            $request,
            $inviteUser ? $inviteUser : null,
            $displayName,
            $organization->getPk(),
            $roleId,
            OrganizationsUsersStatusesManager::ID_INVITED
        );

        $organizationUserInvite = $organizationsUsersInvitesManager->createNewOrganizationUserInvite(
            $request,
            $organizationUser->getPk(),
            $emailAddress,
            $inviteUser ? $inviteUser->getDisplayName() : null
        );

        $activity = $activityManager->trackActivity(
            $request,
            ActivityTypesManager::ACTIVITY_TYPE_TEAM_USER_INVITE,
            $organizationUser->getOrganizationId(),
            $organizationUser->getPk(),
            $adminUser->getUiLanguageId(),
            $adminUser
        );

        Modules::load_helper(Helpers::EMAIL);

        $emailGenerator = new EmailGenerator(
            $request,
            $emailAddress,
            EmailTypesManager::TYPE_SYSTEM_TEAM_USER_INVITE,
            $emailTrackingManager->generateChecksum(),
            $activity->getPk()
        );

        $ctaUrl = $request->getWwwUrl("/auth/review-account");

        $queryParams = [
            GetRequest::PARAM_ORGANIZATION_USER_INVITE_TOKEN => $organizationUserInvite->getInviteHash()
        ];

        $queryString = $request->buildQuery($queryParams);

        $emailViewData = [
            TemplateVars::RECIPIENT => $inviteUser ? $inviteUser : null,
            TemplateVars::ORGANIZATION => $organization,
            TemplateVars::SENDER => $request->user->getEntity(),
            TemplateVars::CTA => $ctaUrl.$queryString,
            TemplateVars::ORGANIZATION_INVITE => $organizationUserInvite
        ];

        $emailGenerator->assignViewData($emailViewData);

        if ($inviteUser)
            $emailGenerator->setRecipientUser($inviteUser);

        $emailGenerator->sendEmail();
    }
}

class OrganizationsGamesLicensesManager extends BaseEntityManager
{
    protected $entityClass = OrganizationGameLicenseEntity::class;
    protected $table = Table::OrganizationGameLicense;
    protected $table_alias = TableAlias::OrganizationGameLicense;
    protected $pk = DBField::ORGANIZATION_GAME_LICENSE_ID;

    public static $fields = [
        DBField::ORGANIZATION_GAME_LICENSE_ID,
        DBField::ORGANIZATION_ID,
        DBField::GAME_ID,
        DBField::START_TIME,
        DBField::END_TIME,

        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @param OrganizationGameLicenseEntity[]|OrganizationGameLicenseEntity $organizationGameLicenses
     */
    public function postProcessOrganizationGameLicenses(Request $request, $organizationGameLicenses, $expand = false)
    {
        $gamesManager = $request->managers->games();

        if ($organizationGameLicenses) {
            if ($organizationGameLicenses instanceof OrganizationGameLicenseEntity)
                $organizationGameLicenses = [$organizationGameLicenses];

            $gameIds = unique_array_extract(DBField::GAME_ID, $organizationGameLicenses);
            $games = $gamesManager->getGamesByIds($request, $gameIds, $expand);
            /** @var GameEntity[] $games */
            $games = $gamesManager->index($games);

            foreach ($organizationGameLicenses as $organizationGameLicense) {
                $organizationGameLicense->setGame($games[$organizationGameLicense->getGameId()]);
            }
        }
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param $gameId
     * @param $startTime
     * @param null $endTime
     * @return OrganizationGameLicenseEntity
     */
    public function createNewOrganizationGameLicense(Request $request, $organizationId, $gameId, $startTime, $endTime = null)
    {
        $data = [
            DBField::ORGANIZATION_ID => $organizationId,
            DBField::GAME_ID => $gameId,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::IS_ACTIVE => 1,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var OrganizationGameLicenseEntity $organizationGameLicense */
        $organizationGameLicense = $this->query($request->db)->createNewEntity($request, $data);

        return $organizationGameLicense;
    }

    /**
     * @param $currentTime
     * @return AndFilter
     */
    public function getActiveLicenseFilter($currentTime)
    {
        return $this->filters->And_(
            $this->filters->isActive(),
            $this->filters->Lte(DBField::START_TIME, $currentTime),
            $this->filters->Or_(
                $this->filters->IsNull(DBField::END_TIME),
                $this->filters->Gte(DBField::END_TIME, $currentTime)
            )
        );
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return OrganizationGameLicenseEntity[]
     */
    public function getOrganizationGameLicensesByOrganizationId(Request $request, $organizationId, $expand = false)
    {
        /** @var OrganizationGameLicenseEntity[] $organizationGameLicenses */
        $organizationGameLicenses = $this->query($request->db)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($this->getActiveLicenseFilter($request->getCurrentSqlTime()))
            ->get_entities($request);

        $this->postProcessOrganizationGameLicenses($request, $organizationGameLicenses, $expand);

        return $organizationGameLicenses;
    }
}

class OrganizationsGamesModsLicensesManager extends BaseEntityManager
{
    protected $entityClass = OrganizationGameModLicenseEntity::class;
    protected $table = Table::OrganizationGameModLicense;
    protected $table_alias = TableAlias::OrganizationGameModLicense;
    protected $pk = DBField::ORGANIZATION_GAME_MOD_LICENSE_ID;

    public static $fields = [
        DBField::ORGANIZATION_GAME_MOD_LICENSE_ID,
        DBField::ORGANIZATION_ID,
        DBField::GAME_MOD_ID,
        DBField::START_TIME,
        DBField::END_TIME,

        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @param OrganizationGameModLicenseEntity[]|OrganizationGameModLicenseEntity $organizationGameModLicenses
     */
    public function postProcessOrganizationGameModLicenses(Request $request, $organizationGameModLicenses, $expand = false)
    {
        $gamesModsManager = $request->managers->gamesMods();

        if ($organizationGameModLicenses) {
            if ($organizationGameModLicenses instanceof OrganizationGameModLicenseEntity)
                $organizationGameModLicenses = [$organizationGameModLicenses];

            $gameModIds = unique_array_extract(DBField::GAME_MOD_ID, $organizationGameModLicenses);
            $gameMods = $gamesModsManager->getGameModsByIds($request, $gameModIds);
            /** @var GameModEntity[] $gameMods */
            $gameMods = $gamesModsManager->index($gameMods);

            foreach ($organizationGameModLicenses as $organizationGameModLicense) {
                $organizationGameModLicense->setGameMod($gameMods[$organizationGameModLicense->getGameModId()]);
            }
        }
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param $gameModId
     * @param $startTime
     * @param null $endTime
     * @return OrganizationGameModLicenseEntity
     */
    public function createNewOrganizationGameModLicense(Request $request, $organizationId, $gameModId, $startTime, $endTime = null)
    {
        $data = [
            DBField::ORGANIZATION_ID => $organizationId,
            DBField::GAME_MOD_ID => $gameModId,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::IS_ACTIVE => 1,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var OrganizationGameModLicenseEntity $organizationGameModLicense */
        $organizationGameModLicense = $this->query($request->db)->createNewEntity($request, $data);

        return $organizationGameModLicense;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return OrganizationGameModLicenseEntity[]
     */
    public function getOrganizationGameModLicensesByOrganizationId(Request $request, $organizationId, $expand = false)
    {
        $activeFilter = $this->filters->And_(
            $this->filters->isActive(),
            $this->filters->Lte(DBField::START_TIME, $request->getCurrentSqlTime()),
            $this->filters->Or_(
                $this->filters->IsNull(DBField::END_TIME),
                $this->filters->Gte(DBField::END_TIME, $request->getCurrentSqlTime())
            )
        );

        /** @var OrganizationGameModLicenseEntity[] $organizationGameModLicenses */
        $organizationGameModLicenses = $this->query($request->db)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($activeFilter)
            ->get_entities($request);

        $this->postProcessOrganizationGameModLicenses($request, $organizationGameModLicenses, $expand);

        return $organizationGameModLicenses;
    }
}

class OrganizationsUsersStatusesManager extends BaseEntityManager
{
    protected $entityClass = OrganizationUserStatusEntity::class;
    protected $table = Table::OrganizationUserStatus;
    protected $table_alias = TableAlias::OrganizationUserStatus;
    protected $pk = DBField::ORGANIZATION_USER_STATUS_ID;

    /** @var OrganizationUserStatusEntity[] $organizationUserStatuses */
    protected $organizationUserStatuses = [];

    const ID_INVITED = 1;
    const ID_ACTIVE = 2;
    const ID_DECLINED = 3;
    const ID_REMOVED = 4;

    public static $fields = [
        DBField::ORGANIZATION_USER_STATUS_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @return OrganizationUserStatusEntity[]
     */
    public function getAllOrganizationUserStatuses(Request $request)
    {
        if (!$this->organizationUserStatuses) {
            /** @var OrganizationUserStatusEntity[] $organizationUserStatuses */
            $organizationUserStatuses = $this->query($request->db)
                ->filter($this->filters->isActive())
                ->get_entities($request);

            $this->organizationUserStatuses = $this->index($organizationUserStatuses);
        }

        return $this->organizationUserStatuses;
    }
}

class OrganizationsUsersInvitesManager extends BaseEntityManager
{
    protected $entityClass = OrganizationUserInviteEntity::class;
    protected $table = Table::OrganizationUserInvite;
    protected $table_alias = TableAlias::OrganizationUserInvite;
    protected $pk = DBField::ORGANIZATION_USER_INVITE_ID;

    public static $fields = [
        DBField::ORGANIZATION_USER_INVITE_ID,
        DBField::ORGANIZATION_USER_ID,
        DBField::EMAIL_ADDRESS,
        DBField::DISPLAY_NAME,
        DBField::INVITE_HASH,
        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @param $organizationUserId
     * @param $emailAddress
     * @param $displayName
     * @return OrganizationUserInviteEntity
     */
    public function createNewOrganizationUserInvite(Request $request, $organizationUserId, $emailAddress, $displayName)
    {
        $emailTrackingManager = $request->managers->emailTracking();

        $data = [
            DBField::ORGANIZATION_USER_ID => $organizationUserId,
            DBField::EMAIL_ADDRESS => $emailAddress,
            DBField::DISPLAY_NAME => $displayName,
            DBField::INVITE_HASH => $emailTrackingManager->generateChecksum(),
            DBField::IS_ACTIVE => 1,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var OrganizationUserInviteEntity $organizationUserInvite */
        $organizationUserInvite = $this->query($request->db)->createNewEntity($request, $data);

        return $organizationUserInvite;
    }

    /**
     * @param Request $request
     * @param $inviteHash
     * @return array|OrganizationUserInviteEntity
     */
    public function getOrganizationUserInviteByHash(Request $request, $inviteHash)
    {
        return $this->query($request->db)
            ->filter($this->filters->byInviteHash($inviteHash))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $organizationUserId
     * @return array|OrganizationUserInviteEntity
     */
    public function getActiveOrganizationUserInviteByOrganizationUserId(Request $request, $organizationUserId)
    {
        $timeFilterDt = new DateTime($request->getCurrentSqlTime());
        $timeFilterDt->modify("-1 day");

        $organizationUserInvite = $this->query($request->db)
            ->filter($this->filters->byOrganizationUserId($organizationUserId))
            ->filter($this->filters->isActive())
            ->filter($this->filters->createdAfter($timeFilterDt->format(SQL_DATETIME)))
            ->get_entity($request);

        return $organizationUserInvite;
    }

}


class OrganizationsMetaManager extends BaseEntityManager
{
    protected $entityClass = OrganizationMetaEntity::class;
    protected $table = Table::OrganizationMeta;
    protected $table_alias = TableAlias::OrganizationMeta;
    protected $pk = DBField::ORGANIZATION_META_ID;

    const DEFAULT_PRIMARY_COLOR = '#003865';
    const DEFAULT_SECONDARY_COLOR = '#9BCBEB';

    const KEY_ONBOARDED = 'onboarded';
    const KEY_PRIMARY_COLOR = 'primary_color';
    const KEY_SECONDARY_COLOR = 'secondary_color';

    const KEY_GOOGLE_ANALYTICS_ID = 'ga_id';
    const KEY_GAMEDAY_PLAN_TIER = 'gameday_tier';

    const KEY_PILOT_ORDER_ID = 'pilot_order_id';

    protected $foreign_managers = [
        OrganizationsManager::class => DBField::ORGANIZATION_ID
    ];

    public static $fields = [
        DBField::ORGANIZATION_META_ID,
        DBField::ORGANIZATION_ID,
        DBField::KEY,
        DBField::VALUE,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @param $organizationId
     * @param $key
     * @return array|OrganizationMetaEntity
     */
    public function getOrganizationMetaByKey(Request $request, $organizationId, $key)
    {
        return $this->query($request->db)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($this->filters->byKey($key))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return array
     */
    public function getAllOrganizationMetaByOrganizationId(Request $request, $organizationId)
    {
        $organizationMeta = $this->query($request->db)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->get_entities($request);

        if ($organizationMeta)
            $organizationMeta = array_index($organizationMeta, DBField::KEY);

        return $organizationMeta;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param $key
     * @param $value
     * @return array|OrganizationMetaEntity
     */
    public function createUpdateOrganizationMeta(Request $request, $organizationId, $key, $value)
    {
        if (!$organizationMeta = $this->getOrganizationMetaByKey($request, $organizationId, $key)) {
            $data = [
                DBField::ORGANIZATION_ID => $organizationId,
                DBField::KEY => $key,
                DBField::VALUE => $value,
                DBField::IS_ACTIVE => 1
            ];

            /** @var OrganizationMetaEntity $organizationMeta */
            $organizationMeta = $this->query($request->db)->createNewEntity($request, $data);
        } else {
            $updatedData = [
                DBField::IS_ACTIVE => 1,
                DBField::VALUE => $value
            ];

            $organizationMeta->assign($updatedData)->saveEntityToDb($request);
        }

        return $organizationMeta;
    }

    /**
     * @param $organizationId
     * @param $key
     * @return bool
     */
    public function checkBoolMetaKey($organizationId, $key)
    {
        return $this->query()
            ->filter($this->filters->byOrganizationId($organizationId))
            ->filter($this->filters->byKey($key))
            ->filter($this->filters->byValue('1'))
            ->filter($this->filters->isActive())
            ->exists();
    }
}


class OrganizationsActivityManager extends BaseEntityManager
{
    protected $entityClass = OrganizationActivityEntity::class;
    protected $pk = DBField::ORGANIZATION_ACTIVITY_ID;
    protected $table = Table::OrganizationActivity;
    protected $table_alias = TableAlias::OrganizationActivity;

    public static $fields = [
        DBField::ORGANIZATION_ACTIVITY_ID,
        DBField::ORGANIZATION_ID,
        DBField::ORGANIZATION_USER_ID,
        DBField::ACTIVITY_ID,
        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        ActivityManager::class => DBField::ACTIVITY_ID
    ];

    /**
     * @param OrganizationUserEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::ORGANIZATION_USER))
            $data->updateField(VField::ORGANIZATION_USER, []);

        $data->updateField(VField::CREATE_TIME_AGO, time_elapsed_string($request->translations, $data->getCreateTime()));
    }

    /**
     * @param Request $request
     * @param ActivityEntity $activity
     * @param $organizationId
     * @param null $organizationUserId
     * @return OrganizationActivityEntity
     */
    public function trackOrganizationActivity(Request $request, ActivityEntity $activity, OrganizationEntity $organization,
                                              OrganizationUserEntity $organizationUser = null)
    {
        $data = [
            DBField::ORGANIZATION_ID => $organization->getPk(),
            DBField::ORGANIZATION_USER_ID => $organizationUser ? $organizationUser->getPk() : null,
            DBField::ACTIVITY_ID => $activity->getPk(),
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var OrganizationActivityEntity $organizationActivity */
        $organizationActivity = $this->query($request->db)->createNewEntity($request, $data);

        return $organizationActivity;
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    protected function queryJoinActivity(Request $request)
    {
        $activityManager = $request->managers->activity();

        return $this->query($request->db)
            ->inner_join($activityManager)
            ->filter($activityManager->filters->isNotDeleted());
    }

    /**
     * @param Request $request
     * @param OrganizationActivityEntity|OrganizationActivityEntity[] $organizationActivities
     */
    public function postProcessOrganizationActivities(Request $request, $organizationActivities)
    {
        $activityManager = $request->managers->activity();
        $organizationsUsersManager = $request->managers->organizationsUsers();

        if ($organizationActivities) {
            if ($organizationActivities instanceof OrganizationActivityEntity)
                $organizationActivities = [$organizationActivities];

            /** @var OrganizationActivityEntity[] $organizationActivities */
            $organizationActivities = $this->index($organizationActivities);

            $activityIds = unique_array_extract(DBField::ACTIVITY_ID, $organizationActivities);
            $activities = $activityManager->getActivitiesByIds($request, $activityIds);

            $organizationUserIds = unique_array_extract(DBField::ORGANIZATION_USER_ID, $organizationActivities);
            $organizationUsers = $organizationsUsersManager->getOrganizationUsersByIds($request, $organizationUserIds, true);
            /** @var OrganizationUserEntity[] $organizationUsers */
            $organizationUsers = $organizationsUsersManager->index($organizationUsers);

            foreach ($organizationActivities as $organizationActivity) {
                $organizationActivity->setActivity($activities[$organizationActivity->getActivityId()]);

                if ($organizationUserId = $organizationActivity->getOrganizationUserId())
                    $organizationActivity->setOrganizationUser($organizationUsers[$organizationUserId]);
            }

        }
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @param int $page
     * @param int $perPage
     * @return OrganizationActivityEntity[]
     */
    public function getOrganizationActivitiesByOrganizationId(Request $request, $organizationId, $page = 1, $perPage = DEFAULT_PERPAGE)
    {
        /** @var OrganizationActivityEntity[] $organizationActivities */
        $organizationActivities = $this->queryJoinActivity($request)
            ->filter($this->filters->byOrganizationId($organizationId))
            ->paging($page, $perPage)
            ->sort_desc($this->field(DBField::CREATE_TIME))
            ->get_entities($request);

        $this->postProcessOrganizationActivities($request, $organizationActivities);

        return $organizationActivities;
    }
}