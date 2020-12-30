<?php

class PayoutCharge {

    /** @var int  */
    protected $payoutStatusId;
    /** @var Bool */
    protected $isSuccess;

    protected $payoutAmount;
    protected $transactionFee;
    protected $payoutDate;
    protected $payoutMessage;
    protected $transactionId;
    protected $authorizationId;
    protected $params;
    protected $response;
    protected $error;

    /**
     * PaymentHelper constructor.
     * @param $payoutAmount
     * @param $transactionFee
     * @param $payoutDate
     * @param $payoutMessage
     * @param $transactionId
     * @param $authorizationId
     * @param $response
     * @param $error
     */
    public function __construct($payoutStatusId, $isSuccess, $payoutAmount, $transactionFee, $payoutDate, $payoutMessage,
                                $transactionId, $authorizationId, $params = [], $response, $error)
    {
        $this->payoutStatusId = $payoutStatusId;
        $this->isSuccess = $isSuccess;
        $this->payoutAmount = $payoutAmount;
        $this->transactionFee = $transactionFee;
        $this->payoutDate = $payoutDate;
        $this->payoutMessage = $payoutMessage;
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
     * @return int
     */
    public function getPayoutStatusId()
    {
        return $this->payoutStatusId;
    }

    /**
     * @return float
     */
    public function getPayoutAmount()
    {
        return $this->payoutAmount;
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
    public function getPayoutDate()
    {
        return $this->payoutDate;
    }

    /**
     * @return string
     */
    public function getPayoutMessage()
    {
        return $this->payoutMessage;
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
        return serialize($this->params);
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

class PayoutServiceException extends Exception {};

interface PayoutServiceInterface {

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param $authorizationKey
     * @return mixed
     */
    public function createPayoutSource(Request $request, $ownerTypeId, $ownerId, $token, $rawMeta);


    /**
     * @param Request $request
     * @param PayoutEntity $payout
     * @param IncomeEntity[] $pendingIncomeRecords
     * @return PayoutCharge
     */
    public function sendPayout(Request $request, PayoutEntity $payout, $pendingIncomeRecords = []);

    public function calculatePayoutFee($incomeRecords = [], $countryId = CountriesManager::ID_UNITED_STATES);

}

abstract class PayoutServiceHandler {

    const TRANSACTION_FEE_BASE = 0.00;
    const TRANSACTION_FEE_PERCENT = 0.00;

    /** @var PayoutServiceEntity */
    protected $payoutService;

    /**
     * PaymentServiceHandler constructor.
     * @param PaymentServiceEntity $payoutService
     */
    public function __construct(PayoutServiceEntity $payoutService)
    {
        $this->payoutService = $payoutService;
        $this->registerApiKeyStartupHook();
    }

    /**
     * @return PayoutServiceEntity
     */
    protected function getPayoutService()
    {
        return $this->payoutService;
    }

    /**
     * @param IncomeEntity[] $incomeRecords
     * @return float
     */
    protected function calculateNetPayoutAmount($incomeRecords = [])
    {
        $netAmount = 0.0000;

        foreach ($incomeRecords as $income) {
            if ($income->is_pending())
                $netAmount += $income->getNetAmount();
        }

        return $netAmount;
    }


    /**
     * @param bool $isSuccess
     * @return int
     */
    protected function getPayoutStatusId($isSuccess = false)
    {
        if ($isSuccess) {

            switch ($this->payoutService->getPk()) {
                case PayoutsServicesManager::SERVICE_MANUAL:
                    $payoutStatusId = PayoutsStatusesManager::STATUS_PENDING;
                    break;
                default:
                    $payoutStatusId = PayoutsStatusesManager::STATUS_PAID;
                    break;
            }
        } else {
            $payoutStatusId = PayoutsStatusesManager::STATUS_FAILED;
        }

        return $payoutStatusId;
    }

    /**
     * @param $apiSecretKey
     */
    abstract protected function registerApiKeyStartupHook();

}

class StripeCustomAccountPayoutServiceHandler extends PayoutServiceHandler implements PayoutServiceInterface {

    const TRANSACTION_FEE_BASE = 0.30;
    const TRANSACTION_FEE_PERCENT = 0.03;

    /**
     * @param $apiSecretKey
     */
    protected function registerApiKeyStartupHook()
    {
        GLOBAL $CONFIG;

        if ($CONFIG[GCConfiguration::IS_DEV])
            $apiSecretKey = $CONFIG['stripe']['test']['secret_key'];
        else
            $apiSecretKey = $CONFIG['stripe']['live']['secret_key'];

        \Stripe\Stripe::setApiKey($apiSecretKey);
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param $authorizationKey
     * @return mixed
     */
    public function createPayoutSource(Request $request, $ownerTypeId, $ownerId, $token, $rawMeta)
    {

    }


    /**
     * @param Request $request
     * @param PayoutEntity $payout
     * @param IncomeEntity $pendingIncomeRecords
     */
    public function sendPayout(Request $request, PayoutEntity $payout, $pendingIncomeRecords = [])
    {

    }

    /**
     * @param $totalAmount
     * @return float
     */
    public function calculatePayoutFee($incomeRecords = [], $countryId = CountriesManager::ID_UNITED_STATES)
    {
        $totalAmount = $this->calculateNetPayoutAmount($incomeRecords);
        return round((self::TRANSACTION_FEE_BASE + ($totalAmount * self::TRANSACTION_FEE_PERCENT)), 2);
    }


}

class PaypalPayoutServiceHandler extends PayoutServiceHandler implements PayoutServiceInterface {

    /** @var  \PayPal\Rest\ApiContext $apiContext */
    protected $apiContext;

    const TRANSACTION_FEE_US = 0.25;

    const TRANSACTION_FEE_BASE = 0;
    const TRANSACTION_FEE_PERCENT = 0.02;
    const TRANSACTION_FEE_CEILING = 20.00;

    /**
     * @param $apiSecretKey
     */
    protected function registerApiKeyStartupHook()
    {
        GLOBAL $CONFIG;

        if ($CONFIG[GCConfiguration::IS_DEV]) {
            $clientId = $CONFIG['paypal']['test']['client_id'];
            $secretKey = $CONFIG['paypal']['test']['secret_key'];
        } else {
            $clientId = $CONFIG['paypal']['test']['client_id'];
            $secretKey = $CONFIG['paypal']['test']['secret_key'];
        }

        $this->apiContext = new \PayPal\Rest\ApiContext(new \PayPal\Auth\OAuthTokenCredential($clientId, $secretKey));
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @param $authorizationKey
     * @return mixed
     */
    public function createPayoutSource(Request $request, $ownerTypeId, $ownerId, $token, $rawMeta)
    {
        $payoutsServicesTokensManager = $request->managers->payoutsServicesTokens();

        return $payoutsServicesTokensManager->createNewPayoutServiceTokenForOwner(
            $request,
            $this->getPayoutService(),
            $ownerTypeId,
            $ownerId,
            $token,
            $rawMeta
        );
    }


    /**
     * @param Request $request
     * @param PayoutEntity $payout
     * @param IncomeEntity[] $pendingIncomeRecords
     * @return PayoutCharge
     */
    public function sendPayout(Request $request, PayoutEntity $payout, $pendingIncomeRecords = [])
    {
        $payoutAmount = ($this->calculateNetPayoutAmount($pendingIncomeRecords)-$this->calculatePayoutFee($pendingIncomeRecords, $payout->getCountryId()));

        $payoutApi = new \PayPal\Api\Payout();

        $senderBatchHeader = new \PayPal\Api\PayoutSenderBatchHeader();
        $senderBatchHeader->setSenderBatchId($payout->getPk())
            ->setEmailSubject("You have received your GlobalComix Payout");

        $params = [
            'recipient_type' => 'EMAIL',
            'sender_item_id' => $payout->getPk(),
            'note' => 'Thank you for publishing with GlobalComix!',
            'receiver' => $payout->getPayoutServiceToken()->getToken(),
            'amount' => [
                'value' => $payoutAmount,
                'currency' => 'USD'
            ]
        ];

        $senderItem = new \PayPal\Api\PayoutItem($params);

        $payoutApi->setSenderBatchHeader($senderBatchHeader)->addItem($senderItem);

        $paypalRequest = clone $payoutApi;

        try {
            $output = $payoutApi->createSynchronous($this->apiContext);

            $transactionId = $output->items[0]->transaction_id;
            $transactionFeeAmount = $output->items[0]->payout_item_fee->value;
            $authorizationId = $output->items[0]->payout_item_id;
            $isSuccess = $output->items[0]->transaction_status == "SUCCESS";
            $payoutMessage = $output->items[0]->transaction_status;
            $response = $output;
            $error = null;

        } catch (PayPal\Exception\PayPalConnectionException $e) {

            $error = $e->getMessage();
            $response = $e->getData();

            $payoutAmount = 0.00;
            $transactionFeeAmount = 0.00;

            switch ($e->getCode()) {
                default:
                    $payoutMessage = null;
                case 422:
                    $payoutMessage = 'Insufficient Funds';
                    break;
            }

            $isSuccess = false;
            $transactionId = null;
            $authorizationId = null;

        } catch (Exception $e) {

            $error = $e->getMessage();
            $payoutAmount = 0.00;
            $transactionFeeAmount = 0.00;

            switch ($e->getCode()) {
                default:
                    $payoutMessage = null;
                case 422:
                    $payoutMessage = 'Insufficient Funds';
                    break;
            }

            $response = null;
            $isSuccess = false;
            $transactionId = null;
            $authorizationId = null;

        }

        return new PayoutCharge(
            $this->getPayoutStatusId($isSuccess),
            $isSuccess,
            $payoutAmount,
            $transactionFeeAmount,
            $request->getCurrentSqlTime(),
            $payoutMessage,
            $transactionId,
            $authorizationId,
            $paypalRequest,
            $response,
            $error
        );
    }



    /**
     * @param IncomeEntity[] $incomeRecords
     * @param string $countryId
     * @return float
     */
    public function calculatePayoutFee($incomeRecords = [], $countryId = CountriesManager::ID_UNITED_STATES)
    {
        $netAmount = $this->calculateNetPayoutAmount($incomeRecords);

        if (!$incomeRecords || $netAmount == 0)
            return 0;

        if ($countryId == CountriesManager::ID_UNITED_STATES) {
            return self::TRANSACTION_FEE_US;
        } else {
            $calculated = round((self::TRANSACTION_FEE_BASE + ($netAmount * self::TRANSACTION_FEE_PERCENT)), 2);
            return self::TRANSACTION_FEE_CEILING < $calculated ? self::TRANSACTION_FEE_CEILING : $calculated;
        }
    }
}

class ManualPayoutServiceHandler extends PayoutServiceHandler implements PayoutServiceInterface
{
    const TRANSACTION_FEE_US = 25;

    const TRANSACTION_FEE_BASE = 40;
    const TRANSACTION_FEE_PERCENT = 0.01;
    const TRANSACTION_FEE_CEILING = 50.00;

    public function registerApiKeyStartupHook()
    {
        // This is not used for manual payouts
    }

    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param $token
     * @param $rawMeta
     * @return PayoutServiceTokenEntity
     */
    public function createPayoutSource(Request $request, $ownerTypeId, $ownerId, $token, $rawMeta)
    {
        $payoutsServicesTokensManager = $request->managers->payoutsServicesTokens();

        return $payoutsServicesTokensManager->createNewPayoutServiceTokenForOwner(
            $request,
            $this->getPayoutService(),
            $ownerTypeId,
            $ownerId,
            $token,
            $rawMeta
        );
    }

    /**
     * @param Request $request
     * @param PayoutEntity $payout
     * @param array $pendingIncomeRecords
     * @return PayoutCharge
     */
    public function sendPayout(Request $request, PayoutEntity $payout, $pendingIncomeRecords = [])
    {
        $payoutServiceToken = $payout->getPayoutServiceToken();

        $isSuccess = true;

        $payoutAmount = ($this->calculateNetPayoutAmount($pendingIncomeRecords)-$this->calculatePayoutFee($pendingIncomeRecords, $payout->getCountryId()));
        $transactionFeeAmount = $this->calculatePayoutFee($pendingIncomeRecords, $payout->getCountryId());

        $transactionId = null;
        $authorizationId = null;
        $payoutMessage = null;
        $response = null;
        $error = null;

        $params = $payoutServiceToken->getMeta();

        return new PayoutCharge(
            $this->getPayoutStatusId($isSuccess),
            $isSuccess,
            $payoutAmount,
            $transactionFeeAmount,
            null,
            $payoutMessage,
            $transactionId,
            $authorizationId,
            $params,
            $response,
            $error
        );
    }

    /**
     * @param array $incomeRecords
     * @param string $countryId
     * @return float|int
     */
    public function calculatePayoutFee($incomeRecords = [], $countryId = CountriesManager::ID_UNITED_STATES)
    {
        $netAmount = $this->calculateNetPayoutAmount($incomeRecords);

        if (!$incomeRecords || $netAmount == 0)
            return 0;

        if ($countryId == CountriesManager::ID_UNITED_STATES) {
            return self::TRANSACTION_FEE_US;
        } else {
            $calculated = round((self::TRANSACTION_FEE_BASE + ($netAmount * self::TRANSACTION_FEE_PERCENT)), 2);
            return self::TRANSACTION_FEE_CEILING < $calculated ? self::TRANSACTION_FEE_CEILING : $calculated;
        }
    }
}