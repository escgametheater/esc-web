<?php


class PaymentCharge {

    protected $isSuccess;
    protected $isCaptured;
    protected $paymentAmount;
    protected $transactionFee;
    protected $paymentDate;
    protected $paymentMessage;
    protected $transactionId;
    protected $authorizationId;
    protected $params;
    protected $response;
    protected $error;

    /**
     * PaymentHelper constructor.
     * @param $paymentAmount
     * @param $transactionFee
     * @param $paymentDate
     * @param $paymentMessage
     * @param $transactionId
     * @param $authorizationId
     * @param $response
     * @param $error
     */
    public function __construct($isSuccess, $isCaptured, $paymentAmount, $transactionFee, $paymentDate, $paymentMessage,
                                $transactionId, $authorizationId, $params = [], $response, $error)
    {
        $this->isSuccess = $isSuccess;
        $this->isCaptured = $isCaptured;
        $this->paymentAmount = $paymentAmount;
        $this->transactionFee = $transactionFee;
        $this->paymentDate = $paymentDate;
        $this->paymentMessage = $paymentMessage;
        $this->transactionId = $transactionId;
        $this->authorizationId = $authorizationId;
        $this->params = $params;
        $this->response = $response;
        $this->error = $error;
    }


    /**
     * @return bool
     */
    public function getIsSuccess()
    {
        return $this->isSuccess;
    }

    /**
     * @return bool
     */
    public function getIsCaptured()
    {
        return $this->isCaptured;
    }

    /**
     * @return int
     */
    public function getPaymentStatusId()
    {
        if ($this->isSuccess) {
            if ($this->isCaptured)
                return PaymentsStatusesManager::STATUS_PAID;
            else
                return PaymentsStatusesManager::STATUS_AUTHORIZED;
        } else {
            return PaymentsStatusesManager::STATUS_FAILED;
        }
    }

    /**
     * @return float
     */
    public function getPaymentAmount()
    {
        return $this->paymentAmount;
    }

    /**
     * @return float
     */
    public function getTransactionFee()
    {
        return $this->transactionFee;
    }

    /**
     * @return string
     */
    public function getPaymentDate()
    {
        return $this->paymentDate;
    }

    /**
     * @return string
     */
    public function getPaymentMessage()
    {
        return $this->paymentMessage;
    }

    /**
     * @return string
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @return string
     */
    public function getAuthorizationId()
    {
        return $this->authorizationId;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return object
     */
    public function getResponse()
    {
        return serialize($this->response);
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

}

class PaymentServiceException extends Exception {};

interface PaymentServiceInterface {

    /**
     * @param Request $request
     * @param UserEntity|OrganizationEntity $owner
     * @param $authorizationKey
     * @return OwnerPaymentServiceTokenEntity
     */
    public function createPaymentSource(Request $request, DBManagerEntity $owner, $authorizationKey);

    /**
     * @param OrderEntity $order
     * @return PaymentCharge
     */
    public function chargePaymentSource(Request $request, OrderEntity $order, OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken, $capture = true);

    /**
     * @param OwnerPaymentServiceTokenEntity $userPaymentServiceToken
     * @return mixed
     */
    public function deleteOwnerPaymentToken(Request $request, OwnerPaymentServiceTokenEntity $userPaymentServiceToken);
}

abstract class PaymentServiceHandler {

    const TRANSACTION_FEE_BASE = 0.00;
    const TRANSACTION_FEE_PERCENT = 0.00;

    /** @var PaymentServiceEntity */
    protected $paymentService;

    /**
     * PaymentServiceHandler constructor.
     * @param PaymentServiceEntity $paymentService
     */
    public function __construct(PaymentServiceEntity $paymentService)
    {
        $this->paymentService = $paymentService;
        $this->registerApiKeyStartupHook();
    }

    /**
     * @param DBManagerEntity $owner
     * @return int|null
     */
    protected function getOwnerTypeId(DBManagerEntity $owner)
    {
        $ownerTypeId = null;
        if ($owner instanceof UserEntity)
            $ownerTypeId = EntityType::USER;
        if ($owner instanceof OrganizationEntity)
            $ownerTypeId = EntityType::ORGANIZATION;

        return $ownerTypeId;
    }

    /**
     * @return PaymentServiceEntity
     */
    protected function getPaymentService()
    {
        return $this->paymentService;
    }

    /**
     * @param $totalAmount
     * @return float
     */
    protected function calculateTransactionFee($totalAmount)
    {
        return round((static::TRANSACTION_FEE_BASE + ($totalAmount * static::TRANSACTION_FEE_PERCENT)), 4);
    }

    /**
     * @param $apiSecretKey
     */
    abstract protected function registerApiKeyStartupHook();

}

class StripeCardPaymentServiceHandler extends PaymentServiceHandler implements PaymentServiceInterface {

    const TRANSACTION_FEE_BASE = 0.30;
    const TRANSACTION_FEE_PERCENT = 0.029;

    /**
     * @param $apiSecretKey
     */
    protected function registerApiKeyStartupHook()
    {
        GLOBAL $CONFIG;

        if ($CONFIG[ESCConfiguration::IS_DEV])
            $apiSecretKey = $CONFIG['stripe']['test']['secret_key'];
        else
            $apiSecretKey = $CONFIG['stripe']['live']['secret_key'];

        \Stripe\Stripe::setApiKey($apiSecretKey);
    }


    /**
     * @param Request $request
     * @param UserEntity|OrganizationEntity $owner
     * @param $authorizationKey
     * @return OwnerPaymentServiceTokenEntity
     */
    public function createPaymentSource(Request $request, DBManagerEntity $owner, $authorizationKey)
    {
        $ownerPaymentsServicesManager = $request->managers->ownersPaymentsServices();
        $ownerPaymentsServicesTokensManager = $request->managers->ownersPaymentsServicesTokens();

        $ownerTypeId = $this->getOwnerTypeId($owner);
        $ownerId = $owner->getPk();

        $paymentService = $this->getPaymentService();

        // If we have a owner payment service for this user, let's create a new card instance with the Stripe customer.
        if ($ownerPaymentService = $ownerPaymentsServicesManager->getOwnerPaymentService($request, $ownerTypeId, $ownerId, $paymentService->getPk())) {

            $customer = \Stripe\Customer::retrieve($ownerPaymentService->getPaymentServiceCustomerKey());

            $stripeCard = $customer->sources->create([
                "source" => $authorizationKey
            ]);

            $customer->default_source = $stripeCard->id;
            $customer->save();

        // If we don't have a user payment service, we need to create the stripe customer along with the card instance.
        } else {
            // Create a Customer

            $customer = \Stripe\Customer::create([
                "source" => $authorizationKey,
                "description" => "{$owner->getFullName()} ({$owner->getUsername()})",
                "email" => $owner->getEmailAddress(),
                "metadata" => [
                    DBField::USER_ID => $owner->getPk(),
                    DBField::USERNAME => $owner->getUsername(),
                    DBField::COUNTRY_ID => $owner->getCountryId(),
                    DBField::FULL_NAME => $owner->getFullName(),
                ]
            ]);

            $ownerPaymentService = $ownerPaymentsServicesManager->createOwnerPaymentService($request, $ownerTypeId, $ownerId, $paymentService, $customer->id);

            $stripeCard = $customer->sources->retrieve($customer->default_source);
        }

        $rawMeta = [
            'brand' => $stripeCard->brand,
            'funding' => $stripeCard->funding,
            'last4' => $stripeCard->last4,
            'exp_month' => $stripeCard->exp_month,
            'exp_year' => $stripeCard->exp_year,
            'zip_code' => $stripeCard->address_zip,
            'card_name' => $stripeCard->name,
            'country_id' => strtolower($stripeCard->country),
        ];

        $userPaymentServiceToken = $ownerPaymentsServicesTokensManager->createNewOwnerPaymentServiceToken(
            $request,
            $ownerPaymentService,
            $stripeCard->id,
            $stripeCard->object,
            true,
            $customer->getLastResponse(),
            $rawMeta,
            $stripeCard->fingerprint
        );

        return $userPaymentServiceToken;
    }

    /**
     * @param Request $request
     * @param OrderEntity $order
     * @param OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken
     * @param bool|true $capture
     * @return PaymentCharge
     */
    public function chargePaymentSource(Request $request, OrderEntity $order, OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken, $capture = true)
    {
        $isSuccess = false;
        $paymentDate = $request->getCurrentSqlTime();
        $paymentAmount = $order->getTotalAmountDueAsFloat();
        $paymentMessage = null;
        $transactionId = null;
        $authorizationId = null;
        $response = null;
        $transactionFee = 0.00;
        $captured = false;

        $description = $order->getNote() ? $order->getNote() : "ESC Order ID {$order->getPk()}";

        $params = [
            "amount" => $order->getTotalAmountDueAsInt(),
            "currency" => "usd",
            "capture"   => $capture,
            "customer" => $ownerPaymentServiceToken->getOwnerPaymentService()->getPaymentServiceCustomerKey(),
            "description" => $description,
            "statement_descriptor" => "GlobalComix Order"
        ];
        try {


            $charge = \Stripe\Charge::create($params);
            $dateTime = new DateTime();
            $dateTime->setTimestamp($charge->created);

            $paymentDate = $dateTime->format($request->settings()->getSqlPostDateFormat());
            $transactionFee = $this->calculateTransactionFee($order->getTotalAmountDueAsFloat());
            $paymentAmount = (floatval($charge->amount)/100)-$transactionFee;
            $paymentMessage = $charge->status;
            $transactionId = $charge->balance_transaction;
            $authorizationId = $charge->id;
            $response = $charge->getLastResponse();
            $isSuccess = $charge->status == "succeeded";
            $captured = $charge->captured;

            $error = null;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        return new PaymentCharge($isSuccess, $captured, $paymentAmount, $transactionFee,
                                 $paymentDate, $paymentMessage, $transactionId, $authorizationId, $params, $response, $error);

    }

    /**
     * @param Request $request
     * @param OwnerPaymentServiceTokenEntity $userPaymentServiceToken
     * @return bool
     */
    public function deleteOwnerPaymentToken(Request $request, OwnerPaymentServiceTokenEntity $userPaymentServiceToken)
    {
        $ownersPaymentsServicesTokensManager = $request->managers->ownersPaymentsServicesTokens();
        $ownersPaymentsServicesTokensManagerLogs = $request->managers->ownersPaymentsServicesTokensLogs();

        $response = null;
        $params = null;
        $content = "Delete Token";

        $stripeCustomer = \Stripe\Customer::retrieve($userPaymentServiceToken->getOwnerPaymentService()->getPaymentServiceCustomerKey());
        $stripeCard = $stripeCustomer->sources->retrieve($userPaymentServiceToken->getToken());

        try {
            $stripeCard->delete();
            $success = true;

            $ownersPaymentsServicesTokensManager->deactivateToken($request, $userPaymentServiceToken);

        } catch (Exception $e) {
            $success = false;
        }

        $ownersPaymentsServicesTokensManagerLogs->insertOwnerPaymentServiceTokenLog(
            $request,
            $userPaymentServiceToken,
            null,
            null,
            $success,
            $content,
            $stripeCard->getLastResponse(),
            $params
        );

        return $success;
    }
}

class InternalPaymentServiceHandler extends PaymentServiceHandler implements PaymentServiceInterface {

    const TRANSACTION_FEE_BASE = 0.00;
    const TRANSACTION_FEE_PERCENT = 0.00;

    /**
     * DummyFunction
     * @return bool
     */
    protected function registerApiKeyStartupHook()
    {
        return true;
    }


    /**
     * @param Request $request
     * @param UserEntity|OrganizationEntity $owner
     * @param $authorizationKey
     * @return array|OwnerPaymentServiceTokenEntity
     */
    public function createPaymentSource(Request $request, DBManagerEntity $owner, $authorizationKey)
    {
        $ownerPaymentsServicesManager = $request->managers->ownersPaymentsServices();
        $ownerPaymentsServicesTokensManager = $request->managers->ownersPaymentsServicesTokens();

        $ownerTypeId = $this->getOwnerTypeId($owner);
        $ownerId = $owner->getPk();

        $paymentService = $this->getPaymentService();

        // If we don't have an instance of the internal payment service for this owner, we should create it.
        if (!$ownerPaymentService = $ownerPaymentsServicesManager->getOwnerPaymentService($request, $ownerTypeId, $ownerId, $paymentService->getPk())) {

            $ownerPaymentService = $ownerPaymentsServicesManager->createOwnerPaymentService($request, $ownerTypeId, $ownerId, $paymentService, $authorizationKey);
            $ownerPaymentService->setPaymentService($paymentService);
        }

        $rawMeta = [
            'country_id' => $owner->getCountryId(),
        ];

        // If we don't have a token for the manual payments, we should create it.
        if (!$ownerPaymentServiceToken = $ownerPaymentsServicesTokensManager->getOwnerPaymentServiceTokenByToken(
            $request,
            $ownerTypeId,
            $ownerId,
            $authorizationKey
        )) {

            $ownerPaymentServiceToken = $ownerPaymentsServicesTokensManager->createNewOwnerPaymentServiceToken(
                $request,
                $ownerPaymentService,
                $authorizationKey,
                'internal',
                true,
                [],
                $rawMeta,
                ''
            );
        }

        return $ownerPaymentServiceToken;
    }

    /**
     * @param Request $request
     * @param OrderEntity $order
     * @param OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken
     * @param bool|true $capture
     * @return PaymentCharge
     */
    public function chargePaymentSource(Request $request, OrderEntity $order, OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken, $capture = true)
    {
        $transactionFee = $this->calculateTransactionFee($order->getTotalAmountDueAsFloat());

        $paymentMessage = $capture ? 'Manual Payment Captured' : 'New Manual Payment Created';

        return new PaymentCharge(
            true,
            $capture,
            $order->getTotalAmountDueAsFloat() - $transactionFee,
            $transactionFee,
            $request->getCurrentSqlTime(),
            $paymentMessage,
            null,
            'manual-payment',
            ['capture' => $capture],
            null,
            null
        );
    }

    public function deleteOwnerPaymentToken(Request $request, OwnerPaymentServiceTokenEntity $userPaymentServiceToken)
    {
        // TODO: Implement deleteUserPaymentToken() method.
    }


}

class IncomePaymentServiceHandler extends PaymentServiceHandler implements PaymentServiceInterface {

    /**
     * DummyFunction
     * @return bool
     */
    protected function registerApiKeyStartupHook()
    {
        return true;
    }


    /**
     * @param Request $request
     * @param UserEntity|OrganizationEntity $owner
     * @param $authorizationKey
     * @return array|OwnerPaymentServiceTokenEntity
     */
    public function createPaymentSource(Request $request, DBManagerEntity $owner, $authorizationKey)
    {
        $userPaymentsServicesManager = $request->managers->ownersPaymentsServices();
        $userPaymentsServicesTokensManager = $request->managers->ownersPaymentsServicesTokens();

        $ownerId = $owner->getPk();
        $ownerTypeId = $this->getOwnerTypeId($owner);

        $paymentService = $this->getPaymentService();

        // If we don't have an instance of the internal payment service for this user, we should create it.
        if (!$ownerPaymentService = $userPaymentsServicesManager->getOwnerPaymentService($request, $ownerTypeId, $ownerId, $paymentService->getPk())) {

            $ownerPaymentService = $userPaymentsServicesManager->createOwnerPaymentService($request, $ownerTypeId, $ownerId, $paymentService, $authorizationKey);
            $ownerPaymentService->setPaymentService($paymentService);
        }

        $rawMeta = [
            'country_id' => $owner->getCountryId()
        ];

        // If we don't have a token for the manual payments, we should create it.
        if (!$ownerPaymentServiceToken = $userPaymentsServicesTokensManager->getOwnerPaymentServiceTokenByToken($request, $ownerTypeId, $ownerId, $authorizationKey)) {

            $ownerPaymentServiceToken = $userPaymentsServicesTokensManager->createNewOwnerPaymentServiceToken(
                $request,
                $ownerPaymentService,
                $authorizationKey,
                'internal',
                false,
                [],
                $rawMeta,
                ''
            );
        }

        return $ownerPaymentServiceToken;
    }

    /**
     * @param Request $request
     * @param OrderEntity $order
     * @param OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken
     * @param bool|true $capture
     * @return PaymentCharge
     */
    public function chargePaymentSource(Request $request, OrderEntity $order, OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken, $capture = true)
    {
        $incomeManager = $request->managers->income();

        $earnedIncomeRecords = $incomeManager->getAllUnclaimedPayableIncomeRecordsForOwner($request, $order->getOwnerTypeId(), $order->getOwnerId());

        $total = 0.00;

        foreach ($earnedIncomeRecords as $incomeRecord) {
            $total += $incomeRecord->getNetAmount();
        }

        if ($total > $order->getTotalAmountAsFloat()) {

            $incomeRecord = $incomeManager->createNewIncomeRecord(
                $request,
                IncomeTypesManager::TYPE_PAYMENT,
                $order->getOwnerTypeId(),
                $order->getOwnerId(),
                -$order->getTotalAmountDueAsFloat(),
                0.0000,
                EntityType::ORDER,
                $order->getPk(),
                "Order Payment"
            );
            $paymentMessage = 'Income Payment Accepted';
            $success = true;
        } else {
            $paymentMessage = "Insufficient Income Funds: {$total}";
            $success = false;
        }

        return new PaymentCharge(
            $success,
            $capture,
            $order->getTotalAmountDueAsFloat(),
            0.00,
            $request->getCurrentSqlTime(),
            $paymentMessage,
            null,
            'income-payment',
            null,
            null,
            null
        );
    }

    public function deleteOwnerPaymentToken(Request $request, OwnerPaymentServiceTokenEntity $userPaymentServiceToken)
    {
        // TODO: Implement deleteUserPaymentToken() method.
    }


}