<?php
/**
 * Rights Manager
 *
 * @package managers
 */

Entities::uses("rights");

class RightsManager extends BaseEntityManager
{
    const RIGHT_ADMIN_PANEL = 'admin-panel';
    const RIGHT_APPLICATIONS = 'applications';
    const RIGHT_BILLING = 'billing';
    const RIGHT_FINANCE_TOOL = 'finance-tool';
    const RIGHT_GAME_BUILDS = 'game-builds';
    const RIGHT_GAMES = 'games';
    const RIGHT_HOST_APP = 'host-app';
    const RIGHT_SDK = 'sdk';
    const RIGHT_I18N = 'i18n';
    const RIGHT_I18N_HOST_APP = 'i18n-host-app';
    const RIGHT_I18N_SDK = 'i18n-sdk';
    const RIGHT_I18N_WEBSITE = 'i18n-website';
    const RIGHT_LOCALE = 'locale';
    const RIGHT_ORGANIZATIONS = 'organizations';
    const RIGHT_PERMISSIONS = 'permissions';
    const RIGHT_USERGROUPS = 'usergroups';
    const RIGHT_USERS = 'users';


    protected $entityClass = RightEntity::class;
    protected $table = Table::Rights;
    protected $table_alias = TableAlias::Rights;
    protected $pk = DBField::RIGHT_ID;

    protected $foreign_managers = [
        RightsGroupsManager::class => DBField::RIGHT_GROUP_ID
    ];

    public static $fields = [
        DBField::RIGHT_ID,
        DBField::NAME,
        DBField::DISPLAY_NAME,
        DBField::RIGHT_GROUP_ID,
        DBField::DESCRIPTION,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /** @var RightEntity[] */
    protected $rights;

    /**
     * @param RightEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $editUri = "/admin/rights/edit/{$data->getPk()}";
        $deleteUri = "/admin/rights/delete/{$data->getPk()}";

        $editPermissionsUri = "/admin/rights/edit-permissions/{$data->getPk()}";

        $data->updateField(VField::EDIT_PERMISSIONS_URL, $request->getWwwUrl($editPermissionsUri));

        $data->updateField(VField::EDIT_URL, $request->getWwwUrl($editUri));
        $data->updateField(VField::DELETE_URL, $request->getWwwUrl($deleteUri));

        $data->updateField(VField::PARSED_DESCRIPTION, parse_post($data->getDescription()));
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getFormFields(Request $request)
    {
        $rightsGroupsManager = $request->managers->rightsGroups();

        $translations = $request->translations;

        $rightGroups = $rightsGroupsManager->getAllActiveRightGroups($request);

        $fields = [
            new CharField(DBField::DISPLAY_NAME, $translations['Display Name'], 64, true, 'This is the visual name of the right and has no functional impact.'),
            new CharField(DBField::NAME, $translations['Right Name'], 24, true, 'This is the system name of the right and is referenced in code permissions checks.'),
            new SelectField(DBField::RIGHT_GROUP_ID, $translations['Group'], $rightGroups, true, 'Choose the logical grouping for the new right.'),
            new TextField(DBField::DESCRIPTION, $translations['Description'], 499, false, 'Describe what permissions / systems this specific right impacts.')
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    protected function queryJoinRightsGroups(Request $request)
    {
        $rightsGroupsManager = $request->managers->rightsGroups();

        $fields = $this->createDBFields();
        $fields[] = $rightsGroupsManager->aliasField(DBField::DISPLAY_NAME, VField::RIGHT_GROUP_NAME);

        return $this->query($request->db)
            ->fields($fields)
            ->inner_join($rightsGroupsManager);
    }

    /**
     * @param Request $request
     * @param $rightName
     * @param $displayName
     * @param $rightGroupId
     * @param null $description
     * @return RightEntity
     */
    public function createNewRight(Request $request, $rightName, $displayName, $rightGroupId, $description = null)
    {
        $rightData = [
            DBField::NAME => $rightName,
            DBField::DISPLAY_NAME => $displayName,
            DBField::RIGHT_GROUP_ID => $rightGroupId,
            DBField::DESCRIPTION => $description
        ];

        /** @var RightEntity $right */
        $right = $this->query($request->db)->createNewEntity($request, $rightData);

        return $right;
    }

    /**
     * @return array
     */
    public function getRightOptions()
    {
        return [
            Rights::USE,
            Rights::MODERATE,
            Rights::ADMINISTER
        ];
    }

    /**
     * @return array
     */
    public function getRightSelectOptions()
    {
        return [
            [
                DBField::ID => Rights::USE,
                DBField::DISPLAY_NAME => "Use"
            ],
            [
                DBField::ID => Rights::MODERATE,
                DBField::DISPLAY_NAME => "Edit"
            ],
            [
                DBField::ID => Rights::ADMINISTER,
                DBField::DISPLAY_NAME => "Administer"
            ]
        ];
    }

    /**
     * @param RightEntity[]|UserGroupEntity[] $facets
     * @return FormField[]
     */
    public function getDynamicFacetFormFields(array $facets, $options = [])
    {
        $fields = [];

        foreach ($facets as $facet) {
            $fields[] = new RadioField($facet->getDynamicFormField(), $facet->getDisplayName(), $options, false);
        }

        return $fields;
    }

    /**
     * @param Request $request
     * @return RightEntity[]
     */
    public function getAllRights(Request $request)
    {
        $rightsGroupsManager = $request->managers->rightsGroups();

        if (!$this->rights) {
            $rights = $this->queryJoinRightsGroups($request)
                ->sort_asc($rightsGroupsManager->field(DBField::DISPLAY_ORDER))
                ->sort_asc($this->field(DBField::DISPLAY_NAME))
                ->get_entities($request);

            if ($rights)
                $rights = array_index($rights, $this->getPkField());

            $this->rights = $rights;
        }


        return $this->rights;
    }

    /**
     * @param Request $request
     * @return RightEntity[]
     */
    public function getAllActiveRights(Request $request)
    {
        $rights = $this->getAllRights($request);

        foreach ($rights as $key => $right) {
            if (!$right->is_active())
                unset($rights[$key]);
        }

        return $rights;
    }

    /**
     * @param array $rights
     * @return array
     */
    public function groupRightsByRightGroup(array $rights = [])
    {
        $groupedRights = [];

        foreach ($rights as $right) {
            if (!array_key_exists($right->getRightGroupId(), $groupedRights))
                $groupedRights[$right->getRightGroupId()] = [];

            $groupedRights[$right->getRightGroupId()][$right->getPk()] = $right;
        }

        return $groupedRights;
    }

    /**
     * @param Request $request
     * @param $rightId
     * @return RightEntity
     */
    public function getRightById(Request $request, $rightId)
    {
        return $this->getAllRights($request)[$rightId];
    }

    /**
     * @param Request $request
     * @param $rightGroupId
     * @return RightEntity[]
     */
    public function getRightsByRightGroupId(Request $request, $rightGroupId)
    {
        $rights = $this->query($request->db)
            ->filter($this->filters->byRightGroupId($rightGroupId))
            ->filter($this->filters->isActive())
            ->sort_asc(DBField::DISPLAY_NAME)
            ->get_entities($request);

        return array_index($rights, $this->getPkField());
    }
}

class RightsGroupsManager extends BaseEntityManager
{
    protected $entityClass = RightGroupEntity::class;
    protected $table = Table::RightsGroups;
    protected $table_alias = TableAlias::RightsGroups;
    protected $pk = DBField::RIGHT_GROUP_ID;

    public static $fields = [
        DBField::RIGHT_GROUP_ID,
        DBField::DISPLAY_NAME,
        DBField::DISPLAY_ORDER,
        DBField::DESCRIPTION,
        DBField::IS_ACTIVE,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param RightGroupEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $editUri = "/admin/rights/edit-group/{$data->getPk()}";
        $deleteUri = "/admin/rights/delete-group/{$data->getPk()}";

        $data->updateField(VField::EDIT_URL, $request->getWwwUrl($editUri));
        $data->updateField(VField::DELETE_URL, $request->getWwwUrl($deleteUri));

        $data->updateField(VField::PARSED_DESCRIPTION, parse_post($data->getDescription()));
    }

    /**
     * @param Request $request
     * @return FormField[]
     */
    public function getFormFields(Request $request, $maxDisplayOrder = null)
    {
        $translations = $request->translations;

        $fields = [
            new CharField(DBField::DISPLAY_NAME, $translations['Display Name'], 64, true, 'This is the visual name of the right and has no functional impact.'),
            new IntegerField(DBField::DISPLAY_ORDER, $translations['Display Order'], true, 'Controls which order rights get grouped and displayed in.', 0, $maxDisplayOrder),
            new TextField(DBField::DESCRIPTION, $translations['Description'], 499, false, 'Describe what general area of permissions this right group governs.')
        ];

        return $fields;
    }

    /**
     * @param Request $request
     * @param $displayName
     * @param $displayOrder
     * @param null $description
     * @return RightGroupEntity
     */
    public function createNewRightGroup(Request $request, $displayName, $displayOrder, $description = null)
    {
        $rightData = [
            DBField::DISPLAY_NAME => $displayName,
            DBField::DISPLAY_ORDER => $displayOrder,
            DBField::DESCRIPTION => $description
        ];

        /** @var RightGroupEntity $right */
        $right = $this->query($request->db)->createNewEntity($request, $rightData);

        return $right;
    }


    /**
     * @return int
     */
    public function getMaxDisplayOrder()
    {
        try {
            $displayOrder = $this->query()
                ->sort_desc(DBField::DISPLAY_ORDER)
                ->limit(1)
                ->get_value(DBField::DISPLAY_ORDER);

        } catch (ObjectNotFound $c) {
            $displayOrder = 0;
        }

        return (int) $displayOrder;
    }

    /**
     * @param Request $request
     * @param $displayOrder
     */
    public function incrementDisplayOrderAboveValue(Request $request, $displayOrder)
    {
        $db = $request->db->get_connection();

        $displayOrderField = DBField::DISPLAY_ORDER;

        $this->query()->sql("
            UPDATE {$db->quote_field($this->getTable())} rg
            SET rg.{$displayOrderField} = rg.{$displayOrderField}+1
            WHERE rg.{$displayOrderField} >= {$db->quote_value($displayOrder)}
              AND rg.is_active = 1;
        ");
    }

    /**
     * @param Request $request
     * @param $displayOrder
     */
    public function decrementDisplayOrderAboveValue(Request $request, $displayOrder)
    {
        $db = $request->db->get_connection();

        $displayOrderField = DBField::DISPLAY_ORDER;

        $this->query()->sql("
            UPDATE {$db->quote_field($this->getTable())} rg
            SET rg.{$displayOrderField} = rg.{$displayOrderField}-1
            WHERE rg.{$displayOrderField} >= {$db->quote_value($displayOrder)}
              AND rg.is_active = 1;;
        ");
    }


    /**
     * @param Request $request
     * @return RightGroupEntity[]
     */
    public function getAllRightGroups(Request $request)
    {
        $rightGroups = $this->query($request->db)
            ->sort_asc(DBField::DISPLAY_ORDER)
            ->get_entities($request);

        return array_index($rightGroups, $this->getPkField());
    }

    /**
     * @param Request $request
     * @return RightGroupEntity[]
     */
    public function getAllActiveRightGroups(Request $request)
    {
        $rightGroups = $this->getAllRightGroups($request);

        foreach ($rightGroups as $key => $rightGroup) {
            if (!$rightGroup->is_active())
                unset($rightGroups[$key]);
        }

        return $rightGroups;
    }

    /**
     * @param Request $request
     * @param $rightGroupId
     * @return RightGroupEntity
     */
    public function getRightGroupById(Request $request, $rightGroupId)
    {
        $rightGroups = $this->getAllRightGroups($request);

        return isset($rightGroups[$rightGroupId]) ? $rightGroups[$rightGroupId] : null;
    }
}