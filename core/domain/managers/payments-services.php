<?php

Entities::uses('payments');

class PaymentsServicesManager extends BaseEntityManager {

    const SERVICE_PAYPAL = 1;
    const SERVICE_STRIPE = 2;
    const SERVICE_COINBASE = 3;
    const SERVICE_ADYEN = 4;
    const SERVICE_INTERNAL = 5;
    const SERVICE_INCOME = 6;

    const DEFAULT_PAYMENT_SERVICE = self::SERVICE_STRIPE;

    const GNS_KEY_PREFIX = '.payments.services';

    protected $entityClass = PaymentServiceEntity::class;
    protected $table = Table::PaymentService;
    protected $table_alias = TableAlias::PaymentService;

    protected $pk = DBField::PAYMENT_SERVICE_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::PAYMENT_SERVICE_ID,
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

    /**
     * @return string
     */
    public function generateAllPaymentServicesCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return PaymentServiceEntity[]
     */
    public function getAllPaymentServices(Request $request)
    {
        return $this->query($request->db)->get_entities($request);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getAllActivePaymentServices(Request $request)
    {
        $paymentServices = [];

        foreach ($this->getAllPaymentServices($request) as $paymentService) {
            if ($paymentService->is_active())
                $paymentServices[$paymentService->getPk()] = $paymentService;
        }

        return $paymentServices;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getAllPublicPaymentServices(Request $request)
    {
        $paymentServices = [];

        foreach ($this->getAllPaymentServices($request) as $paymentService) {
            if ($paymentService->is_active() && $paymentService->is_public())
                $paymentServices[$paymentService->getPk()] = $paymentService;
        }

        return $paymentServices;
    }

    /**
     * @param Request $request
     * @param $id
     * @param bool|true $public
     * @return PaymentServiceEntity|array
     */
    public function getPaymentServiceById(Request $request, $id, $public = true)
    {
        $paymentServices = $public
            ? $this->getAllPublicPaymentServices($request)
            : $this->getAllActivePaymentServices($request);

        if (isset($paymentServices[$id]))
            return $paymentServices[$id];
        else
            return [];
    }

    /**
     * @param $ownerTypeId
     * @param $ownerId
     * @return string
     */
    public function generateInternalPaymentServiceToken($ownerTypeId, $ownerId)
    {
        return $token = "internal-auth-{$ownerTypeId}-{$ownerId}";
    }

    /**
     * @param PaymentServiceEntity $paymentService
     * @return PaymentServiceInterface
     */
    public function getPaymentServiceHandlerForPaymentService(PaymentServiceEntity $paymentService)
    {
        Modules::load_helper('payments');

        /** @var PaymentServiceInterface $paymentServiceHandlerClass */
        $paymentServiceHandlerClass = "{$paymentService->getName()}PaymentServiceHandler";

        return new $paymentServiceHandlerClass($paymentService);
    }
}

class OwnerPaymentsServicesManager extends BaseEntityManager {

    protected $entityClass = OwnerPaymentServiceEntity::class;
    protected $table = Table::OwnerPaymentService;
    protected $table_alias = TableAlias::OwnerPaymentService;

    protected $pk = DBField::OWNER_PAYMENT_SERVICE_ID;
    protected $foreign_managers = [
        PaymentsServicesManager::class => DBField::PAYMENT_SERVICE_ID
    ];

    public static $fields = [
        DBField::OWNER_PAYMENT_SERVICE_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::PAYMENT_SERVICE_ID,
        DBField::PAYMENT_SERVICE_CUSTOMER_KEY,
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
     * @return SQLQuery
     */
    protected function queryJoinPaymentServices(Request $request)
    {
        $paymentsServicesManager = $request->managers->paymentsServices();

        return $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($paymentsServicesManager))
            ->inner_join($paymentsServicesManager);
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param PaymentServiceEntity $paymentService
     * @param $paymentServiceCustomerKey
     * @return OwnerPaymentServiceEntity
     */
    public function createOwnerPaymentService(Request $request, $ownerTypeId, $ownerId, PaymentServiceEntity $paymentService, $paymentServiceCustomerKey)
    {
        $ownerPaymentServiceData = [
            DBField::OWNER_TYPE_ID => $ownerTypeId,
            DBField::OWNER_ID => $ownerId,
            DBField::PAYMENT_SERVICE_ID => $paymentService->getPk(),
            DBField::PAYMENT_SERVICE_CUSTOMER_KEY => $paymentServiceCustomerKey,
            DBField::IS_ACTIVE => 1,
            DBField::CREATED_BY => $request->requestId,
        ];

        /** @var OwnerPaymentServiceEntity $ownerPaymentService */
        $ownerPaymentService = $this->query($request->db)->createNewEntity($request, $ownerPaymentServiceData, false);

        $ownerPaymentService->setPaymentService($paymentService);

        return $ownerPaymentService;
    }


    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param $paymentServiceId
     * @return array|OwnerPaymentServiceEntity
     */
    public function getOwnerPaymentService(Request $request, $ownerTypeId, $ownerId, $paymentServiceId)
    {
        return $this->queryJoinPaymentServices($request)
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->byPaymentServiceId($paymentServiceId))
            ->get_entity($request);
    }
}

class OwnersPaymentsServicesTokensManager extends BaseEntityManager {

    protected $entityClass = OwnerPaymentServiceTokenEntity::class;
    protected $table = Table::OwnerPaymentServiceToken;
    protected $table_alias = TableAlias::OwnerPaymentServiceToken;

    protected $pk = DBField::OWNER_PAYMENT_SERVICE_TOKEN_ID;
    protected $foreign_managers = [
        OwnerPaymentsServicesManager::class => DBField::OWNER_PAYMENT_SERVICE_ID
    ];

    public static $fields = [
        DBField::OWNER_PAYMENT_SERVICE_TOKEN_ID,
        DBField::OWNER_PAYMENT_SERVICE_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::IS_PRIMARY,
        DBField::IS_ACTIVE,
        DBField::TOKEN,
        DBField::FINGERPRINT,
        DBField::CLIENT_SECRET,
        DBField::TYPE,
        DBField::RAW_META,
        //DBField::RESPONSE, // Let's not get the response every time we get the token
        DBField::ADDRESS_ID,
        DBField::UPDATER_ID,
        DBField::UPDATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @param OwnerPaymentServiceTokenEntity $data
     * @param Request $request
     * @return array
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::META, json_decode($data->getRawMeta(), true));
        return $data;
    }

    /**
     * @param Request $request
     * @return SQLQuery
     */
    protected function queryJoinOwnerPaymentsServices(Request $request, $ownerId, $ownerTypeId = EntityType::USER, $activeOnly = true)
    {
        $ownersPaymentsServicesManager = $request->managers->ownersPaymentsServices();
        $paymentsServicesManager = $request->managers->paymentsServices();

        $queryBuilder = $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($ownersPaymentsServicesManager, $paymentsServicesManager))
            ->inner_join($ownersPaymentsServicesManager)
            ->inner_join($paymentsServicesManager, $ownersPaymentsServicesManager->filters->join($paymentsServicesManager))
            ->filter($ownersPaymentsServicesManager->filters->byOwnerTypeId($ownerTypeId))
            ->filter($ownersPaymentsServicesManager->filters->byOwnerId($ownerId))
            ->filter($this->filters->isActive());

        if ($activeOnly)
            $queryBuilder->filter($ownersPaymentsServicesManager->filters->isActive());

        return $queryBuilder;
    }


    /**
     * @param Request $request
     * @param OwnerPaymentServiceEntity $ownerPaymentService
     * @param $token
     * @param $type
     * @param bool|true $isPrimary
     * @param $response
     * @param array $rawMeta
     * @param null $fingerPrint
     * @param null $clientSecret
     * @return OwnerPaymentServiceTokenEntity
     */
    public function createNewOwnerPaymentServiceToken(Request $request, OwnerPaymentServiceEntity $ownerPaymentService, $token, $type,
                                                      $isPrimary = true, $response = null, $rawMeta = [], $fingerPrint = null, $clientSecret = null)
    {
        if ($isPrimary) {
            // Remove Primary Field for User Payment Service Tokens
            $this->query($request->db)
                ->filter($this->filters->byOwnerTypeId($ownerPaymentService->getOwnerTypeId()))
                ->filter($this->filters->byOwnerId($ownerPaymentService->getOwnerId()))
                ->update([DBField::IS_PRIMARY => 0]);

            // Deactivate other tokens of same type for the user payment service)
            $this->query($request->db)
                ->filter($this->filters->byOwnerPaymentServiceId($ownerPaymentService->getPk()))
                ->filter($this->filters->byType($type))
                ->update([DBField::IS_ACTIVE => 0]);
        }

        $userPaymentServiceTokenData = [
            DBField::OWNER_TYPE_ID => $ownerPaymentService->getOwnerTypeId(),
            DBField::OWNER_ID => $ownerPaymentService->getOwnerId(),
            DBField::OWNER_PAYMENT_SERVICE_ID => $ownerPaymentService->getPk(),
            DBField::IS_PRIMARY => $isPrimary ? 1 : 0,
            DBField::IS_ACTIVE => 1,
            DBField::TOKEN => $token,
            DBField::FINGERPRINT => $fingerPrint,
            DBField::CLIENT_SECRET => $clientSecret,
            DBField::TYPE => $type,
            DBField::RAW_META => json_encode($rawMeta),
            DBField::RESPONSE => serialize($response),
            DBField::ADDRESS_ID => null // Implement This Later
        ];

        /** @var OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken */
        $ownerPaymentServiceToken = $this->query($request->db)->createNewEntity($request, $userPaymentServiceTokenData, false);

        $ownerPaymentServiceToken->setOwnerPaymentService($ownerPaymentService);

        return $ownerPaymentServiceToken;

    }

    /**
     * @param Request $request
     * @param DBManagerEntity $user
     * @param $ownerPaymentServiceTokenId
     * @return OwnerPaymentServiceTokenEntity
     * @throws ObjectNotFound
     */
    public function getOwnerPaymentServiceTokenById(Request $request, DBManagerEntity $owner, $ownerPaymentServiceTokenId, $ownerTypeId = EntityType::USER)
    {
        return $this->queryJoinOwnerPaymentsServices($request, $owner->getPk(), $ownerTypeId)
            ->filter($this->filters->byPk($ownerPaymentServiceTokenId))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param $ownerPaymentServiceTokenId
     * @return array|OwnerPaymentServiceTokenEntity[]
     */
    public function getOwnerPaymentServiceTokenByIds(Request $request, $ownerPaymentServiceTokenIds)
    {
        $ownersPaymentsServicesManager = $request->managers->ownersPaymentsServices();
        $paymentsServicesManager = $request->managers->paymentsServices();

        $ownerPaymentServiceTokens = $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($ownersPaymentsServicesManager, $paymentsServicesManager))
            ->inner_join($ownersPaymentsServicesManager)
            ->inner_join($paymentsServicesManager, $ownersPaymentsServicesManager->filters->join($paymentsServicesManager))
            ->filter($this->filters->byPk($ownerPaymentServiceTokenIds))
            ->get_entities($request);

        return $this->index($ownerPaymentServiceTokens);
    }



    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param $token
     * @return array|OwnerPaymentServiceTokenEntity
     */
    public function getOwnerPaymentServiceTokenByToken(Request $request, $ownerTypeId, $ownerId, $token)
    {
        return $this->queryJoinOwnerPaymentsServices($request, $ownerId, $ownerTypeId)
            ->filter($this->filters->byToken($token))
            ->get_entity($request);
    }

    /**
     * @param Request $request
     * @param int $ownerTypeId
     * @param int $ownerId
     * @return OwnerPaymentServiceTokenEntity[]
     */
    public function getAllActiveOwnerPaymentServiceTokens(Request $request, $ownerTypeId, $ownerId, $index = false)
    {
        $ownersPaymentsServicesManager = $request->managers->ownersPaymentsServices();

        /** @var OwnerPaymentServiceTokenEntity[] $ownerPaymentServiceTokens */
        $ownerPaymentServiceTokens = $this->queryJoinOwnerPaymentsServices($request, $ownerId, $ownerTypeId)
            ->sort_asc(DBField::IS_PRIMARY)
            ->sort_asc($ownersPaymentsServicesManager->field(DBField::PAYMENT_SERVICE_ID))
            ->sort_desc(DBField::CREATE_TIME)
            ->get_entities($request);

        return $index ? array_index($ownerPaymentServiceTokens, $this->getPkField()) : $ownerPaymentServiceTokens;
    }

    /**
     * @param Request $request
     * @param OwnerPaymentServiceTokenEntity[] $ownerPaymentServiceTokens
     * @param $ownerTypeId
     * @param $ownerId
     * @param $countryId
     */
    public function checkCreateOwnerHasEarnedIncomePaymentService(Request $request, $ownerPaymentServiceTokens, $ownerTypeId, $ownerId, $countryId)
    {
        $paymentsServicesManager = $request->managers->paymentsServices();

        $hasIncomePaymentServiceMethod = false;

        foreach ($ownerPaymentServiceTokens as $userPaymentServiceToken) {
            if ($userPaymentServiceToken->getOwnerPaymentService()->getPaymentServiceId() == PaymentsServicesManager::SERVICE_INCOME)
                $hasIncomePaymentServiceMethod = true;
        }

        if (!$hasIncomePaymentServiceMethod) {
            // Auto-create income payment service for artists on donate modal creation
            $paymentsServicesManager->getPaymentServiceById($request, PaymentsServicesManager::SERVICE_INCOME)
                ->getPaymentServiceHandler()
                ->createPaymentSource($request, $ownerTypeId, $ownerId, "income-{$ownerTypeId}-{$ownerId}", $countryId);
        }

    }

    /**
     * @param Request $request
     * @param OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken
     */
    public function deactivateToken(Request $request, OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken)
    {
        $ownerPaymentServiceToken->updateField(DBField::IS_ACTIVE, 0);
        $ownerPaymentServiceToken->saveEntityToDb($request);
    }

}

class OwnersPaymentsServicesTokensLogsManager extends BaseEntityManager {

    const DIRECTION_INBOUND = 'inbound';
    const DIRECTION_OUTBOUND = 'outbound';

    protected $entityClass = OwnerPaymentServiceTokenLogEntity::class;
    protected $table = Table::OwnerPaymentServiceTokenLog;
    protected $table_alias = TableAlias::OwnerPaymentServiceTokenLog;

    protected $pk = DBField::OWNER_PAYMENT_SERVICE_TOKEN_LOG_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::OWNER_PAYMENT_SERVICE_TOKEN_LOG_ID,
        DBField::ORDER_ID,
        DBField::OWNER_PAYMENT_SERVICE_TOKEN_ID,
        DBField::PAYMENT_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::TRANSACTION_ID,
        DBField::IS_SUCCESSFUL,
        DBField::DIRECTION,
        DBField::CONTENT,
        DBField::RESPONSE,
        DBField::PARAMS,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @param Request $request
     * @param OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken
     * @param $orderId
     * @param $paymentId
     * @param $transactionId
     * @param bool|true $isSuccessful
     * @param string $direction
     * @param $content
     * @param $response
     * @param $params
     */
    public function insertOwnerPaymentServiceTokenLog(Request $request, OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken,
                                                      $orderId, $paymentId, $transactionId, $isSuccessful = true,
                                                      $content, $response, $params, $direction = self::DIRECTION_INBOUND)
    {
        $ownersPaymentServiceTokenLogData = [
            DBField::OWNER_TYPE_ID => $ownerPaymentServiceToken->getOwnerTypeId(),
            DBField::OWNER_ID => $ownerPaymentServiceToken->getOwnerId(),
            DBField::ORDER_ID => $orderId,
            DBField::OWNER_PAYMENT_SERVICE_TOKEN_ID => $ownerPaymentServiceToken->getPk(),
            DBField::PAYMENT_ID => $paymentId,
            DBField::TRANSACTION_ID => $transactionId,
            DBField::IS_SUCCESSFUL => $isSuccessful,
            DBField::DIRECTION => $direction,
            DBField::CONTENT => $content,
            DBField::RESPONSE => $response ? serialize($response) : null,
            DBField::PARAMS => $params ? json_encode($params) : null
        ];

        $this->query($request->db)->createNewEntity($request, $ownersPaymentServiceTokenLogData, false);
    }
}