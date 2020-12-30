<?php
/**
 * User Groups Manager
 *
 * @package managers
 */
class UserGroupsManager extends BaseEntityManager
{
    protected $entityClass = UserGroupEntity::class;
    protected $table = Table::UserGroups;
    protected $table_alias = TableAlias::UserGroups;
    protected $pk = DBField::USERGROUP_ID;

    public static $fields = [
        DBField::USERGROUP_ID,
        DBField::DISPLAY_NAME,
        DBField::DESCRIPTION,
        DBField::IS_PRIMARY,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    const GROUP_ID_GUESTS = 1;
    const GROUP_ID_UNCONFIRMED_USERS = 2;
    const GROUP_ID_REGISTERED_USERS = 3;
    const GROUP_ID_ESC_EMPLOYEES = 4;
    const GROUP_ID_ESC_ADMINS = 5;

    const GROUP_ID_INTERNAL_BETA_TESTERS = 6;
    const GROUP_ID_ESI_DESIGN = 7;
    const GROUP_ID_SHAKE_ACTIVATIONS = 8;

    const GNS_KEY_PREFIX = GNS_ROOT.'.user-groups';


    /**
     * @param UserGroupEntity $data
     * @param Request $request
     * @return UserGroupEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $editUri = "/admin/usergroups/edit/{$data->getPk()}";
        $deleteUri = "/admin/usergroups/delete/{$data->getPk()}";
        $editPermissionsUri = "/admin/usergroups/edit-permissions/{$data->getPk()}";

        $data->updateField(VField::EDIT_URL, $request->getWwwUrl($editUri));
        $data->updateField(VField::DELETE_URL, $request->getWwwUrl($deleteUri));
        $data->updateField(VField::EDIT_PERMISSIONS_URL, $request->getWwwUrl($editPermissionsUri));

        $data->updateField(VField::PARSED_DESCRIPTION, parse_post($data->getDescription()));
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
     */
    public function bustCacheForAll(Request $request)
    {
        $request->cache->delete($this->generateCacheKey(), true);
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getFormFields(Request $request)
    {
        return [
            new CharField(DBField::DISPLAY_NAME, $request->translations['Name'], 254, true, 'This is the display name of the user group.'),
            new TextField(DBField::DESCRIPTION, $request->translations['Description'], 499, false, 'Explain what the purposes of this group is.'),
            new BooleanField(DBField::IS_PRIMARY, $request->translations['Is Primary'], false, 'Controls whether this usergroup can be a primary group for user accounts.')
        ];
    }

    public function getRightsFormFields(Request $request, $userGroups, $rights = [])
    {
        $rightsManager = $request->managers->rights();
        $rightOptions = $rightsManager->getRightOptions();

        $fields = [];

        foreach ($rights as $right) {
            $fields[] = new RadioField($right->getDynamicFormField(), $right->getDisplayName(), $rightOptions, false);
        }

        return $fields;
    }

    /**
     * @param Request $request
     * @param $displayName
     * @param null $description
     * @return UserGroupEntity
     */
    public function createNewUserGroup(Request $request, $displayName, $description = null)
    {
        $userGroupData = [
            DBField::DISPLAY_NAME => $displayName,
            DBField::DESCRIPTION => $description,
        ];

        /** @var UserGroupEntity $userGroup */
        $userGroup = $this->query($request->db)->createNewEntity($request, $userGroupData);

        return $userGroup;
    }

    /**
     * @param Request $request
     * @return UserGroupEntity[]
     */
    public function getAllUserGroups(Request $request)
    {
        $userGroups = $this->query($request->db)
            ->cache($this->generateCacheKey(), ONE_DAY)
            ->get_entities($request);

        return array_index($userGroups, $this->getPkField());
    }

    /**
     * @param Request $request
     * @return UserGroupEntity[]
     */
    public function getAllActiveUserGroups(Request $request)
    {
        $userGroups = $this->getAllUserGroups($request);

        foreach ($userGroups as $key => $userGroup) {
            if (!$userGroup->is_active())
                unset($userGroups[$key]);
        }

        return $userGroups;
    }

    /**
     * @param Request $request
     * @return UserGroupEntity[]
     */
    public function getAllActivePrimaryUserGroups(Request $request)
    {
        $userGroups = $this->getAllUserGroups($request);

        foreach ($userGroups as $key => $userGroup) {
            if (!$userGroup->is_active())
                unset($userGroups[$key]);
            if (!$userGroup->is_primary())
                unset($userGroups[$key]);
        }

        return $userGroups;
    }

    /**
     * @param Request $request
     * @return UserGroupEntity[]
     */
    public function getAllActiveSecondaryUserGroups(Request $request, $excludeGroupIds = [])
    {
        $userGroups = $this->getAllUserGroups($request);

        foreach ($userGroups as $key => $userGroup) {
            if (!$userGroup->is_active())
                unset($userGroups[$key]);
            if ($userGroup->is_primary())
                unset($userGroups[$key]);

            // Don't allow Guest UserGroup
            if ($userGroup->getPk() == 1)
                unset($userGroups[$key]);
        }

        return $userGroups;
    }


    /**
     * @param Request $request
     * @param $userGroupId
     * @return array|UserGroupEntity
     */
    public function getUserGroupById(Request $request, $userGroupId, $fetchRights = true)
    {
        /** @var UserGroupEntity $userGroup */
        $userGroup = $this->query($request->db)
            ->filter($this->filters->byPk($userGroupId))
            ->get_entity($request);

        if ($fetchRights)
            $this->postProcessAllUserGroupRights($userGroup);

        return $userGroup;
    }

    /**
     * @param Request $request
     * @param array $userGroupIds
     * @return UserGroupEntity[]
     */
    public function getUserGroupsByIds(Request $request, $userGroupIds = [])
    {
        $activeUserGroups = $this->getAllActiveUserGroups($request);

        $userGroups = [];

        foreach ($userGroupIds as $userGroupId) {
            if (array_key_exists($userGroupId, $activeUserGroups))
                $userGroups[$userGroupId] = $activeUserGroups[$userGroupId];
        }

        return $userGroups;
    }


    /**
     * @param UserGroupEntity $userGroup
     */
    protected function postProcessAllUserGroupRights(UserGroupEntity $userGroup)
    {
        $group_id = $userGroup->getPk();
        Validators::id($group_id, true);

        $adminAccess = Rights::getAccessLevel(Rights::ADMINISTER);
        $moderateAccess = Rights::getAccessLevel(Rights::MODERATE);
        $useAccess = Rights::getAccessLevel(Rights::USE);

        $sql = DB::inst(SQLN_SITE);

        $data = [];

        // Administer permissions
        $r = $sql->query_read('
            SELECT gr.`right_id` 
            FROM '.$sql->quote_field(Table::GroupRights).' gr
            JOIN '.$sql->quote_field(Table::Rights).' r
                ON r.right_id = gr.right_id
            WHERE gr.`usergroup_id` = '.$group_id.'
            AND gr.`access_level` & 1 = '.$adminAccess.'
        ');

        $a_rights = [];
        while ($row = $r->fetch_assoc()) {
            $a_rights[$row[DBField::RIGHT_ID]] = $row[DBField::RIGHT_ID];
        }
        $data[VField::A_RIGHTS] = $a_rights;

        // Moderate permissions
        $r = $sql->query_read('
            SELECT gr.`right_id` 
            FROM '.$sql->quote_field(Table::GroupRights).' gr
            JOIN '.$sql->quote_field(Table::Rights).' r
                ON r.right_id = gr.right_id
            WHERE gr.`usergroup_id` = '.$group_id.'
            AND gr.`access_level` & 2 = '.$moderateAccess.'
        ');

        $m_rights = [];

        while ($row = $r->fetch_assoc()) {
            $m_rights[$row[DBField::RIGHT_ID]] = $row[DBField::RIGHT_ID];
        }

        $data[VField::M_RIGHTS] = $m_rights;

        // User permissions
        $r = $sql->query_read('
            SELECT gr.`right_id` 
            FROM '.$sql->quote_field(Table::GroupRights).' gr
            JOIN '.$sql->quote_field(Table::Rights).' r
                ON r.right_id = gr.right_id
            WHERE gr.`usergroup_id` = '.$group_id.'
            AND gr.`access_level` & 4 = '.$useAccess.'
        ');

        $u_rights = [];

        while ($row = $r->fetch_assoc()) {
            $u_rights[$row[DBField::RIGHT_ID]] = $row[DBField::RIGHT_ID];
        }

        $data[VField::U_RIGHTS] = $u_rights;

        $userGroup->updateField(VField::PERMISSIONS, $data);
    }

    /**
     * @param Request $request
     * @param UserGroupEntity[] $userGroups
     */
    public function postProcessUserGroupsRightByRightId($userGroups = [], $rightId)
    {
        if ($userGroups) {

            $userGroupIds = extract_pks($userGroups);

            $adminAccess = Rights::getAccessLevel(Rights::ADMINISTER);
            $moderateAccess = Rights::getAccessLevel(Rights::MODERATE);
            $useAccess = Rights::getAccessLevel(Rights::USE);

            $sql = DB::inst(SQLN_SITE);

            $data = [];

            // Administer permissions
            $r = $sql->query_read('
                SELECT gr.`right_id`, gr.usergroup_id 
                FROM '.$sql->quote_field(Table::GroupRights).' gr
                JOIN '.$sql->quote_field(Table::Rights).' r
                    ON r.right_id = gr.right_id
                WHERE gr.`usergroup_id` in ('.join(',', $sql->quote_values($userGroupIds)).')
                    AND gr.`access_level` & 1 = '.$adminAccess.'
                    AND gr.`right_id` = '.$sql->quote_value($rightId).'
            ');

            while ($row = $r->fetch_assoc()) {

                $userGroupId = $row[DBField::USERGROUP_ID];
                $rightId = $row[DBField::RIGHT_ID];

                if (!array_key_exists($userGroupId, $data))
                    $data[$userGroupId] = [];

                $data[$userGroupId][VField::A_RIGHTS][$rightId] = $rightId;
            }

            // Moderate permissions

            $r = $sql->query_read('
                SELECT gr.`right_id`, gr.usergroup_id 
                FROM '.$sql->quote_field(Table::GroupRights).' gr
                JOIN '.$sql->quote_field(Table::Rights).' r
                    ON r.right_id = gr.right_id
                WHERE gr.`usergroup_id` in ('.join(',', $sql->quote_values($userGroupIds)).')
                    AND gr.`access_level` & 2 = '.$moderateAccess.'
                    AND gr.`right_id` = '.$sql->quote_value($rightId).'
            ');

            while ($row = $r->fetch_assoc()) {

                $userGroupId = $row[DBField::USERGROUP_ID];
                $rightId = $row[DBField::RIGHT_ID];

                if (!array_key_exists($userGroupId, $data))
                    $data[$userGroupId] = [];

                $data[$userGroupId][VField::M_RIGHTS][$rightId] = $rightId;
            }

            // User permissions

            $r = $sql->query_read('
                SELECT gr.`right_id`, gr.usergroup_id 
                FROM '.$sql->quote_field(Table::GroupRights).' gr
                JOIN '.$sql->quote_field(Table::Rights).' r
                    ON r.right_id = gr.right_id
                WHERE gr.`usergroup_id` in ('.join(',', $sql->quote_values($userGroupIds)).')
                    AND gr.`access_level` & 4 = '.$useAccess.'
                    AND gr.`right_id` = '.$sql->quote_value($rightId).'
            ');

            while ($row = $r->fetch_assoc()) {
                $userGroupId = $row[DBField::USERGROUP_ID];
                $rightId = $row[DBField::RIGHT_ID];

                if (!array_key_exists($userGroupId, $data))
                    $data[$userGroupId] = [];

                $data[$userGroupId][VField::U_RIGHTS][$rightId] = $rightId;

            }

            foreach ($userGroups as $userGroup) {
                if (array_key_exists($userGroup->getPk(), $data))
                    $permissions = $data[$userGroup->getPk()];
                else
                    $permissions = [];

                $userGroup->updateField(VField::PERMISSIONS, $permissions);
            }
        }
    }

}

class UserGroupsRightsManager extends BaseEntityManager
{
    protected $table = Table::GroupRights;
    protected $table_alias = TableAlias::GroupRights;
    protected $pk = DBField::USERGROUP_ID;

    protected $foreign_managers = [
        RightsManager::class => DBField::RIGHT_ID,
    ];

    public static $fields = [
        DBField::USERGROUP_ID,
        DBField::RIGHT_ID,
        DBField::ACCESS_LEVEL
    ];


    /**
     * @param $userGroupIds
     * @return array
     */
    public function getRightsListByUserGroupIds(Request $request, $userGroupIds)
    {
        $rightsManager = $request->managers->rights();

        $fields = $this->createDBFields();
        $fields[] = $rightsManager->field(DBField::DISPLAY_NAME);
        $fields[] = $rightsManager->field(DBField::NAME);

        $results =  $this->query()
            ->inner_join($rightsManager)
            ->filter($this->filters->byUserGroupId($userGroupIds))
            ->get_list($fields);

        if ($results) {
            $results = array_index($results, $this->getPkField());
        }

        return $results;
    }

    /**
     * @param $rightId
     * @return array
     */
    public function getUserGroupRightsListByRightId($rightId)
    {
        return $this->query()
            ->filter($this->filters->byRightId($rightId))
            ->get_list(DBField::RIGHT_ID, DBField::ACCESS_LEVEL, DBField::USERGROUP_ID);
    }

    /**
     * @param int $userGroupId
     * @param int $rightId
     * @param int $accessLevel
     */
    public function addNewUserGroupRight(int $userGroupId, int $rightId, int $accessLevel)
    {
        $newRightData = [
            DBField::USERGROUP_ID => $userGroupId,
            DBField::RIGHT_ID => $rightId,
            DBField::ACCESS_LEVEL => $accessLevel
        ];

        $this->query()->replace($newRightData);
    }

    /**
     * @param int $userGroupId
     * @param int $rightId
     */
    public function deleteUserGroupRight(int $userGroupId, int $rightId)
    {
        $this->query()
            ->filter($this->filters->byUserGroupId($userGroupId))
            ->filter($this->filters->byRightId($rightId))
            ->delete();
    }

    /**
     * @param int $userGroupId
     * @param int $rightId
     * @param int $accessLevel
     */
    public function updateUserGroupRight(int $userGroupId, int $rightId, int $accessLevel)
    {
        $updatedAccessLevel = [
            DBField::ACCESS_LEVEL => $accessLevel
        ];

        $this->query()
            ->filter($this->filters->byUserGroupId($userGroupId))
            ->filter($this->filters->byRightId($rightId))
            ->replace($updatedAccessLevel);
    }

    /**
     * Updates rights for a user-group in the database
     *
     * @return string
     */
    public function updateAllRightsForUserGroupId(Request $request, $userGroupId, array $adminRights, array $moderateRights, array $useRights)
    {
        Validators::id($userGroupId, true);

        $adminAccess = Rights::getAccessLevel('amu');
        $moderateAccess = Rights::getAccessLevel('mu');
        $useAccess = Rights::getAccessLevel('u');
        $oldRights = [];

        foreach ($this->getRightsListByUserGroupIds($request, $userGroupId) as $oldRight) {
            $oldRights[$oldRight[DBField::RIGHT_ID]] = (int) $oldRight[DBField::ACCESS_LEVEL];
        }

        // Combine new rights array
        $newRights = [];

        // Admin Rights First
        foreach ($adminRights as $right) {
            $newRights[$right] = $adminAccess;
        }

        // Moderate Rights Second
        foreach ($moderateRights as $right) {
            if (array_key_exists($right, $newRights))
                $newRights[$right] |= $moderateAccess;
            else
                $newRights[$right] = $moderateAccess;
        }

        // Use Rights Third
        foreach ($useRights as $right) {
            if (array_key_exists($right, $newRights))
                $newRights[$right] |= $useAccess;
            else
                $newRights[$right] = $useAccess;
        }

        // Insert or update new rights definitions
        foreach ($newRights as $rightId => $accessLevel) {

            Validators::id($rightId, true);
            Validators::uint($accessLevel, true);

            if (array_key_exists($rightId, $oldRights)) {
                // Update all the rights in both $oldRights and $rights with
                // different access level

                if ($oldRights[$rightId] != $newRights[$rightId])
                    $this->updateUserGroupRight($userGroupId, $rightId, $accessLevel);

            } else {
                // Add all the rights, missing in $old_rights
                $this->addNewUserGroupRight($userGroupId, $rightId, $accessLevel);
            }
        }

        // Remove all the rights in $old_rights but not in $rights
        foreach ($oldRights as $rightId => $accessLevel) {
            if (!array_key_exists($rightId, $newRights)) {
                $this->deleteUserGroupRight($userGroupId, $rightId);
            }
        }
    }

    /**
     * Updates rights for a user-group in the database
     *
     * @return string
     */
    public function updateRightForAllUserGroups(Request $request, $rightId, array $adminRights, array $moderateRights, array $useRights)
    {
        Validators::id($rightId, true);

        $adminAccess = Rights::getAccessLevel('amu');
        $moderateAccess = Rights::getAccessLevel('mu');
        $useAccess = Rights::getAccessLevel('u');
        $oldRights = [];

        foreach ($this->getUserGroupRightsListByRightId($rightId) as $oldRight) {
            $oldRights[$oldRight[DBField::USERGROUP_ID]] = (int) $oldRight[DBField::ACCESS_LEVEL];
        }

        // Combine new rights array
        $newRights = [];

        // Admin Rights First
        foreach ($adminRights as $userGroupId) {
            $newRights[$userGroupId] = $adminAccess;
        }

        // Moderate Rights Second
        foreach ($moderateRights as $userGroupId) {
            if (array_key_exists($userGroupId, $newRights))
                $newRights[$userGroupId] |= $moderateAccess;
            else
                $newRights[$userGroupId] = $moderateAccess;
        }

        // Use Rights Third
        foreach ($useRights as $userGroupId) {
            if (array_key_exists($userGroupId, $newRights))
                $newRights[$userGroupId] |= $useAccess;
            else
                $newRights[$userGroupId] = $useAccess;
        }

        // Insert or update new rights definitions
        foreach ($newRights as $userGroupId => $accessLevel) {

            Validators::id($userGroupId, true);
            Validators::uint($accessLevel, true);

            if (array_key_exists($userGroupId, $oldRights)) {
                // Update all the rights in both $oldRights and $rights with
                // different access level

                if ($oldRights[$userGroupId] != $newRights[$userGroupId])
                    $this->updateUserGroupRight($userGroupId, $rightId, $accessLevel);

            } else {
                // Add all the rights, missing in $old_rights
                $this->addNewUserGroupRight($userGroupId, $rightId, $accessLevel);
            }
        }

        // Remove all the rights in $old_rights but not in $rights
        foreach ($oldRights as $userGroupId => $accessLevel) {
            if (!array_key_exists($userGroupId, $newRights)) {
                $this->deleteUserGroupRight($userGroupId, $rightId);
            }
        }

        Permissions::bustGuestPermissionsCache($request);
    }

}


class UsersUserGroupsManager extends BaseEntityManager {

    protected $entityClass = UserUserGroupEntity::class;
    protected $table = Table::UsersUserGroups;
    protected $table_alias = TableAlias::UsersUserGroups;
    protected $pk = DBField::USER_USERGROUP_ID;

    const GNS_KEY_PREFIX = UsersManager::GNS_KEY_PREFIX;

    public static $fields = [
        DBField::USER_USERGROUP_ID,
        DBField::USER_ID,
        DBField::USERGROUP_ID,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID
    ];

    /**
     * @param $userId
     * @return string
     */
    public function generateCacheKeyForUser($userId)
    {
        return self::GNS_KEY_PREFIX.".{$userId}.usergroups";
    }

    /**
     * @param Request $request
     * @param $userId
     * @return array
     */
    public function getUserGroupIdsForUserId(Request $request, $userId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            // Disable caching for the time being -- we don't need the performance improvement of 0.3ms (empty table).
            //->cache($this->generateCacheKeyForUser($userId), ONE_DAY)
            ->get_values(DBField::USERGROUP_ID, true, $request);
    }

    /**
     * @param Request $request
     * @param $userId
     * @param $userGroupId
     * @return bool
     */
    public function addUserToUserGroup(Request $request, $userId, $userGroupId)
    {
        $added = false;

        $exists = $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            ->filter($this->filters->byUserGroupId($userGroupId))
            ->exists();

        if (!$exists) {

            $added = true;
            $data = [
                DBField::USER_ID => $userId,
                DBField::USERGROUP_ID => $userGroupId,
                DBField::CREATOR_ID => $request->user->id
            ];
            $this->query($request->db)->createNewEntity($request, $data);
        }

        return $added;
    }


    /**
     * @param Request $request
     * @param $userId
     * @param $userGroupId
     * @return bool
     */
    public function removeUserFromUserGroup(Request $request, $userId, $userGroupId)
    {
        $exists = $this->query($request->db)
            ->filter($this->filters->byUserId($userId))
            ->filter($this->filters->byUserGroupId($userGroupId))
            ->exists();

        if ($exists) {
            $this->query($request->db)
                ->filter($this->filters->byUserId($userId))
                ->filter($this->filters->byUserGroupId($userGroupId))
                ->delete();
            return true;
        } else {
            return false;
        }
    }
}