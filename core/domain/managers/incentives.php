<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 7/11/19
 * Time: 5:03 PM
 */

Entities::uses('incentives');

class IncentivesManager extends BaseEntityManager
{
    protected $entityClass = IncentiveEntity::class;
    protected $table = Table::Incentive;
    protected $table_alias = TableAlias::Incentive;
    protected $pk = DBField::INCENTIVE_ID;

    protected $tokenLength = 8;
    protected $tokenChars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';


    public static $fields = [
        DBField::INCENTIVE_ID,
        DBField::INCENTIVE_TYPE_ID,
        DBField::TOKEN,
        DBField::MAX_AMOUNT,
        DBField::DISCOUNT_PERCENTAGE,
        DBField::START_TIME,
        DBField::END_TIME,
        DBField::ORIGINAL_USES,
        DBField::REMAINING_USES,

        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @param IncentiveEntity[]|IncentiveEntity $incentives
     */
    public function postProcessIncentives(Request $request, $incentives)
    {
        $incentiveTypesManager = $request->managers->incentivesTypesManager();
        $usersManager = $request->managers->users();

        if ($incentives) {
            if ($incentives instanceof IncentiveEntity)
                $incentives = [$incentives];

            /** @var IncentiveEntity[] $incentives */
            $incentives = $this->index($incentives);

            $creatorIds = unique_array_extract(DBField::CREATOR_ID, $incentives);
            $creatorUsers = $usersManager->getUsersByIds($request, $creatorIds);
            /** @var UserEntity[] $creatorUsers */
            $creatorUsers = $usersManager->index($creatorUsers);

            foreach ($incentives as $incentive) {
                $incentiveType = $incentiveTypesManager->getIncentiveTypeById($request, $incentive->getIncentiveTypeId());
                $creatorUser = $creatorUsers[$incentive->getCreatorId()];
                $incentive->setIncentiveType($incentiveType);
                $incentive->setCreatorUser($creatorUser);
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
        return generate_random_string($this->tokenLength, $this->tokenChars);
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
     * @param $incentiveTypeId
     * @param $maxAmount
     * @param $discountPercentage
     * @param $startTime
     * @param $endTime
     * @param int $originalUses
     * @param int $remainingUses
     * @param null $token
     * @return IncentiveEntity
     */
    public function createNewIncentive(Request $request, $incentiveTypeId, $maxAmount, $discountPercentage, $startTime,
                                       $endTime, $originalUses = 1, $remainingUses = 0, $token = null)
    {
        if (!$token) {
            $token = $this->getNewUniqueToken();
        }

        $data = [
            DBField::INCENTIVE_TYPE_ID => $incentiveTypeId,
            DBField::TOKEN => $token,
            DBField::MAX_AMOUNT => $maxAmount,
            DBField::DISCOUNT_PERCENTAGE => $discountPercentage,
            DBField::START_TIME => $startTime,
            DBField::END_TIME => $endTime,
            DBField::ORIGINAL_USES => $originalUses,
            DBField::REMAINING_USES => $remainingUses,
            DBField::IS_ACTIVE => 1,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::CREATOR_ID => $request->user->id,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var IncentiveEntity $incentive */
        $incentive = $this->query($request->db)->createNewEntity($request, $data);

        $this->postProcessIncentives($request, $incentive);

        return $incentive;
    }

    /**
     * @param Request $request
     * @param $incentiveIds
     * @return IncentiveEntity[]
     */
    public function getIncentivesByIds(Request $request, $incentiveIds)
    {
        /** @var IncentiveEntity[] $incentives */
        $incentives = $this->query($request->db)
            ->filter($this->filters->byPk($incentiveIds))
            ->get_entities($request);

        $this->postProcessIncentives($request, $incentives);

        return $incentives;
    }
}

class IncentivesTypesManager extends BaseEntityManager
{
    protected $entityClass = IncentiveTypeEntity::class;
    protected $table = Table::IncentiveType;
    protected $table_alias = TableAlias::IncentiveType;
    protected $pk = DBField::INCENTIVE_TYPE_ID;

    /** @var IncentiveTypeEntity[]  */
    protected $incentiveTypes = [];

    const TYPE_STORE_CREDIT = 1;
    const TYPE_MANUAL_DISCOUNT = 2;

    public static $fields = [
        DBField::INCENTIVE_TYPE_ID,
        DBField::DISPLAY_NAME,

        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    /**
     * @param Request $request
     * @return array|IncentiveTypeEntity[]
     */
    public function getAllIncentiveTypes(Request $request)
    {
        if (!$this->incentiveTypes) {
            $incentiveTypes = $this->query($request->db)
                ->get_entities($request);

            $this->incentiveTypes = $this->index($incentiveTypes);
        }

        return $this->incentiveTypes;
    }

    /**
     * @param Request $request
     * @param $incentiveTypeId
     * @return array|IncentiveTypeEntity
     */
    public function getIncentiveTypeById(Request $request, $incentiveTypeId)
    {
        return $this->getAllIncentiveTypes($request)[$incentiveTypeId] ?? [];
    }

}


class IncentivesInstancesManager extends BaseEntityManager
{
    protected $entityClass = IncentiveInstanceEntity::class;
    protected $table = Table::IncentiveInstance;
    protected $table_alias = TableAlias::IncentiveInstance;
    protected $pk = DBField::INCENTIVE_INSTANCE_ID;

    public static $fields = [
        DBField::INCENTIVE_INSTANCE_ID,
        DBField::INCENTIVE_ID,
        DBField::CONTEXT_ENTITY_TYPE_ID,
        DBField::CONTEXT_ENTITY_ID,
        DBField::IS_ACTIVE,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY
    ];

    protected $foreign_managers = [
        IncentivesManager::class => DBField::INCENTIVE_ID
    ];

    /**
     * @param IncentiveInstanceEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::INCENTIVE))
            $data->updateField(VField::INCENTIVE, []);
    }

    /**
     * @param Request $request
     * @param bool $activeIncentives
     * @return SQLQuery
     */
    protected function queryJoinIncentives(Request $request, $activeIncentives = false)
    {
        $incentivesManager = $request->managers->incentivesManager();

        $queryBuilder = $this->query($request->db)
            ->inner_join($incentivesManager);

        if ($activeIncentives)
            $queryBuilder->filter($incentivesManager->filters->isActive());

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param IncentiveInstanceEntity[]|IncentiveInstanceEntity $incentiveInstances
     */
    public function postProcessIncentiveInstances(Request $request, $incentiveInstances)
    {
        $incentivesManager = $request->managers->incentivesManager();

        if ($incentiveInstances) {
            if ($incentiveInstances instanceof IncentiveEntity)
                $incentiveInstances = [$incentiveInstances];

            /** @var IncentiveInstanceEntity[] $incentiveInstances */
            $incentiveInstances = $this->index($incentiveInstances);
            $incentiveIds = unique_array_extract(DBField::INCENTIVE_ID, $incentiveInstances);

            $incentives = $incentivesManager->getIncentivesByIds($request, $incentiveIds);
            $incentives = $incentivesManager->index($incentives);

            foreach ($incentiveInstances as $incentiveInstance) {
                $incentive = $incentives[$incentiveInstance->getIncentiveId()];
                $incentiveInstance->setIncentive($incentive);
            }
        }
    }

    /**
     * @param Request $request
     * @param $incentiveId
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @return IncentiveInstanceEntity
     */
    public function createNewIncentiveInstance(Request $request, $incentiveId, $contextEntityTypeId, $contextEntityId)
    {
        $data = [
            DBField::INCENTIVE_ID => $incentiveId,
            DBField::CONTEXT_ENTITY_TYPE_ID => $contextEntityTypeId,
            DBField::CONTEXT_ENTITY_ID => $contextEntityId,
            DBField::IS_ACTIVE => 1,
            DBField::CREATE_TIME => $request->getCurrentSqlTime(),
            DBField::CREATOR_ID => $request->user->id,
            DBField::CREATED_BY => $request->requestId
        ];

        /** @var IncentiveInstanceEntity $incentiveInstance */
        $incentiveInstance = $this->query($request->db)->createNewEntity($request, $data);

        return $incentiveInstance;
    }

    /**
     * @param Request $request
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @return IncentiveInstanceEntity[]
     */
    public function getIncentiveInstancesByContext(Request $request, $contextEntityTypeId, $contextEntityId)
    {
        /** @var IncentiveInstanceEntity[] $incentiveInstances */
        $incentiveInstances = $this->queryJoinIncentives($request)
            ->filter($this->filters->byContextEntityTypeId($contextEntityTypeId))
            ->filter($this->filters->byContextEntityId($contextEntityId))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        $this->postProcessIncentiveInstances($request, $incentiveInstances);

        return $incentiveInstances;
    }
}