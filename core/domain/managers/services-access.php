<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 12/12/18
 * Time: 12:12 AM
 */

Entities::uses('services-access');

class ServicesAccessTokensManager extends BaseEntityManager
{
    protected $entityClass = ServiceAccessTokenEntity::class;
    protected $table = Table::ServiceAccessToken;
    protected $table_alias = TableAlias::ServiceAccessToken;
    protected $pk = DBField::SERVICE_ACCESS_TOKEN_ID;

    protected $accessTokenLength = 8;
    protected $accessTokenChars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    protected $foreign_managers = [
        ServicesAccessTokensTypesManager::class => DBField::SERVICE_ACCESS_TOKEN_TYPE_ID
    ];

    public static $fields = [
        DBField::SERVICE_ACCESS_TOKEN_ID,
        DBField::SERVICE_ACCESS_TOKEN_TYPE_ID,

        DBField::TOKEN,
        DBField::ORGANIZATION_ID,
        DBField::GAME_ID,
        DBField::NET_PRICE,

        DBField::MAX_SEATS,
        DBField::DURATION,

        DBField::START_TIME,
        DBField::END_TIME,
        DBField::ORIGINAL_USES,
        DBField::REMAINING_USES,

        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @param ServiceAccessTokenEntity $data
     * @param Request $request
     * @return DBManagerEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::SERVICE_ACCESS_TOKEN_TYPE))
            $data->updateField(VField::SERVICE_ACCESS_TOKEN_TYPE, []);
    }

    /**
     * @param Request $request
     * @param ServiceAccessTokenEntity[]|ServiceAccessTokenEntity $serviceAccessTokens
     */
    public function postProcessServiceAccessTokens(Request $request, $serviceAccessTokens)
    {
        $servicesAccessTokensTypesManager = $request->managers->servicesAccessTokensTypes();

        if ($serviceAccessTokens) {
            if ($serviceAccessTokens instanceof ServiceAccessTokenEntity)
                $serviceAccessTokens = [$serviceAccessTokens];

            /** @var ServiceAccessTokenEntity[] $serviceAccessTokens */
            $serviceAccessTokens = array_index($serviceAccessTokens, $this->getPkField());

            foreach ($serviceAccessTokens as $serviceAccessToken) {
                $serviceAccessTokenType = $servicesAccessTokensTypesManager->getServiceAccessTokenTypeById($request, $serviceAccessToken->getServiceAccessTokenTypeId());
                $serviceAccessToken->setServiceAccessTokenType($serviceAccessTokenType);
            }
        }
    }

    /**
     * @param $token
     * @return bool
     */
    public function checkTokenExists($token)
    {
        return $this->query()
            ->filter($this->filters->byToken($token))
            ->exists();
    }

    /**
     * @return string
     */
    public function generateToken()
    {
        return generate_random_string($this->accessTokenLength, $this->accessTokenChars);
    }

    /**
     * @return string
     */
    public function getNewUniqueToken()
    {
        $token = $this->generateToken();

        $tokenExists = $this->checkTokenExists($token);

        while ($tokenExists) {
            $token = $this->generateToken();
            $tokenExists = $this->checkTokenExists($token);
        }

        return $token;
    }


    /**
     * @param Request $request
     * @param $serviceAccessTokenTypeId
     * @param null $netPrice
     * @param $maxSeats
     * @param $originalUses
     * @param int $remainingUses
     * @param null $duration
     * @param null $startTime
     * @param null $endTime
     * @param null $organizationId
     * @param null $gameId
     * @return ServiceAccessTokenEntity
     */
    public function createNewServiceAccessToken(Request $request, $serviceAccessTokenTypeId, $netPrice = null, $maxSeats,
                                                $originalUses, $remainingUses = 0, $duration = null, $startTime = null,
                                                $endTime = null, $organizationId = null, $gameId = null)
    {

        $data = [
            DBField::SERVICE_ACCESS_TOKEN_TYPE_ID => $serviceAccessTokenTypeId,
            DBField::TOKEN => $this->getNewUniqueToken(),
            DBField::ORGANIZATION_ID => $organizationId,
            DBField::GAME_ID => $gameId,
            DBField::NET_PRICE => $netPrice,
            DBField::MAX_SEATS => $maxSeats,
            DBField::DURATION => $duration,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::ORIGINAL_USES => $originalUses,
            DBField::REMAINING_USES => $remainingUses,
            DBField::IS_ACTIVE => 1,
            DBField::CREATOR_ID => $request->user->id,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var ServiceAccessTokenEntity $serviceAccessToken */
        $serviceAccessToken = $this->query($request->db)->createNewEntity($request, $data);

        //$this->postProcessServiceAccessTokens($request, $serviceAccessToken);

        return $serviceAccessToken;
    }

    /**
     * @param Request $request
     * @param $token
     * @return array|ServiceAccessTokenEntity
     */
    public function getServiceAccessTokenByToken(Request $request, $token)
    {
        $serviceAccessToken = $this->query($request->db)
            ->filter($this->filters->byToken($token))
            ->get_entity($request);

        $this->postProcessServiceAccessTokens($request, $serviceAccessToken);

        return $serviceAccessToken;
    }

    /**
     * @param Request $request
     * @param $serviceAccessTokenIds
     * @return ServiceAccessTokenEntity[]
     */
    public function getServiceAccessTokensByIds(Request $request, $serviceAccessTokenIds)
    {
        /** @var ServiceAccessTokenEntity[] $serviceAccessTokens */
        $serviceAccessTokens = $this->query($request->db)
            ->filter($this->filters->byPk($serviceAccessTokenIds))
            ->get_entities($request);

        $this->postProcessServiceAccessTokens($request, $serviceAccessTokens);

        return $serviceAccessTokens;
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return ServiceAccessTokenEntity[]
     */
    public function getServiceAccessTokensByOrganizationInstances(Request $request, $organizationId)
    {
        $servicesAccessTokensTypesManager = $request->managers->servicesAccessTokensTypes();
        $servicesAccessTokensInstancesManager = $request->managers->servicesAccessTokensInstances();
        $joinServicesAccessTokensInstancesFilter = $this->filters->And_(
            $servicesAccessTokensInstancesManager->filters->byServiceAccessTokenId($this->createPkField()),
            $servicesAccessTokensInstancesManager->filters->isActive()
        );

        /** @var ServiceAccessTokenEntity[] $serviceAccessTokens */
        $serviceAccessTokens = $this->query($request->db)
            ->inner_join($servicesAccessTokensTypesManager)
            ->inner_join($servicesAccessTokensInstancesManager, $joinServicesAccessTokensInstancesFilter)
            ->filter($servicesAccessTokensInstancesManager->filters->byOwnerTypeId(EntityType::ORGANIZATION))
            ->filter($servicesAccessTokensInstancesManager->filters->byOwnerId($organizationId))
            ->filter($this->filters->isActive())
            ->group_by($this->createPkField())
            ->get_entities($request);

        $this->postProcessServiceAccessTokens($request, $serviceAccessTokens);

        return $serviceAccessTokens;
    }
}

class ServicesAccessTokensTypesManager extends BaseEntityManager
{
    protected $entityClass = ServiceAccessTokenTypeEntity::class;
    protected $table = Table::ServiceAccessTokenType;
    protected $table_alias = TableAlias::ServiceAccessTokenType;
    protected $pk = DBField::SERVICE_ACCESS_TOKEN_TYPE_ID;

    /** @var ServiceAccessTokenTypeEntity[] $allServiceAccessTokenTypes */
    protected $allServiceAccessTokenTypes = [];

    const GNS_KEY_PREFIX = GNS_ROOT.'.service-access-token-types';

    protected $foreign_managers = [
        ServicesAccessTokensTypesCategoriesManager::class => DBField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY_ID,
        ServicesAccessTokensTypesGroupsManager::class => DBField::SERVICE_ACCESS_TOKEN_TYPE_GROUP_ID,
    ];

    public static $fields = [
        DBField::SERVICE_ACCESS_TOKEN_TYPE_ID,
        DBField::SERVICE_ACCESS_TOKEN_TYPE_GROUP_ID,
        DBField::PRIORITY,
        DBField::SLUG,
        DBField::DISPLAY_NAME,
        DBField::DESCRIPTION,
        DBField::IS_BUYABLE,
        DBField::IS_ORGANIZATION_CREATABLE,
        DBField::NET_PRICE,
        DBField::ORIGINAL_USES,
        DBField::MAX_SEATS,
        DBField::DURATION,

        DBField::IS_ACTIVE,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @param ServiceAccessTokenTypeEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::SERVICE_ACCESS_TOKEN_TYPE_GROUP))
            $data->updateField(VField::SERVICE_ACCESS_TOKEN_TYPE_GROUP, []);
    }

    /**
     * @param Request $request
     * @param ServiceAccessTokenTypeEntity[]|ServiceAccessTokenTypeEntity $serviceAccessTokenTypes
     */
    protected function postProcessServiceAccessTokenTypes(Request $request, $serviceAccessTokenTypes)
    {
        $servicesAccessTokensTypesGroupsManager = $request->managers->servicesAccessTokensTypesGroups();

        if ($serviceAccessTokenTypes) {
            if ($serviceAccessTokenTypes instanceof ServiceAccessTokenTypeEntity)
                $serviceAccessTokenTypes = [$serviceAccessTokenTypes];

            foreach ($serviceAccessTokenTypes as $serviceAccessTokenType) {
                $serviceAccessTokenTypeGroup = $servicesAccessTokensTypesGroupsManager->getServiceAccessTokenTypeGroupById($request, $serviceAccessTokenType->getServiceAccessTokenTypeGroupId());

                $serviceAccessTokenType->setServiceAccessTokenTypeGroup($serviceAccessTokenTypeGroup);
            }
        }
    }

    /**
     * @return string
     */
    public function generateAllCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return array|ServiceAccessTokenTypeEntity[]
     */
    public function getAllServiceAccessTokenTypes(Request $request)
    {
        if (!$this->allServiceAccessTokenTypes) {

            $serviceAccessTokenTypes = $this->query($request->db)
                //->cache($this->generateAllCacheKey(), ONE_WEEK)
                ->get_entities($request);

            $this->postProcessServiceAccessTokenTypes($request, $serviceAccessTokenTypes);

            $this->allServiceAccessTokenTypes = $this->index($serviceAccessTokenTypes);
        }

        return $this->allServiceAccessTokenTypes;
    }

    /**
     * @param Request $request
     * @param $serviceAccessTokenTypeId
     * @return ServiceAccessTokenTypeEntity
     */
    public function getServiceAccessTokenTypeById(Request $request, $serviceAccessTokenTypeId)
    {
        return $this->getAllServiceAccessTokenTypes($request)[$serviceAccessTokenTypeId];
    }

    /**
     * @param Request $request
     * @param $serviceAccessTokenTypeCategoryId
     * @return ServiceAccessTokenTypeEntity[]
     */
    public function getAllServiceAccessTokenTypesByCategory(Request $request, $serviceAccessTokenTypeCategoryId, $index = false)
    {
        $serviceAccessTokenTypeGroupsManager = $request->managers->servicesAccessTokensTypesGroups();

        $buyableServiceAccessTokenTypes = $this->query($request->db)
            ->inner_join($serviceAccessTokenTypeGroupsManager)
            ->filter($this->filters->isActive())
            ->filter($this->filters->isNotOrganizationCreatable())
            ->filter($this->filters->byServiceAccessTokenTypeCategoryId($serviceAccessTokenTypeCategoryId))
            ->sort_asc($serviceAccessTokenTypeGroupsManager->field(DBField::PRIORITY))
            //->sort_asc($this->field(DBField::PRIORITY))
            ->sort_asc($this->field(DBField::NET_PRICE))
            ->get_entities($request);

        $this->postProcessServiceAccessTokenTypes($request, $buyableServiceAccessTokenTypes);

        return $index && $buyableServiceAccessTokenTypes ? array_index($buyableServiceAccessTokenTypes, $this->getPkField()) : $buyableServiceAccessTokenTypes;
    }

    /**
     * @param Request $request
     * @param $serviceAccessTokenTypeCategoryId
     * @return ServiceAccessTokenTypeEntity[]
     */
    public function getBuyableServiceAccessTokenTypesByCategory(Request $request, $serviceAccessTokenTypeCategoryId, $index = false)
    {
        $serviceAccessTokenTypeGroupsManager = $request->managers->servicesAccessTokensTypesGroups();

        $buyableServiceAccessTokenTypes = $this->query($request->db)
            ->inner_join($serviceAccessTokenTypeGroupsManager)
            ->filter($this->filters->isActive())
            ->filter($this->filters->isBuyable())
            ->filter($this->filters->isNotOrganizationCreatable())
            ->filter($this->filters->byServiceAccessTokenTypeCategoryId($serviceAccessTokenTypeCategoryId))
            ->sort_asc($serviceAccessTokenTypeGroupsManager->field(DBField::PRIORITY))
            //->sort_asc($this->field(DBField::PRIORITY))
            ->sort_asc($this->field(DBField::NET_PRICE))
            ->get_entities($request);

        $this->postProcessServiceAccessTokenTypes($request, $buyableServiceAccessTokenTypes);

        return $index && $buyableServiceAccessTokenTypes ? array_index($buyableServiceAccessTokenTypes, $this->getPkField()) : $buyableServiceAccessTokenTypes;
    }

    /**
     * @param Request $request
     * @param $serviceAccessTokenTypeCategoryId
     * @param bool $index
     * @return array|ServiceAccessTokenTypeEntity[]
     */
    public function getGiftableServiceAccessTokenTypesByCategory(Request $request, $serviceAccessTokenTypeCategoryId, $index = false)
    {
        $serviceAccessTokenTypeGroupsManager = $request->managers->servicesAccessTokensTypesGroups();

        /** @var ServiceAccessTokenTypeEntity[] $giftableServiceAccessTokenTypes */
        $giftableServiceAccessTokenTypes = $this->query($request->db)
            ->inner_join($serviceAccessTokenTypeGroupsManager)
            ->filter($this->filters->isActive())
            ->filter($this->filters->isNotBuyable())
            ->filter($this->filters->isNotOrganizationCreatable())
            ->filter($this->filters->byServiceAccessTokenTypeCategoryId($serviceAccessTokenTypeCategoryId))
            ->sort_asc($serviceAccessTokenTypeGroupsManager->field(DBField::PRIORITY))
            ->sort_asc($this->field(DBField::PRIORITY))
            //->sort_asc($this->field(DBField::PRIORITY))
            ->sort_asc($this->field(DBField::NET_PRICE))
            ->get_entities($request);

        $this->postProcessServiceAccessTokenTypes($request, $giftableServiceAccessTokenTypes);

        return $index && $giftableServiceAccessTokenTypes ? array_index($giftableServiceAccessTokenTypes, $this->getPkField()) : $giftableServiceAccessTokenTypes;
    }

}


class ServicesAccessTokensTypesGroupsManager extends BaseEntityManager
{
    protected $entityClass = ServiceAccessTokenTypeGroupEntity::class;
    protected $table = Table::ServiceAccessTokenTypeGroup;
    protected $table_alias = TableAlias::ServiceAccessTokenTypeGroup;
    protected $pk = DBField::SERVICE_ACCESS_TOKEN_TYPE_GROUP_ID;

    /** @var ServiceAccessTokenTypeGroupEntity[] $allServiceAccessTokenTypesGroups */
    protected $allServiceAccessTokenTypesGroups = [];

    const GNS_KEY_PREFIX = GNS_ROOT.'.service-access-token-types.groups';

    protected $foreign_managers = [
        ServicesAccessTokensTypesCategoriesManager::class => DBField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY_ID
    ];

    public static $fields = [
        DBField::SERVICE_ACCESS_TOKEN_TYPE_GROUP_ID,
        DBField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY_ID,
        DBField::PRIORITY,
        DBField::SLUG,
        DBField::DISPLAY_NAME,
        DBField::DESCRIPTION,

        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @return string
     */
    public function generateAllCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param ServiceAccessTokenTypeGroupEntity $data
     * @param Request $request
     * @return DBManagerEntity|void
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY))
            $data->updateField(VField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY, []);
    }

    /**
     * @param Request $request
     * @param ServiceAccessTokenTypeGroupEntity[]|ServiceAccessTokenTypeGroupEntity $serviceAccessTokenTypeGroups
     */
    protected function postProcessServiceAccessTokenTypeGroups(Request $request, $serviceAccessTokenTypeGroups)
    {
        $serviceAccessTokenTypeCategoriesManager = $request->managers->servicesAccessTokensTypesCategories();

        if ($serviceAccessTokenTypeGroups) {
            if ($serviceAccessTokenTypeGroups instanceof ServiceAccessTokenTypeGroupEntity)
                $serviceAccessTokenTypeGroups = [$serviceAccessTokenTypeGroups];

            /** @var ServiceAccessTokenTypeGroupEntity[] $serviceAccessTokenTypeGroups */
            $serviceAccessTokenTypeGroups = $this->index($serviceAccessTokenTypeGroups);

            foreach ($serviceAccessTokenTypeGroups as $serviceAccessTokenTypeGroup) {
                $serviceAccessTokenTypeCategory = $serviceAccessTokenTypeCategoriesManager->getServiceAccessTokenTypeCategoryById($request, $serviceAccessTokenTypeGroup->getServiceAccessTokenTypeCategoryId());
                $serviceAccessTokenTypeGroup->setServiceAccessTokenTypeCategory($serviceAccessTokenTypeCategory);
            }

        }
    }

    /**
     * @param Request $request
     * @return array|ServiceAccessTokenTypeGroupEntity[]
     */
    public function getAllServiceAccessTokenTypeGroups(Request $request)
    {
        if (!$this->allServiceAccessTokenTypesGroups) {

            $serviceAccessTokenTypesGroups = $this->query($request->db)
                //->cache($this->generateAllCacheKey(), ONE_WEEK)
                ->get_entities($request);

            $this->postProcessServiceAccessTokenTypeGroups($request, $serviceAccessTokenTypesGroups);

            $this->allServiceAccessTokenTypesGroups = array_index($serviceAccessTokenTypesGroups, $this->getPkField());

        }

        return $this->allServiceAccessTokenTypesGroups;
    }

    /**
     * @param Request $request
     * @param $serviceAccessTokenTypeId
     * @return ServiceAccessTokenTypeGroupEntity
     */
    public function getServiceAccessTokenTypeGroupById(Request $request, $serviceAccessTokenTypeId)
    {
        return $this->getAllServiceAccessTokenTypeGroups($request)[$serviceAccessTokenTypeId];
    }

    /**
     * @param Request $request
     * @param $serviceAccessTokenTypeCategoryId
     * @return ServiceAccessTokenTypeGroupEntity[]
     */
    public function getAllActiveServiceAccessTokensByCategory(Request $request, $serviceAccessTokenTypeCategoryId)
    {
        /** @var ServiceAccessTokenTypeGroupEntity[] $serviceAccessTokenGroups */
        $serviceAccessTokenGroups = [];

        foreach ($this->getAllServiceAccessTokenTypeGroups($request) as $serviceAccessTokenTypeGroup) {
            if ($serviceAccessTokenTypeGroup->getServiceAccessTokenTypeCategoryId() == $serviceAccessTokenTypeCategoryId)
                $serviceAccessTokenGroups[$serviceAccessTokenTypeGroup->getPk()] = $serviceAccessTokenTypeGroup;
        }

        return $serviceAccessTokenGroups;
    }

}

class ServicesAccessTokensTypesCategoriesManager extends BaseEntityManager
{
    protected $entityClass = ServiceAccessTokenTypeCategoryEntity::class;
    protected $table = Table::ServiceAccessTokenTypeCategory;
    protected $table_alias = TableAlias::ServiceAccessTokenTypeCategory;
    protected $pk = DBField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY_ID;

    /** @var ServiceAccessTokenTypeCategoryEntity[] $allServiceAccessTokenTypesCategories */
    protected $allServiceAccessTokenTypesCategories = [];

    const ID_HOST_SEATS = 1;
    const ID_TEAM_SEATS = 2;

    const GNS_KEY_PREFIX = GNS_ROOT.'.service-access-token-types.categories';

    public static $fields = [
        DBField::SERVICE_ACCESS_TOKEN_TYPE_CATEGORY_ID,
        DBField::PRIORITY,
        DBField::SLUG,
        DBField::DISPLAY_NAME,
        DBField::DESCRIPTION,

        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @return string
     */
    public function generateAllCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return array|ServiceAccessTokenTypeCategoryEntity[]
     */
    public function getAllServiceAccessTokenTypeCategories(Request $request)
    {
        if (!$this->allServiceAccessTokenTypesCategories) {

            $serviceAccessTokenTypesCategories = $this->query($request->db)
                //->cache($this->generateAllCacheKey(), ONE_WEEK)
                ->get_entities($request);


            $this->allServiceAccessTokenTypesCategories = array_index($serviceAccessTokenTypesCategories, $this->getPkField());
        }

        return $this->allServiceAccessTokenTypesCategories;
    }

    /**
     * @param Request $request
     * @param $serviceAccessTokenTypeCategoryId
     * @return ServiceAccessTokenTypeCategoryEntity
     */
    public function getServiceAccessTokenTypeCategoryById(Request $request, $serviceAccessTokenTypeCategoryId)
    {
        return $this->getAllServiceAccessTokenTypeCategories($request)[$serviceAccessTokenTypeCategoryId];
    }

}

class ServicesAccessTokensInstancesManager extends  BaseEntityManager
{
    protected $entityClass = ServiceAccessTokenInstanceEntity::class;
    protected $table = Table::ServiceAccessTokenInstance;
    protected $table_alias = TableAlias::ServiceAccessTokenInstance;
    protected $pk = DBField::SERVICE_ACCESS_TOKEN_INSTANCE_ID;

    protected $foreign_managers = [
        ServicesAccessTokensManager::class => DBField::SERVICE_ACCESS_TOKEN_ID
    ];

    public static $fields = [
        DBField::SERVICE_ACCESS_TOKEN_INSTANCE_ID,
        DBField::SERVICE_ACCESS_TOKEN_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::START_TIME,
        DBField::END_TIME,

        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @param ServiceAccessTokenInstanceEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::SERVICE_ACCESS_TOKEN))
            $data->updateField(VField::SERVICE_ACCESS_TOKEN, []);

    }

    /**
     * @param Request $request
     * @return SqlQuery
     */
    protected function queryJoinAccessTokens(Request $request)
    {
        $serviceAccessTokensManager = $request->managers->servicesAccessTokens();

        return $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($serviceAccessTokensManager))
            ->inner_join($serviceAccessTokensManager)
            ->filter($serviceAccessTokensManager->filters->isActive());

    }

    /**
     * @param Request $request
     * @return ServiceAccessTokenInstanceEntity
     */
    public function createNewServiceAccessTokenInstance(Request $request, ServiceAccessTokenEntity $serviceAccessToken,
                                                        $ownerTypeId, $ownerId, $startTime = null, $endTime = null)
    {
        $data = [
            DBField::SERVICE_ACCESS_TOKEN_ID => $serviceAccessToken->getPk(),
            DBField::OWNER_TYPE_ID => $ownerTypeId,
            DBField::OWNER_ID => $ownerId,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::IS_ACTIVE => 1,
            DBField::CREATOR_ID => $request->user->id,
            DBField::CREATE_TIME => $request->getCurrentSqlTime()
        ];

        /** @var ServiceAccessTokenInstanceEntity $serviceAccessTokenInstance */
        $serviceAccessTokenInstance = $this->query($request->db)->createNewEntity($request, $data, false);

        $serviceAccessTokenInstance->setServiceAccessToken($serviceAccessToken);

        return $serviceAccessTokenInstance;
    }

    /**
     * @param Request $request
     * @param $ownerId
     * @param $serviceAccessTokenTypeCategoryId
     * @return array|ServiceAccessTokenInstanceEntity
     */
    public function getActiveServiceAccessTokenInstanceByOwnerAndTypeCategory(Request $request, $ownerTypeId, $ownerId, $serviceAccessTokenTypeCategoryId)
    {
        $serviceAccessTokensManager = $request->managers->servicesAccessTokens();
        $serviceAccessTokensTypesManager = $request->managers->servicesAccessTokensTypes();

        $joinServiceAccessTokenTypesFilter = $serviceAccessTokensTypesManager->filters->byPk($serviceAccessTokensManager->field(DBField::SERVICE_ACCESS_TOKEN_TYPE_ID));

        /** @var ServiceAccessTokenInstanceEntity $serviceAccessTokenInstance */
        $serviceAccessTokenInstance = $this->queryJoinAccessTokens($request)
            ->inner_join($serviceAccessTokensTypesManager, $joinServiceAccessTokenTypesFilter)
            ->filter($serviceAccessTokensTypesManager->filters->byServiceAccessTokenTypeCategoryId($serviceAccessTokenTypeCategoryId))
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->Lte(DBField::START_TIME, $request->getCurrentSqlTime()))
            ->filter($this->filters->Gt(DBField::END_TIME, $request->getCurrentSqlTime()))
            ->filter($this->filters->isActive())
            ->sort_desc($serviceAccessTokensManager->field(DBField::MAX_SEATS))
            ->sort_desc($serviceAccessTokensManager->field(DBField::NET_PRICE))
            ->sort_desc($this->field(DBField::CREATE_TIME))
            ->get_entity($request);

        //$serviceAccessTokensManager->postProcessServiceAccessTokens($request, $serviceAccessTokenInstance->getServiceAccessToken());

        return $serviceAccessTokenInstance;
    }


    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @return ServiceAccessTokenInstanceEntity[]
     */
    public function getAvailableActivationServiceAccessTokenInstancesForOwner(Request $request, $ownerTypeId, $ownerId)
    {
        $serviceAccessTokensManager = $request->managers->servicesAccessTokens();
        $serviceAccessTokensTypesManager = $request->managers->servicesAccessTokensTypes();
        $activationsGroupsManager = $request->managers->activationsGroups();

        $joinServiceAccessTokenTypesFilter = $this->filters->And_(
            $serviceAccessTokensTypesManager->filters->byPk($serviceAccessTokensManager->field(DBField::SERVICE_ACCESS_TOKEN_TYPE_ID)),
            $serviceAccessTokensTypesManager->filters->byServiceAccessTokenTypeCategoryId(ServicesAccessTokensTypesCategoriesManager::ID_HOST_SEATS),
            $serviceAccessTokensTypesManager->filters->isActive()
        );

        $activeFilter = $this->filters->And_(
            $this->filters->isActive(),
            $this->filters->Lte(DBField::START_TIME, $request->getCurrentSqlTime()),
            $this->filters->Or_(
                $this->filters->IsNull(DBField::END_TIME),
                $this->filters->Gte(DBField::END_TIME, $request->getCurrentSqlTime())
            )
        );

        $joinActivationsGroupsManager = $this->filters->And_(
            $this->filters->byPk($activationsGroupsManager->field(DBField::SERVICE_ACCESS_TOKEN_INSTANCE_ID)),
            $activationsGroupsManager->filters->isActive()
        );

        /** @var ServiceAccessTokenInstanceEntity[] $serviceAccessTokenInstances */
        $serviceAccessTokenInstances = $this->queryJoinAccessTokens($request)
            ->inner_join($serviceAccessTokensTypesManager, $joinServiceAccessTokenTypesFilter)
            ->left_join($activationsGroupsManager, $joinActivationsGroupsManager)
            ->filter($activationsGroupsManager->filters->IsNull(DBField::ACTIVATION_GROUP_ID))
            ->filter($serviceAccessTokensManager->filters->isActive())
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($activeFilter)
            ->get_entities($request);

        $serviceAccessTokens = [];

        foreach ($serviceAccessTokenInstances as $serviceAccessTokenInstance) {
            $serviceAccessTokens[$serviceAccessTokenInstance->getServiceAccessTokenId()] = $serviceAccessTokenInstance->getServiceAccessToken();
        }

        $serviceAccessTokensManager->postProcessServiceAccessTokens($request, $serviceAccessTokens);

        return $serviceAccessTokenInstances;
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @return ServiceAccessTokenInstanceEntity[]
     */
    public function getAllServiceAccessTokenInstancesForOwner(Request $request, $ownerTypeId, $ownerId)
    {
        $serviceAccessTokensManager = $request->managers->servicesAccessTokens();
        $serviceAccessTokensTypesManager = $request->managers->servicesAccessTokensTypes();

        $joinServiceAccessTokenTypesFilter = $this->filters->And_(
            $serviceAccessTokensTypesManager->filters->byPk($serviceAccessTokensManager->field(DBField::SERVICE_ACCESS_TOKEN_TYPE_ID)),
            $serviceAccessTokensTypesManager->filters->isActive()
        );

        /** @var ServiceAccessTokenInstanceEntity[] $serviceAccessTokenInstances */
        $serviceAccessTokenInstances = $this->queryJoinAccessTokens($request)
            ->inner_join($serviceAccessTokensTypesManager, $joinServiceAccessTokenTypesFilter)
            ->filter($serviceAccessTokensManager->filters->isActive())
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        $serviceAccessTokens = [];

        foreach ($serviceAccessTokenInstances as $serviceAccessTokenInstance) {
            $serviceAccessTokens[$serviceAccessTokenInstance->getServiceAccessTokenId()] = $serviceAccessTokenInstance->getServiceAccessToken();
        }

        $serviceAccessTokensManager->postProcessServiceAccessTokens($request, $serviceAccessTokens);

        foreach ($serviceAccessTokenInstances as $serviceAccessTokenInstance) {
            $serviceAccessToken = $serviceAccessTokens[$serviceAccessTokenInstance->getServiceAccessTokenId()];
            $serviceAccessTokenInstance->setServiceAccessToken($serviceAccessToken);
        }

        return $serviceAccessTokenInstances;
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param $serviceAccessTokenTypeCategoryId
     * @return ServiceAccessTokenInstanceEntity[]
     */
    public function getActiveServiceAccessTokenInstancesByOwnerAndTypeCategory(Request $request, $ownerTypeId, $ownerId, $serviceAccessTokenTypeCategoryId)
    {
        $serviceAccessTokensManager = $request->managers->servicesAccessTokens();
        $serviceAccessTokensTypesManager = $request->managers->servicesAccessTokensTypes();

        $joinServiceAccessTokenTypesFilter = $this->filters->And_(
            $serviceAccessTokensTypesManager->filters->byPk($serviceAccessTokensManager->field(DBField::SERVICE_ACCESS_TOKEN_TYPE_ID)),
            $serviceAccessTokensTypesManager->filters->byServiceAccessTokenTypeCategoryId($serviceAccessTokenTypeCategoryId),
            $serviceAccessTokensTypesManager->filters->isActive()
        );

        $activeFilter = $this->filters->And_(
            $this->filters->isActive(),
            $this->filters->Lte(DBField::START_TIME, $request->getCurrentSqlTime()),
            $this->filters->Or_(
                $this->filters->IsNull(DBField::END_TIME),
                $this->filters->Gte(DBField::END_TIME, $request->getCurrentSqlTime())
            )
        );

        /** @var ServiceAccessTokenInstanceEntity[] $serviceAccessTokenInstances */
        $serviceAccessTokenInstances = $this->queryJoinAccessTokens($request)
            ->inner_join($serviceAccessTokensTypesManager, $joinServiceAccessTokenTypesFilter)
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($activeFilter)
            ->get_entities($request);

        $serviceAccessTokens = [];

        foreach ($serviceAccessTokenInstances as $serviceAccessTokenInstance) {
            $serviceAccessTokens[$serviceAccessTokenInstance->getServiceAccessTokenId()] = $serviceAccessTokenInstance->getServiceAccessToken();
        }

        $serviceAccessTokensManager->postProcessServiceAccessTokens($request, $serviceAccessTokens);

        return $serviceAccessTokenInstances;
    }

    /**
     * @param Request $request
     * @param $serviceAccessTokenIds
     * @param $ownerId
     * @param int $ownerTypeId
     */
    public function deactivateServiceAccessTokenInstancesByTokensAndOwner(Request $request, $serviceAccessTokenIds, $ownerId, $ownerTypeId = EntityType::ORGANIZATION)
    {
        $updatedData = [
            DBField::MODIFIED_BY => $request->requestId,
            DBField::DELETED_BY => $request->requestId,
            DBField::IS_ACTIVE => 0
        ];

        $this->query($request->db)
            ->filter($this->filters->byServiceAccessTokenId($serviceAccessTokenIds))
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->isActive())
            ->update($updatedData);
    }

}
