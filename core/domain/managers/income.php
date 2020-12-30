<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 6/10/17
 * Time: 3:16 PM
 */
Entities::uses('income');

class IncomeManager extends BaseEntityManager {

    const MINIMUM_CONTENT_INCOME_TO_SUMMARIZE = 0.01; // One Cent

    protected $entityClass = IncomeEntity::class;
    protected $table = Table::Income;
    protected $table_alias = TableAlias::Income;

    protected $pk = DBField::INCOME_ID;

    protected $foreign_managers = [
        IncomeTypesManager::class => DBField::INCOME_TYPE_ID,
        IncomeStatusesManager::class => DBField::INCOME_STATUS_ID
    ];

    public static $fields = [
        DBField::INCOME_ID,
        DBField::INCOME_TYPE_ID,
        DBField::INCOME_STATUS_ID,
        DBField::PAYOUT_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::NET_AMOUNT,
        DBField::TAX_RATE,
        DBField::CONTEXT_ENTITY_TYPE_ID,
        DBField::CONTEXT_ENTITY_ID,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::UPDATER_ID,
        DBField::UPDATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @param IncomeEntity $data
     * @param Request $request
     * @return IncomeEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::INCOME_RECORDS, []);

        return $data;
    }

    /**
     * @param Request $request
     * @return SqlQuery
     */
    public function queryJoinStatusTypes(Request $request)
    {
        $incomeStatusesManager = $request->managers->incomeStatuses();
        $incomeTypesManager = $request->managers->incomeTypes();

        return $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($incomeStatusesManager, $incomeTypesManager))
            ->inner_join($incomeTypesManager)
            ->inner_join($incomeStatusesManager)
            ->filter($this->filters->isActive());
    }

    /**
     * @param Request $request
     * @param $incomeTypeId
     * @param $ownerTypeId
     * @param $ownerId
     * @param $netAmount
     * @param $contextEntityTypeId
     * @param $contextEntityId
     * @return IncomeEntity
     */
    public function createNewIncomeRecord(Request $request, $incomeTypeId, $ownerTypeId, $ownerId, $netAmount, $taxRate, $contextEntityTypeId, $contextEntityId, $displayName)
    {
        $incomeData = [
            DBField::INCOME_STATUS_ID => IncomeStatusesManager::STATUS_PENDING,
            DBField::INCOME_TYPE_ID => $incomeTypeId,
            DBField::OWNER_TYPE_ID => $ownerTypeId,
            DBField::OWNER_ID => $ownerId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::NET_AMOUNT => $netAmount,
            DBField::TAX_RATE => $taxRate,
            DBField::CONTEXT_ENTITY_TYPE_ID => $contextEntityTypeId,
            DBField::CONTEXT_ENTITY_ID => $contextEntityId,
            DBField::IS_ACTIVE => 1
        ];

        /** @var IncomeEntity $income */
        $income = $this->query($request->db)->createNewEntity($request, $incomeData, false);

        return $income;
    }

    /**
     * @param Request $request
     * @param $incomeId
     * @return IncomeEntity
     * @throws ObjectNotFound
     */
    public function getIncomeById(Request $request, $incomeId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($incomeId))
            ->get_entity($request);
    }

    /**
     * @return OrFilter
     */
    protected function getUnclaimedPayableIncomePaymentOrFilter()
    {
        $dateFormat = new DateTime('now');
        return $this->filters->Or_(
            $this->filters->byIncomeTypeId(IncomeTypesManager::TYPE_PAYMENT),
            $this->filters->Lt(DBField::CREATE_TIME, $dateFormat->modify("-2 week")->format(SQL_DATETIME))
        );
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @return IncomeEntity[]
     */
    public function getAllUnclaimedPendingIncomeRecordsForOwner(Request $request, $ownerTypeId, $ownerId)
    {
        $dateFormat = new DateTime('now');
        /** @var IncomeEntity[] $incomeRecords */
        $incomeRecords = $this->queryJoinStatusTypes($request)
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->byIncomeStatusId(IncomeStatusesManager::STATUS_PENDING))
            ->filter($this->filters->NotEq(DBField::INCOME_TYPE_ID, IncomeTypesManager::TYPE_PAYMENT))
            ->filter($this->filters->IsNull(DBField::PAYOUT_ID))
            ->filter($this->filters->Gte(DBField::CREATE_TIME, $dateFormat->modify("-2 week")->format(SQL_DATETIME)))
            ->get_entities($request);

        return $incomeRecords;
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @return IncomeEntity[]
     */
    public function getAllUnclaimedPayableIncomeRecordsForOwner(Request $request, $ownerTypeId, $ownerId)
    {
        /** @var IncomeEntity[] $incomeRecords */
        $incomeRecords = $this->queryJoinStatusTypes($request)
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->byIncomeStatusId(IncomeStatusesManager::STATUS_PENDING))
            ->filter($this->filters->IsNull(DBField::PAYOUT_ID))
            ->filter($this->getUnclaimedPayableIncomePaymentOrFilter())
            ->get_entities($request);

        return $incomeRecords;
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @return float|int
     */
    public function getTotalUnclaimedPendingIncomeAmountForOwner(Request $request, $ownerTypeId, $ownerId)
    {
        $dateFormat = new DateTime('now');
        $value =  $this->query($request->db)
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->byIncomeStatusId(IncomeStatusesManager::STATUS_PENDING))
            ->filter($this->filters->NotEq(DBField::INCOME_TYPE_ID, IncomeTypesManager::TYPE_PAYMENT))
            ->filter($this->filters->IsNull(DBField::PAYOUT_ID))
            ->filter($this->filters->isActive())
            ->filter($this->filters->Gte(DBField::CREATE_TIME, $dateFormat->modify("-2 week")->format(SQL_DATETIME)))
            ->get_value(new SumDBField(DBField::PAYOUT_AMOUNT, DBField::NET_AMOUNT, $this->getTable()));

        return is_null($value) ? 0.00 : $value;
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @return float|int
     */
    public function getTotalUnclaimedPayableIncomeAmountForOwner(Request $request, $ownerTypeId, $ownerId)
    {
        try {
            $value =  $this->query($request->db)
                ->filter($this->filters->byOwnerTypeId($ownerTypeId))
                ->filter($this->filters->byOwnerId($ownerId))
                ->filter($this->filters->byIncomeStatusId(IncomeStatusesManager::STATUS_PENDING))
                ->filter($this->filters->IsNull(DBField::PAYOUT_ID))
                ->filter($this->filters->isActive())
                ->filter($this->getUnclaimedPayableIncomePaymentOrFilter())
                ->get_value(new SumDBField(DBField::PAYOUT_AMOUNT, $this->field(DBField::NET_AMOUNT)));

        } catch (ObjectNotFound $e) {
            $value = 0.00;
        }

        if (is_null($value))
            return 0.00;

        return $value;
    }

    /**
     * @param Request $request
     * @param PayoutEntity $payout
     * @return IncomeEntity[]
     */
    public function getAllIncomeRecordsForPayoutId(Request $request, $payoutId)
    {
        return $this->queryJoinStatusTypes($request)
            ->filter($this->filters->byPayoutId($payoutId))
            ->get_entities($request);
    }


    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param IncomeEntity[] $incomeRecords
     */
    public function assignPayoutIdToIncomeRecords(Request $request, PayoutEntity $payout, $incomeRecords = [])
    {
        $success = false;

        if ($incomeRecords) {

            $updatedData = [
                DBField::PAYOUT_ID => $payout->getPk(),
                DBField::UPDATER_ID => $request->user->id,
                DBField::UPDATE_TIME => $request->getCurrentSqlTime()
            ];


            $incomeIds = [];

            foreach ($incomeRecords as $income) {
                $incomeIds[] = $income->getPk();
                $income->assign($updatedData);
                $payout->setIncomeRecord($income);
            }

            $this->query($request->db)
                ->filter($this->filters->byPk($incomeIds))
                ->filter($this->filters->isActive())
                ->update($updatedData);

            $success = true;
        }

        return $success;
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param IncomeEntity[] $incomeRecords
     */
    public function removePayoutIdFromIncomeRecords(Request $request, PayoutEntity $payout)
    {
        $success = false;

        if ($incomeRecords = $payout->getIncomeRecords()) {

            $updatedData = [
                DBField::PAYOUT_ID => null,
                DBField::UPDATER_ID => $request->user->id,
                DBField::UPDATE_TIME => $request->getCurrentSqlTime()
            ];

            $incomeIds = [];

            foreach ($incomeRecords as $income) {
                $incomeIds[] = $income->getPk();
                $income->assign($updatedData);
                $payout->removeIncomeRecord($income->getPk());
            }

            $this->query($request->db)
                ->filter($this->filters->byPk($incomeIds))
                ->filter($this->filters->isActive())
                ->update($updatedData);

            $success = true;
        }

        return $success;
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param IncomeEntity[] $incomeRecords
     */
    public function markIncomeRecordsAsPaid(Request $request, $incomeRecords)
    {
        $success = false;

        if ($incomeRecords) {

            $updatedData = [
                DBField::INCOME_STATUS_ID => IncomeStatusesManager::STATUS_PAID,
                DBField::UPDATER_ID => $request->user->id,
                DBField::UPDATE_TIME => $request->getCurrentSqlTime()
            ];


            $incomeIds = [];

            foreach ($incomeRecords as $income) {
                $incomeIds[] = $income->getPk();
                $income->assign($updatedData);
            }

            $this->query($request->db)
                ->filter($this->filters->byPk($incomeIds))
                ->filter($this->filters->isActive())
                ->update($updatedData);

            $success = true;
        }

        return $success;
    }
}

class IncomeTypesManager extends BaseEntityManager {

    const TYPE_DONATION = 1;
    const TYPE_REVENUE = 2;
    const TYPE_ADS = 3;
    const TYPE_TRANSLATION = 4;
    const TYPE_PAYMENT = 5;

    protected $entityClass = IncomeTypeEntity::class;
    protected $table = Table::IncomeType;
    protected $table_alias = TableAlias::IncomeType;

    protected $pk = DBField::INCOME_TYPE_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::INCOME_TYPE_ID,
        DBField::NAME,
        DBField::DISPLAY_NAME,
        DBField::IS_PUBLIC,
        DBField::IS_ACTIVE,
        DBField::UPDATER_ID,
        DBField::UPDATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];
}

class IncomeStatusesManager extends BaseEntityManager {

    const STATUS_PENDING = 1;
    const STATUS_PAID = 2;
    const STATUS_REFUNDED = 3;
    const STATUS_CANCELLED = 4;

    protected $entityClass = IncomeStatusEntity::class;
    protected $table = Table::IncomeStatus;
    protected $table_alias = TableAlias::IncomeStatus;

    protected $pk = DBField::INCOME_STATUS_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::INCOME_STATUS_ID,
        DBField::NAME,
        DBField::DISPLAY_NAME,
        DBField::IS_ACTIVE,
        DBField::UPDATER_ID,
        DBField::UPDATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];
}

class IncomeContentSummaryManager extends BaseEntityManager {

    const TAX_RATE = 0.0000;

    protected $entityClass = IncomeContentSummaryEntity::class;
    protected $table = Table::IncomeContentSummary;
    protected $table_alias = TableAlias::IncomeContentSummary;
    protected $pk = DBField::INCOME_CONTENT_SUMMARY_ID;

    public static $fields = [
        DBField::INCOME_CONTENT_SUMMARY_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::IS_OPEN,
        DBField::IS_ACTIVE,
        DBField::UPDATER_ID,
        DBField::UPDATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    protected $foreign_managers = [];

    /**
     * @param Request $request
     * @param bool|true $isNull
     * @return SqlQuery
     */
    protected function queryLeftJoinIncome(Request $request, $isNull = true)
    {
        $incomeManager = $request->managers->income();

        $queryBuilder = $this->query($request->db)->left_join($incomeManager, $this->getJoinIncomeFilter($request));

        if ($isNull)
            $queryBuilder->filter($incomeManager->filters->IsNull($incomeManager->createPkField()));

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @return AndFilter
     */
    protected function getJoinIncomeFilter(Request $request)
    {
        $incomeManager = $request->managers->income();

        $joinIncomeFilter = $incomeManager->filters->And_(
            $incomeManager->filters->byContextEntityTypeId(EntityType::INCOME_CONTENT),
            $incomeManager->filters->byContextEntityId($this->createPkField()),
            $incomeManager->filters->byOwnerTypeId($this->field(DBField::OWNER_TYPE_ID)),
            $incomeManager->filters->byOwnerId($this->field(DBField::OWNER_ID)),
            $incomeManager->filters->isActive()
        );

        return $joinIncomeFilter;
    }

    /**
     * @param $artistId
     * @return AndFilter
     */
    protected function getUserFilter($artistId)
    {
        return $this->filters->And_(
            $this->filters->byOwnerTypeId(EntityType::USER),
            $this->filters->byOwnerId($artistId)
        );
    }

    /**
     * @param Request $request
     * @param $userId
     * @return IncomeContentSummaryEntity
     */
    public function createNewIncomeContentSummaryForUserId(Request $request, $userId)
    {
        $incomeContentSummaryData = [
            DBField::OWNER_TYPE_ID => EntityType::USER,
            DBField::OWNER_ID => $userId,
            DBField::IS_ACTIVE => 1
        ];

        return $this->query($request->db)->createNewEntity($request, $incomeContentSummaryData);
    }

    /**
     * @param Request $request
     * @param $incomeContentSummaryId
     * @param null $artistId
     * @return IncomeContentSummaryEntity
     * @throws ObjectNotFound
     */
    public function getIncomeContentSummaryById(Request $request, $incomeContentSummaryId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($incomeContentSummaryId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $incomeContentSummaryId
     * @return IncomeContentSummaryEntity
     * @throws ObjectNotFound
     */
    public function getClosedUnlinkedIncomeContentSummaryById(Request $request, $incomeContentSummaryId)
    {
        return $this->queryLeftJoinIncome($request)
            ->filter($this->filters->byPk($incomeContentSummaryId))
            ->filter($this->filters->isNotOpen())
            ->get_entity($request);
    }


    /**
     * @param Request $request
     * @param $userId
     * @return IncomeContentSummaryEntity
     * @throws ObjectNotFound
     */
    public function getOpenUnlinkedIncomeContentSummaryForArtist(Request $request, $userId)
    {
        $incomeContentSummary = $this->queryLeftJoinIncome($request)
            ->filter($this->getUserFilter($userId))
            ->filter($this->filters->isOpen())
            ->filter($this->filters->isActive())
            ->get_entity($request);

        return $incomeContentSummary;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return IncomeContentSummaryEntity[]
     * @throws ObjectNotFound
     */
    public function getClosedUnlinkedIncomeContentSummariesForArtist(Request $request, $userId)
    {
        $incomeContentSummaries = $this->queryLeftJoinIncome($request)
            ->filter($this->getUserFilter($userId))
            ->filter($this->filters->isNotOpen())
            ->filter($this->filters->isActive())
            ->get_entities($request);

        return $incomeContentSummaries;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return array
     */
    public function getClosedUnlinkedIncomeContentSummaryIdsByArtist(Request $request, $userId)
    {
        return $this->queryLeftJoinIncome($request)
            ->filter($this->getUserFilter($userId))
            ->filter($this->filters->isNotOpen())
            ->filter($this->filters->isActive())
            ->get_values($this->getPkField());
    }


    /**
     * @param Request $request
     * @param $artistId
     * @param $incomeContentSummaryId
     * @return float
     */
    public function getNetMarkupAmountForIncomeContentSummaryId(Request $request, $incomeContentSummaryId)
    {
        $paidUsersPagesManager = $request->managers->paidUsersPages();

        $queryBuilder = $this->queryJoinPaidUsersPages($request)
            ->filter($this->filters->isActive())
            ->filter($this->filters->byPk($incomeContentSummaryId));

        try {

            $summedField = new SumDBField(DBField::VALUE, $paidUsersPagesManager->field(DBField::NET_MARKUP));

            $contentRevenue = $queryBuilder->get_value($summedField);;

            if (!$contentRevenue)
                $contentRevenue = 0.0000;

        } catch (ObjectNotFound $e) {
            $contentRevenue = 0.0000;
        }

        return (float)$contentRevenue;
    }

    /**
     * @param Request $request
     * @param IncomeContentSummaryEntity $incomeContentSummary
     * @return array
     */
    public function fetchRevenueContextForIncomeContentSummary(Request $request, IncomeContentSummaryEntity $incomeContentSummary)
    {
        $paidUsersPagesManager = $request->managers->paidUsersPages();

        $fields = [
            new SumDBField(DBField::NET_MARKUP, $paidUsersPagesManager->field(DBField::NET_MARKUP)),
            new CountDBField(VField::COUNT_OF_USERS, DBField::USER_ID, $paidUsersPagesManager->getTable(), true),
            new CountDBField(VField::COUNT_OF_PAGES, $paidUsersPagesManager->getPkField(), $paidUsersPagesManager->getTable(), true)
        ];

        try {
            $result = $paidUsersPagesManager->query($request)
                ->filter($paidUsersPagesManager->filters->byIncomeContentSummaryId($incomeContentSummary->getPk()))
                ->filter($paidUsersPagesManager->filters->isActive())
                ->filter($paidUsersPagesManager->filters->Gt($paidUsersPagesManager->field(DBField::NET_PRICE), 0))
                ->get($fields);

        } catch (ObjectNotFound $e) {

            $result = [
                DBField::NET_MARKUP => 0,
                VField::COUNT_OF_USERS => 0,
                VField::COUNT_OF_PAGES => 0
            ];
        }

        $incomeContentSummary->assign($result);
    }


    /**
     * @param Request $request
     * @param $artistId
     * @param int $minimumEarningValue
     * @return array
     */
    public function closeOpenIncomeSummaryRecordsForArtist(Request $request, $artistId, $minimumEarningValue = 1)
    {
        $paidUsersPagesManager = $request->managers->paidUsersPages();

        $joinPaidUsersPagesFilter = $this->filters->And_(
            $paidUsersPagesManager->filters->byIncomeContentSummaryId($this->createPkField()),
            $paidUsersPagesManager->filters->Gt(DBField::NET_PRICE, 0)
        );

        $fields = [
            $this->createPkField(),
            new SumDBField(DBField::NET_AMOUNT, $paidUsersPagesManager->field(DBField::NET_PRICE))
        ];

        $openIncomeSummaryRecords = $this->query($request->db)
            ->inner_join($paidUsersPagesManager, $joinPaidUsersPagesFilter)
            ->filter($this->getUserFilter($artistId))
            ->filter($this->filters->isActive())
            ->filter($this->filters->isOpen())
            ->group_by($this->createPkField())
            ->get_list($fields);

        $incomeSummaryRecordsToClose = [];

        foreach ($openIncomeSummaryRecords as $openIncomeSummaryRecord) {
            if ($openIncomeSummaryRecord[DBField::NET_AMOUNT] > $minimumEarningValue)
                $incomeSummaryRecordsToClose[] = $openIncomeSummaryRecord[$this->getPkField()];
        }

        if ($incomeSummaryRecordsToClose) {
            $this->query($request->db)
                ->filter($this->filters->byPk($incomeSummaryRecordsToClose))
                ->filter($this->filters->isOpen())
                ->update([DBField::IS_OPEN => 0]);
        }

        return $openIncomeSummaryRecords;
    }

    /**
     * @param Request $request
     * @param IncomeContentSummaryEntity $incomeContentSummary
     * @return bool|IncomeEntity
     */
    public function createIncomeRecordFromIncomeSummary(Request $request, IncomeContentSummaryEntity $incomeContentSummary)
    {
        $incomeManager = $request->managers->income();

        if (!$incomeContentSummary->is_open()) {

            $netMarkup = $this->getNetMarkupAmountForIncomeContentSummaryId($request, $incomeContentSummary->getPk());

            $income = $incomeManager->createNewIncomeRecord(
                $request,
                IncomeTypesManager::TYPE_REVENUE,
                $incomeContentSummary->getOwnerTypeId(),
                $incomeContentSummary->getOwnerId(),
                $netMarkup,
                self::TAX_RATE,
                EntityType::INCOME_CONTENT,
                $incomeContentSummary->getPk(),
                "Content Revenue"
            );

            return $income;
        }

        return false;
    }
}