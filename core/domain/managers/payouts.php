<?php

Entities::uses('payouts');

class PayoutsManager extends BaseEntityManager {

    protected $entityClass = PayoutEntity::class;
    protected $table = Table::Payout;
    protected $table_alias = TableAlias::Payout;

    protected $pk = DBField::PAYOUT_ID;
    protected $foreign_managers = [
        PayoutsServicesManager::class => DBField::PAYOUT_SERVICE_ID,
        PayoutsStatusesManager::class => DBField::PAYOUT_STATUS_ID,
        PayoutsServicesTokensManager::class => DBField::PAYOUT_SERVICE_TOKEN_ID
    ];

    public static $fields = [
        DBField::PAYOUT_ID,
        DBField::PAYOUT_SERVICE_ID,
        DBField::PAYOUT_STATUS_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::PAYOUT_SERVICE_TOKEN_ID,
        DBField::CURRENCY_ID,
        DBField::COUNTRY_ID,
        DBField::PAYOUT_AMOUNT,
        DBField::TRANSACTION_FEE,
        DBField::PAYOUT_DATE,
        DBField::PAYOUT_MESSAGE,
        DBField::TRANSACTION_ID,
        DBField::AUTHORIZATION_ID,
        DBField::TRANSACTION_NUMBER,
        //DBField::PARAMS, // Let's not load these fields every time we get a payout
        //DBField::RESPONSE,
        //DBField::ERROR,
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
     * @param PayoutEntity $data
     * @param Request $request
     * @return PayoutEntity
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
    protected function queryJoinServicesStatusesTokens(Request $request)
    {
        $payoutsServicesManager = $request->managers->payoutsServices();
        $payoutsStatusesManager = $request->managers->payoutsStatuses();
        $payoutsServicesTokensManager = $request->managers->payoutsServicesTokens();

        $fields = $this->selectAliasedManagerFields($payoutsServicesManager, $payoutsStatusesManager, $payoutsServicesTokensManager);

        return $this->query($request->db)
            ->fields($fields)
            ->inner_join($payoutsServicesManager)
            ->inner_join($payoutsStatusesManager)
            ->inner_join($payoutsServicesTokensManager)
            ->filter($this->filters->isActive());
    }

    /**
     * @param Request $request
     * @param PayoutServiceEntity $payoutService
     * @param $ownerTypeId
     * @param $ownerId
     * @param $countryId
     * @param $ownerPayoutIdentifier
     * @param array $incomeRecords
     * @return PayoutEntity
     */
    protected function createPendingIncomePayout(Request $request, PayoutServiceTokenEntity $payoutServiceToken, $countryId, $incomeRecords = [])
    {
        $payoutData = [
            DBField::PAYOUT_SERVICE_ID => $payoutServiceToken->getPayoutServiceId(),
            DBField::PAYOUT_STATUS_ID => PayoutsStatusesManager::STATUS_PENDING,
            DBField::OWNER_TYPE_ID => $payoutServiceToken->getOwnerTypeId(),
            DBField::OWNER_ID => $payoutServiceToken->getOwnerId(),
            DBField::PAYOUT_SERVICE_TOKEN_ID => $payoutServiceToken->getPk(),
            DBField::CURRENCY_ID => CurrenciesManager::TYPE_USD,
            DBField::COUNTRY_ID => $countryId,
            DBField::PAYOUT_AMOUNT => 0.0000,
            DBField::TRANSACTION_FEE => 0.0000,
            DBField::PAYOUT_DATE => null,
            DBField::PAYOUT_MESSAGE => null,
            DBField::TRANSACTION_ID => null,
            DBField::AUTHORIZATION_ID => null,
            DBField::RESPONSE => null,
            DBField::ERROR => null,
            DBField::IS_ACTIVE => 1
        ];

        /** @var PayoutEntity $payout */
        $payout = $this->query($request->db)->createNewEntity($request, $payoutData, false);

        $payout->setPayoutServiceToken($payoutServiceToken);
        $payout->setPayoutService($payoutServiceToken->getPayoutService());

        return $payout;
    }

    /**
     * @param Request $request
     * @param PayoutEntity[] $payouts
     * @return PayoutEntity[]
     */
    protected function postProcessPayouts(Request $request, $payouts)
    {
        $incomeManager = $request->managers->income();
        $payoutsFeesInvoicesManager = $request->managers->payoutsFeesInvoices();
        $payoutsInvoicesManager = $request->managers->payoutsInvoices();

        if ($payouts) {
            /** @var PayoutEntity[] $payouts */
            $payouts = array_index($payouts, $this->getPkField());

            $incomeRecords = $incomeManager->getAllIncomeRecordsForPayoutId($request, array_keys($payouts));

            foreach ($incomeRecords as $income)
                $payouts[$income->getPayoutId()]->setIncomeRecord($income);

            // Get Paid Payout Ids for getting invoices
            $paidPayoutIds = [];
            foreach ($payouts as $payout) {
                if ($payout->is_paid())
                    $paidPayoutIds[] = $payout->getPk();
            }

            if ($paidPayoutIds) {

                // Get Payout Fee Invoices for Paid Payouts
                $payoutFeeInvoices = $payoutsFeesInvoicesManager->getPayoutFeeInvoicesByPayoutIds($request, $paidPayoutIds);
                foreach ($payoutFeeInvoices as $payoutFeeInvoice)
                    $payouts[$payoutFeeInvoice->getPayoutId()]->setPayoutFeeInvoice($payoutFeeInvoice);

                // Get Payout Invoices for Paid Payouts
                $payoutInvoices = $payoutsInvoicesManager->getPayoutInvoicesByPayoutIds($request, $paidPayoutIds);
                foreach ($payoutInvoices as $payoutInvoice)
                    $payouts[$payoutInvoice->getPayoutId()]->setPayoutInvoice($payoutInvoice);

            }
        }
        return $payouts;
    }

    /**
     * @param Request $request
     * @param PayoutEntity $payout
     * @return PayoutEntity|array
     */
    protected function postProcessPayout(Request $request, $payout)
    {
        if ($payout) {
            $payouts = $this->postProcessPayouts($request, [$payout]);
            $payout = array_pop($payouts);
        }
        return $payout;
    }


    /**
     * @param Request $request
     * @param PayoutEntity $payout
     * @param IncomeEntity[] $pendingIncomeRecords
     * @return mixed
     * @throws BaseManagerEntityException
     * @throws Exception
     */
    public function sendPayout(Request $request, PayoutEntity $payout, $pendingIncomeRecords = [])
    {
        $incomeManager = $request->managers->income();

        $payoutCharge = $payout->getPayoutService()->getPayoutServiceHandler()->sendPayout($request, $payout, $pendingIncomeRecords);

        $updatedPayoutData = [
            DBField::PAYOUT_STATUS_ID => $payoutCharge->getPayoutStatusId(),
            DBField::PAYOUT_AMOUNT => $payoutCharge->getPayoutAmount(),
            DBField::TRANSACTION_FEE => $payoutCharge->getTransactionFee(),
            DBField::PAYOUT_DATE => $payoutCharge->getPayoutDate(),
            DBField::PAYOUT_MESSAGE => $payoutCharge->getPayoutMessage(),
            DBField::TRANSACTION_ID => $payoutCharge->getTransactionId(),
            DBField::AUTHORIZATION_ID => $payoutCharge->getAuthorizationId(),
            DBField::PARAMS => $payoutCharge->getParams(),
            DBField::RESPONSE => $payoutCharge->getResponse(),
            DBField::ERROR => $payoutCharge->getError(),
        ];

        $this->query($request->db)
            ->filter($this->filters->byPk($payout->getPk()))
            ->update($updatedPayoutData);

        $payout->assign($updatedPayoutData);

        $incomeManager->assignPayoutIdToIncomeRecords($request, $payout, $pendingIncomeRecords);

        if ($payout->is_paid())
            $this->finalizePayout($request, $payout);

        return $payoutCharge->getIsSuccess();
    }

    /**
     * @param Request $request
     * @param PayoutEntity $payout
     */
    public function finalizePayout(Request $request, PayoutEntity $payout)
    {
        $activityTrackingManager = $request->managers->activity();
        $usersManager = $request->managers->users();
        $incomeManager = $request->managers->income();
        $payoutsInvoicesManager = $request->managers->payoutsInvoices();
        $payoutsFeesInvoicesManager = $request->managers->payoutsFeesInvoices();
        $tasksManager = $request->managers->tasks();

        // Mark Income Records as Paid
        $incomeManager->markIncomeRecordsAsPaid($request, $payout->getIncomeRecords());

        // Create Payout Invoice
        $payoutsInvoicesManager->createNewPayoutInvoice($request, $payout);

        // If there's a transaction fee, create payout fee invoice
        if ($payout->getTransactionFee() > 0.0000)
            $payoutsFeesInvoicesManager->createNewPayoutFeeInvoice($request, $payout);

        $user = $usersManager->getUserById($request, $payout->getOwnerId());

        $activity = $activityTrackingManager->trackActivity(
            $request,
            ActivityTypesManager::ACTIVITY_TYPE_BRAND_PAYOUT_COMPLETE,
            $payout->getOwnerId(),
            $payout->getPk(),
            $user->getUiLanguageId(),
            $user
        );

        $taskArgs = [
            DBField::PAYOUT_ID => $payout->getPk(),
            DBField::ACTIVITY_ID => $activity->getPk()
        ];
        $tasksManager->add(TasksManager::TASK_PAYOUT_SENT_OWNER_NOTIFICATION, $taskArgs);
    }


    /**
     * @param Request $request
     * @param $userId
     * @return PayoutEntity[]
     */
    public function getPaidIncomePayoutsForUser(Request $request, $userId)
    {
        return $this->getPaidIncomePayouts($request, EntityType::USER, $userId);
    }

    /**
     * @param Request $request
     * @param $organizationId
     * @return PayoutEntity[]
     */
    public function getPaidIncomePayoutsForOrganization(Request $request, $organizationId)
    {
        return $this->getPaidIncomePayouts($request, EntityType::ORGANIZATION, $organizationId);
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @return PayoutEntity[]
     */
    protected function getPaidIncomePayouts(Request $request, $ownerTypeId, $ownerId)
    {
        $excludePayoutStatusIds = [
            PayoutsStatusesManager::STATUS_FAILED,
            PayoutsStatusesManager::STATUS_CANCELLED
        ];

        /** @var PayoutEntity[] $payouts */
        $payouts = $this->queryJoinServicesStatusesTokens($request)
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->notByPayoutStatusId($excludePayoutStatusIds))
            ->sort_desc(DBField::CREATE_TIME)
            ->get_entities($request);

        return $this->postProcessPayouts($request, $payouts);
    }

    /**
     * @param Request $request
     * @param $payoutId
     * @param $ownerTypeId
     * @param $ownerId
     * @return array|PayoutEntity
     * @throws ObjectNotFound
     */
    public function getPayoutByIdForOwner(Request $request, $payoutId, $ownerTypeId, $ownerId)
    {
        /** @var PayoutEntity $payout */
        $payout = $this->queryJoinServicesStatusesTokens($request)
            ->filter($this->filters->byPk($payoutId))
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->get_entity($request);

        return $this->postProcessPayout($request, $payout);
    }

    /**
     * @param Request $request
     * @param $payoutId
     * @return array|PayoutEntity
     * @throws ObjectNotFound
     */
    public function getPayoutById(Request $request, $payoutId)
    {
        /** @var PayoutEntity $payout */
        $payout = $this->queryJoinServicesStatusesTokens($request)
            ->filter($this->filters->byPk($payoutId))
            ->get_entity($request);

        return $this->postProcessPayout($request, $payout);
    }

    /**
     * @param Request $request
     * @param int $page
     * @param int $perPage
     * @param null $payoutStatusId
     * @return PayoutEntity[]
     */
    public function getPayouts(Request $request, $page = 1, $perPage = 50, $payoutStatusId = null, $descending = true)
    {
        $queryBuilder = $this->queryJoinServicesStatusesTokens($request)
            ->filter($this->filters->byPayoutStatusId($payoutStatusId))
            ->paging($page, $perPage);

        if ($descending)
            $queryBuilder->sort_desc($this->getPkField());

        /** @var PayoutEntity[] $payouts */
        $payouts = $queryBuilder->get_entities($request);

        return $this->postProcessPayouts($request, $payouts);
    }

    /**
     * @param Request $request
     * @param null $payoutStatusId
     * @return int
     */
    public function getPayoutsCount(Request $request, $payoutStatusId = null)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPayoutStatusId($payoutStatusId))
            ->count();
    }

    /**
     * @param Request $request
     * @param PayoutEntity $payout
     * @return bool
     */
    public function cancelPayout(Request $request, PayoutEntity $payout)
    {
        $incomeManager = $request->managers->income();

        $success = false;

        if ($payout->is_pending()) {

            $incomeManager->removePayoutIdFromIncomeRecords($request, $payout);

            $payout->updateField(DBField::PAYOUT_STATUS_ID, PayoutsStatusesManager::STATUS_CANCELLED)->saveEntityToDb($request);

            $success = true;
        }

        return $success;
    }

    /**
     * @param Request $request
     * @param $countryId
     * @param array $incomeRecords
     * @return array|PayoutEntity
     */
    public function createPendingIncomePayoutForOrganization(Request $request, $organizationId, $countryId, $incomeRecords = [])
    {
        $payoutsServicesManager = $request->managers->payoutsServices();
        $payoutsServicesTokensManager = $request->managers->payoutsServicesTokens();

        $payoutServiceId = PayoutsServicesManager::SERVICE_MANUAL;

        // Confirm we have an active payout service ID defined for this Artist
        if (!$payoutService = $payoutsServicesManager->getPayoutServiceById($request, $payoutServiceId)) {
            std_log("*** Attempt to fetch PayoutService for Organization: {$organizationId} - failed");
            return [];
        }

        $payoutServiceToken = $payoutsServicesTokensManager->getPayoutServiceTokenForOwner(
            $request,
            $payoutService->getPk(),
            EntityType::ORGANIZATION,
            $organizationId
        );

        return $this->createPendingIncomePayout(
            $request,
            $payoutServiceToken,
            $countryId,
            $incomeRecords
        );
    }

}

class PayoutsStatusesManager extends BaseEntityManager {

    const STATUS_PENDING = 1;
    const STATUS_FAILED = 2;
    const STATUS_PAID = 3;
    const STATUS_REVERSED = 4;
    const STATUS_CANCELLED = 5;

    protected $entityClass = PayoutStatusEntity::class;
    protected $table = Table::PayoutStatus;
    protected $table_alias = TableAlias::PayoutStatus;

    protected $pk = DBField::PAYOUT_STATUS_ID;
    protected $foreign_managers = [];

    const GNS_KEY_PREFIX = GNS_ROOT.'payouts-statuses';

    public static $fields = [
        DBField::PAYOUT_STATUS_ID,
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

    /**
     * @return string
     */
    public function generateCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return PayoutStatusEntity[]
     */
    public function getAllPayoutStatuses(Request $request)
    {
        /** @var PayoutStatusEntity[] $payoutStatuses */
        $payoutStatuses = $this->query($request->db)
            ->cache($this->generateCacheKey(), ONE_WEEK)
            ->get_entities($request);

        if ($payoutStatuses) {
            /** @var PayoutStatusEntity[] $payoutStatuses */
            $payoutStatuses = array_index($payoutStatuses, $this->getPkField());
        }

        return $payoutStatuses;
    }
}

class PayoutsServicesManager extends BaseEntityManager {

    const SERVICE_PAYPAL = 1;
    const SERVICE_ADYEN = 2;
    const SERVICE_STRIPE = 3;
    const SERVICE_COINBASE = 4;
    const SERVICE_MANUAL = 5;

    const DEFAULT_PAYOUT_SERVICE_ID = self::SERVICE_PAYPAL;

    const GNS_KEY_PREFIX = GNS_ROOT.'.payouts.services';

    protected $entityClass = PayoutServiceEntity::class;
    protected $table = Table::PayoutService;
    protected $table_alias = TableAlias::PayoutService;

    protected $pk = DBField::PAYOUT_SERVICE_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::PAYOUT_SERVICE_ID,
        DBField::NAME,
        DBField::DISPLAY_NAME,
        //DBField::MINIMUM_PAYOUT_AMOUNT,
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

    /**
     * @return string
     */
    public function generateAllPayoutServicesCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param $artistId
     * @return string
     */
    public function generateManualCreatorPayoutServiceTokenValue($payoutServiceName, $artistId)
    {
        return "{$payoutServiceName}-creator-{$artistId}";
    }

    /**
     * @param Request $request
     * @return PayoutServiceEntity[]
     */
    public function getAllPayoutServices(Request $request)
    {
        return $this->query($request->db)
            ->local_cache($this->generateAllPayoutServicesCacheKey(), ONE_WEEK)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @return PayoutServiceEntity[]
     */
    public function getAllActivePayoutServices(Request $request)
    {
        $activePayoutServices = [];

        foreach ($this->getAllPayoutServices($request) as $payoutService) {
            if ($payoutService->is_active())
                $activePayoutServices[$payoutService->getPk()] = $payoutService;
        }
        return $activePayoutServices;
    }

    /**
     * @param Request $request
     * @return PayoutServiceEntity[]
     */
    public function getAllPublicPayoutServices(Request $request)
    {
        $publicPayoutServices = [];

        foreach ($this->getAllActivePayoutServices($request) as $payoutService) {
            if ($payoutService->is_public())
                $publicPayoutServices[$payoutService->getPk()] = $payoutService;
        }

        return $publicPayoutServices;
    }

    /**
     * @param Request $request
     * @param $payoutServiceId
     * @return array|PayoutServiceEntity
     */
    public function getPayoutServiceById(Request $request, $payoutServiceId)
    {
        foreach ($this->getAllPayoutServices($request) as $payoutService) {
            if ($payoutService->getPk() == $payoutServiceId)
                return $payoutService;
        }
        return [];
    }


    /**
     * @param PayoutServiceEntity $payoutService
     * @return PayoutServiceInterface
     */
    public function getPayoutServiceHandlerForPayoutService(PayoutServiceEntity $payoutService)
    {
        Modules::load_helper('payouts');

        /** @var PayoutServiceInterface $payoutHandlerClass */
        $serviceName = ucfirst($payoutService->getName());
        $payoutHandlerClass = "{$serviceName}PayoutServiceHandler";

        return new $payoutHandlerClass($payoutService);
    }
}

class PayoutsServicesTokensManager extends BaseEntityManager
{
    protected $entityClass = PayoutServiceTokenEntity::class;
    protected $table = Table::PayoutServiceToken;
    protected $table_alias = TableAlias::PayoutServiceToken;

    protected $pk = DBField::PAYOUT_SERVICE_TOKEN_ID;

    protected $foreign_managers = [
        PayoutsServicesManager::class => DBField::PAYOUT_SERVICE_ID
    ];

    public static $fields = [
        DBField::PAYOUT_SERVICE_TOKEN_ID,
        DBField::PAYOUT_SERVICE_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::TOKEN,
        DBField::RAW_META,
        DBField::RESPONSE,
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
     * @param PayoutServiceTokenEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if ($data->getRawMeta()) {
            $meta = json_decode($data->getRawMeta(), true);
        } else {
            $meta = [];
        }

        $data->updateField(VField::META, $meta);
    }

    /**
     * @param Request $request
     * @return SqlQuery
     */
    protected function queryJoinServices(Request $request)
    {
        $payoutsServicesManager = $request->managers->payoutsServices();

        return $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($payoutsServicesManager))
            ->inner_join($payoutsServicesManager)
            ->filter($this->filters->isActive());
    }

    /**
     * @param Request $request
     * @param PayoutServiceEntity $payoutService
     * @param $ownerTypeId
     * @param $ownerId
     * @param $token
     * @param array $rawMeta
     * @param null $response
     * @return PayoutServiceTokenEntity
     */
    public function createNewPayoutServiceTokenForOwner(Request $request, PayoutServiceEntity $payoutService, $ownerTypeId, $ownerId, $token, $rawMeta = [], $response = null)
    {
        $payoutsServicesTokensHistoryManager = $request->managers->payoutsServicesTokensHistory();

        $payoutServiceTokendata = [
            DBField::PAYOUT_SERVICE_ID => $payoutService->getPk(),
            DBField::OWNER_TYPE_ID => $ownerTypeId,
            DBField::OWNER_ID => $ownerId,
            DBField::TOKEN => $token,
            DBField::RAW_META => $rawMeta ? json_encode($rawMeta) : null,
            DBField::RESPONSE => $response ? serialize($response) : null,
            DBField::IS_ACTIVE => 1,
            DBField::UPDATE_TIME => null,
            DBField::UPDATER_ID => null
        ];

        /** @var PayoutServiceTokenEntity $payoutServiceToken */
        $payoutServiceToken = $this->query($request->db)->createNewEntity($request, $payoutServiceTokendata, false);

        $payoutsServicesTokensHistoryManager->insertPayoutServiceTokenHistory($request, $payoutServiceToken);

        $payoutServiceToken->setPayoutService($payoutService);

        return $payoutServiceToken;
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @return PayoutServiceTokenEntity|array
     * @throws ObjectNotFound
     */
    public function getPayoutServiceTokenForOwner(Request $request, $payoutServiceId, $ownerTypeId, $ownerId)
    {
        return $this->queryJoinServices($request)
            ->filter($this->filters->byPayoutServiceId($payoutServiceId))
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->get_entity($request);
    }
}
class PayoutsServicesTokensHistoryManager extends BaseEntityManager
{
    protected $entityClass = PayoutServiceTokenHistoryEntity::class;
    protected $table = Table::PayoutServiceTokenHistory;
    protected $table_alias = TableAlias::PayoutServiceTokenHistory;

    protected $pk = DBField::PAYOUT_SERVICE_TOKEN_HISTORY_ID;

    protected $foreign_managers = [
        PayoutsServicesTokensManager::class => DBField::PAYOUT_SERVICE_TOKEN_ID
    ];

    public static $fields = [
        DBField::PAYOUT_SERVICE_TOKEN_HISTORY_ID,
        DBField::PAYOUT_SERVICE_TOKEN_ID,
        DBField::PAYOUT_SERVICE_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::TOKEN,
        DBField::RAW_META,
        DBField::RESPONSE,
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
     * @param Request $request
     * @param PayoutServiceTokenEntity $payoutServiceToken
     * @return PayoutServiceTokenHistoryEntity
     */
    public function insertPayoutServiceTokenHistory(Request $request, PayoutServiceTokenEntity $payoutServiceToken)
    {
        $payoutServiceTokenHistoryData = [
            DBField::PAYOUT_SERVICE_TOKEN_ID => $payoutServiceToken->getPk(),
            DBField::PAYOUT_SERVICE_ID => $payoutServiceToken->getPayoutServiceId(),
            DBField::OWNER_TYPE_ID => $payoutServiceToken->getOwnerTypeId(),
            DBField::OWNER_ID => $payoutServiceToken->getOwnerId(),
            DBField::TOKEN  => $payoutServiceToken->getToken(),
            DBField::RAW_META => $payoutServiceToken->getRawMeta(),
            DBField::RESPONSE => $payoutServiceToken->getResponse(),
            DBField::IS_ACTIVE => $payoutServiceToken->getIsActive(),
            DBField::UPDATER_ID => $payoutServiceToken->getUpdaterId(),
            DBField::UPDATE_TIME  => $payoutServiceToken->getUpdateTime(),
            DBField::CREATOR_ID => $payoutServiceToken->getCreatorId(),
            DBField::CREATE_TIME => $payoutServiceToken->getCreateTime()
        ];

        $payoutServiceTokenHistory = $this->query($request->db)->createNewEntity($request, $payoutServiceTokenHistoryData, false);

        return $payoutServiceTokenHistory;
    }
}


class PayoutsFeesInvoicesManager extends BaseEntityManager {

    protected $entityClass = PayoutFeeInvoiceEntity::class;
    protected $table = Table::PayoutFeeInvoice;
    protected $table_alias = TableAlias::PayoutFeeInvoice;

    protected $pk = DBField::PAYOUT_FEE_INVOICE_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::PAYOUT_FEE_INVOICE_ID,
        DBField::PAYOUT_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::CURRENCY_ID,
        DBField::COUNTRY_ID,
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
     * @param PayoutFeeInvoiceEntity $data
     * @param Request $request
     * @return PayoutFeeInvoiceEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::PAYOUT_FEE_INVOICE_TRANSACTIONS, []);
        return $data;
    }

    /**
     * @param Request $request
     * @param PayoutEntity $payout
     * @return PayoutFeeInvoiceEntity
     */
    public function createNewPayoutFeeInvoice(Request $request, PayoutEntity $payout)
    {
        $payoutsFeesInvoicesTransactionsManager = $request->managers->payoutsFeesInvoicesTransactions();

        $payoutFeeInvoiceData = [
            DBField::PAYOUT_ID => $payout->getPk(),
            DBField::OWNER_TYPE_ID => $payout->getOwnerTypeId(),
            DBField::OWNER_ID => $payout->getOwnerId(),
            DBField::CURRENCY_ID => $payout->getCurrencyId(),
            DBField::COUNTRY_ID => $payout->getCountryId(),
            DBField::IS_ACTIVE => 1
        ];

        /** @var PayoutFeeInvoiceEntity $payoutFeeInvoice */
        $payoutFeeInvoice = $this->query($request->db)->createNewEntity($request, $payoutFeeInvoiceData, false);

        $payoutsFeesInvoicesTransactionsManager->createNewPayoutFeeInvoiceTransaction($request, $payout, $payoutFeeInvoice);

        $payout->setPayoutFeeInvoice($payoutFeeInvoice);

        return $payoutFeeInvoice;
    }

    /**
     * @param Request $request
     * @param $payoutIds
     * @return PayoutFeeInvoiceEntity[]
     */
    public function getPayoutFeeInvoicesByPayoutIds(Request $request, $payoutIds)
    {
        $payoutsFeesInvoicesTransactionsManager = $request->managers->payoutsFeesInvoicesTransactions();

        /** @var PayoutFeeInvoiceEntity[] $payoutFeeInvoices */
        $payoutFeeInvoices = $this->query($request->db)
            ->filter($this->filters->byPayoutId($payoutIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($payoutFeeInvoices) {
            /** @var PayoutFeeInvoiceEntity[] $payoutFeeInvoices */
            $payoutFeeInvoices = array_index($payoutFeeInvoices, $this->getPkField());

            $payoutFeeInvoiceTransactions = $payoutsFeesInvoicesTransactionsManager->getPayoutFeeInvoiceTransactionsByPayoutFeeInvoiceIds($request, array_keys($payoutFeeInvoices));
            foreach ($payoutFeeInvoiceTransactions as $payoutFeeInvoiceTransaction)
                $payoutFeeInvoices[$payoutFeeInvoiceTransaction->getPayoutFeeInvoiceId()]->setPayoutFeeInvoiceTransaction($payoutFeeInvoiceTransaction);
        }

        return $payoutFeeInvoices;
    }
}

class PayoutsFeesInvoicesTransactionsManager extends  BaseEntityManager {

    protected $entityClass = PayoutFeeInvoiceTransactionEntity::class;
    protected $table = Table::PayoutFeeInvoiceTransaction;
    protected $table_alias = TableAlias::PayoutFeeInvoiceTransaction;

    protected $pk = DBField::PAYOUT_FEE_INVOICE_TRANSACTION_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::PAYOUT_FEE_INVOICE_TRANSACTION_ID,
        DBField::PAYOUT_FEE_INVOICE_ID,
        DBField::DEBIT_CREDIT,
        DBField::LINE_TYPE,
        DBField::NET_AMOUNT,
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
     * @param Request $request
     * @param PayoutEntity $payout
     * @param PayoutFeeInvoiceEntity $payoutFeeInvoice
     * @return PayoutFeeInvoiceTransactionEntity
     */
    public function createNewPayoutFeeInvoiceTransaction(Request $request, PayoutEntity $payout, PayoutFeeInvoiceEntity $payoutFeeInvoice)
    {
        $paymentFeeInvoiceTransactionData = [
            DBField::PAYOUT_FEE_INVOICE_ID => $payoutFeeInvoice->getPk(),
            DBField::DEBIT_CREDIT => PaymentsInvoicesTransactionsManager::DEBIT,
            DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_TOTAL,
            DBField::NET_AMOUNT => $payout->getTransactionFee(),
            DBField::DISPLAY_NAME => $payout->getPayoutService()->getDisplayName(),
            DBField::IS_ACTIVE => 1
        ];

        /** @var PayoutFeeInvoiceTransactionEntity $payoutFeeInvoiceTransaction */
        $payoutFeeInvoiceTransaction = $this->query($request->db)->createNewEntity($request, $paymentFeeInvoiceTransactionData, false);

        $payoutFeeInvoice->setPayoutFeeInvoiceTransaction($payoutFeeInvoiceTransaction);

        return $payoutFeeInvoiceTransaction;
    }

    /**
     * @param Request $request
     * @param $payoutFeeInvoiceIds
     * @return PayoutFeeInvoiceTransactionEntity[]
     */
    public function getPayoutFeeInvoiceTransactionsByPayoutFeeInvoiceIds(Request $request, $payoutFeeInvoiceIds)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPayoutFeeInvoiceId($payoutFeeInvoiceIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }
}


class PayoutsInvoicesManager extends BaseEntityManager {

    protected $entityClass = PayoutInvoiceEntity::class;
    protected $table = Table::PayoutInvoice;
    protected $table_alias = TableAlias::PayoutInvoice;

    protected $pk = DBField::PAYOUT_INVOICE_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::PAYOUT_INVOICE_ID,
        DBField::PAYOUT_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::CURRENCY_ID,
        DBField::COUNTRY_ID,
        DBField::CODE,
        DBField::DIM_1,
        DBField::INVOICE_NO,
        DBField::ACCOUNTING_STATUS_ID,
        DBField::TRANSACTION_NUMBER,
        DBField::RESPONSE,
        DBField::ERROR,
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
     * @param PayoutInvoiceEntity $data
     * @param Request $request
     * @return PayoutInvoiceEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::PAYOUT_INVOICE_TRANSACTIONS, []);
        return $data;
    }

    /**
     * @param Request $request
     * @param PayoutEntity $payout
     * @return PayoutInvoiceEntity
     */
    public function createNewPayoutInvoice(Request $request, PayoutEntity $payout)
    {
        $payoutsInvoicesTransactionsManager = $request->managers->payoutsInvoicesTransactions();

        $payoutInvoiceData = [
            DBField::PAYOUT_ID => $payout->getPk(),
            DBField::OWNER_TYPE_ID => $payout->getOwnerTypeId(),
            DBField::OWNER_ID => $payout->getOwnerId(),
            DBField::CURRENCY_ID => $payout->getCurrencyId(),
            DBField::COUNTRY_ID => $payout->getCountryId(),
            DBField::ACCOUNTING_STATUS_ID => AccountingStatusesManager::STATUS_NEW,
            DBField::IS_ACTIVE => 1
        ];

        /** @var PayoutInvoiceEntity $payoutInvoice */
        $payoutInvoice = $this->query($request->db)->createNewEntity($request, $payoutInvoiceData, false);

        // Create Transaction Lines for each income record
        foreach ($payout->getIncomeRecords() as $income)
            $payoutsInvoicesTransactionsManager->createNewPayoutInvoiceTransactions($request, $payoutInvoice, $income);

        $payout->setPayoutInvoice($payoutInvoice);

        return $payoutInvoice;
    }

    /**
     * @param Request $request
     * @param $payoutIds
     * @return PayoutInvoiceEntity[]
     */
    public function getPayoutInvoicesByPayoutIds(Request $request, $payoutIds)
    {
        $payoutsInvoicesTransactionsManager = $request->managers->payoutsInvoicesTransactions();

        /** @var PayoutInvoiceEntity[] $payoutInvoices */
        $payoutInvoices = $this->query($request->db)
            ->filter($this->filters->byPayoutId($payoutIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($payoutInvoices) {
            /** @var PayoutInvoiceEntity[] $payoutInvoices */
            $payoutInvoices = array_index($payoutInvoices, $this->getPkField());

            $payoutInvoiceTransactions = $payoutsInvoicesTransactionsManager->getPayoutInvoiceTransactionsByPayoutInvoiceIds($request, array_keys($payoutInvoices));
            foreach ($payoutInvoiceTransactions as $payoutInvoiceTransaction)
                $payoutInvoices[$payoutInvoiceTransaction->getPayoutInvoiceId()]->setPayoutInvoiceTransaction($payoutInvoiceTransaction);
        }

        return $payoutInvoices;
    }
}

class PayoutsInvoicesTransactionsManager extends BaseEntityManager {

    protected $entityClass = PayoutInvoiceTransactionEntity::class;
    protected $table = Table::PayoutInvoiceTransaction;
    protected $table_alias = TableAlias::PayoutInvoiceTransaction;

    protected $pk = DBField::PAYOUT_INVOICE_TRANSACTION_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::PAYOUT_INVOICE_TRANSACTION_ID,
        DBField::PAYOUT_INVOICE_ID,
        DBField::INCOME_ID,
        DBField::DEBIT_CREDIT,
        DBField::LINE_TYPE,
        DBField::NET_AMOUNT,
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
     * @param Request $request
     * @param PayoutInvoiceEntity $payoutInvoice
     * @param IncomeEntity $income
     * @return PayoutInvoiceTransactionEntity[]
     */
    public function createNewPayoutInvoiceTransactions(Request $request, PayoutInvoiceEntity $payoutInvoice, IncomeEntity $income)
    {
        $payoutInvoiceTransactionsData = [];

        // Base Price Component
        $payoutInvoiceTransactionsData[] = [
            DBField::PAYOUT_INVOICE_ID => $payoutInvoice->getPk(),
            DBField::INVOICE_TRANSACTION_TYPE_ID => InvoicesTransactionsTypesManager::TYPE_MARKUP,
            DBField::DEBIT_CREDIT => $income->getNetBaseAmount() < 0 ? PaymentsInvoicesTransactionsManager::CREDIT : PaymentsInvoicesTransactionsManager::DEBIT,
            DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_DETAIL,
            DBField::NET_AMOUNT => abs($income->getNetBaseAmount()),
            DBField::DISPLAY_NAME => "{$income->getIncomeType()->getDisplayName()} / BASE",
            DBField::INCOME_ID => $income->getPk(),
            DBField::IS_ACTIVE => 1
        ];

        // Tax Price Component
        if ($income->has_tax()) {
            $payoutInvoiceTransactionsData[] = [
                DBField::PAYOUT_INVOICE_ID => $payoutInvoice->getPk(),
                DBField::INVOICE_TRANSACTION_TYPE_ID => InvoicesTransactionsTypesManager::TYPE_MARKUP,
                DBField::DEBIT_CREDIT => $income->getNetTaxAmount() < 0 ? PaymentsInvoicesTransactionsManager::CREDIT : PaymentsInvoicesTransactionsManager::DEBIT,
                DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_VAT,
                DBField::NET_AMOUNT => abs($income->getNetTaxAmount()),
                DBField::DISPLAY_NAME => "{$income->getIncomeType()->getDisplayName()} / TAX",
                DBField::INCOME_ID => $income->getPk(),
                DBField::IS_ACTIVE => 1
            ];

        }

        /** @var PayoutInvoiceTransactionEntity[] $payoutInvoiceTransactions */
        $payoutInvoiceTransactions = [];

        foreach ($payoutInvoiceTransactionsData as $payoutInvoiceTransactionData) {
            /** @var PayoutInvoiceTransactionEntity $payoutInvoiceTransaction */
            $payoutInvoiceTransaction = $this->query($request->db)->createNewEntity($request, $payoutInvoiceTransactionData, false);
            $payoutInvoice->setPayoutInvoiceTransaction($payoutInvoiceTransaction);
        }


        return $payoutInvoiceTransactions;
    }

    /**
     * @param Request $request
     * @param $payoutInvoiceIds
     * @return PayoutInvoiceTransactionEntity[]
     */
    public function getPayoutInvoiceTransactionsByPayoutInvoiceIds(Request $request, $payoutInvoiceIds)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPayoutInvoiceId($payoutInvoiceIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }
}