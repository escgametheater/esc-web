<?php

Entities::uses('payments');
class PaymentsManager extends BaseEntityManager {

    const TAX_RATE_PRODUCT_NYC = 8.8750;
    const TAX_RATE_SERVICE_NYC = 4.5;

    protected $entityClass = PaymentEntity::class;
    protected $table = Table::Payment;
    protected $table_alias = TableAlias::Payment;

    protected $pk = DBField::PAYMENT_ID;
    protected $foreign_managers = [
        PaymentsServicesManager::class => DBField::PAYMENT_SERVICE_ID,
        PaymentsStatusesManager::class => DBField::PAYMENT_STATUS_ID,
        OwnersPaymentsServicesTokensManager::class => DBField::OWNER_PAYMENT_SERVICE_TOKEN_ID
    ];

    public static $fields = [
        DBField::PAYMENT_ID,
        DBField::ORDER_ID,
        DBField::PAYMENT_SERVICE_ID,
        DBField::PAYMENT_STATUS_ID,
        DBField::OWNER_PAYMENT_SERVICE_TOKEN_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::CURRENCY_ID,
        DBField::COUNTRY_ID,
        DBField::PAYMENT_AMOUNT,
        DBField::TRANSACTION_FEE,
        DBField::PAYMENT_DATE,
        DBField::PAYMENT_MESSAGE,
        DBField::TRANSACTION_ID,
        DBField::AUTHORIZATION_ID,
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
     * @param PaymentEntity $data
     * @param Request $request
     * @return PaymentEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $adminCaptureUrl = $request->getWwwUrl("/admin/payments/capture/{$data->getPk()}");
        $data->updateField(VField::ADMIN_CAPTURE_URL, $adminCaptureUrl);
    }

    /**
     * @param Request $request
     * @return SqlQuery
     */
    public function queryJoinTokensServicesStatuses(Request $request)
    {
        $paymentsStatusesManager = $request->managers->paymentsStatuses();
        $paymentsServicesManager = $request->managers->paymentsServices();
        $usersPaymentsServicesTokensManager = $request->managers->ownersPaymentsServicesTokens();

        $fields = $this->selectAliasedManagerFields($paymentsStatusesManager, $paymentsServicesManager, $usersPaymentsServicesTokensManager);

        return $this->query($request->db)
            ->fields($fields)
            ->inner_join($usersPaymentsServicesTokensManager)
            ->inner_join($paymentsStatusesManager)
            ->inner_join($paymentsServicesManager)
            ->filter($this->filters->isActive());
    }


    /**
     * @param Request $request
     * @param $ownerTypeId
     * @param $ownerId
     * @param OrderEntity $order
     * @param OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken
     * @param bool $capture
     * @return PaymentEntity
     */
    public function createNewPayment(Request $request, $ownerTypeId, $ownerId, OrderEntity $order, OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken, $capture = true)
    {
        $ownersPaymentsServicesTokensLogsManager = $request->managers->ownersPaymentsServicesTokensLogs();
        $paymentsStatusesManager = $request->managers->paymentsStatuses();

        // Get PaymentService
        $paymentService = $ownerPaymentServiceToken->getOwnerPaymentService()->getPaymentService();

        // Charge the Payment Source to collect the payment before writing the payment record to DB.
        $paymentCharge = $paymentService->getPaymentServiceHandler()->chargePaymentSource($request, $order, $ownerPaymentServiceToken, $capture);

        // Get PaymentStatus
        $paymentStatus = $paymentsStatusesManager->getPaymentStatusById($request, $paymentCharge->getPaymentStatusId());

        if (!$countryId = $ownerPaymentServiceToken->getMeta('country_id'))
            $countryId = CountriesManager::ID_UNITED_STATES;

        $paymentData = [
            DBField::ORDER_ID => $order->getPk(),
            DBField::PAYMENT_SERVICE_ID => $paymentService->getPk(),
            DBField::PAYMENT_STATUS_ID => $paymentStatus->getPk(),
            DBField::OWNER_PAYMENT_SERVICE_TOKEN_ID => $ownerPaymentServiceToken->getPk(),
            DBField::OWNER_TYPE_ID => $ownerTypeId,
            DBField::OWNER_ID => $ownerId,
            DBField::CURRENCY_ID => 1,
            DBField::COUNTRY_ID => $countryId,
            DBField::PAYMENT_AMOUNT => $paymentCharge->getPaymentAmount(),
            DBField::TRANSACTION_FEE => $paymentCharge->getTransactionFee(),
            DBField::PAYMENT_DATE => $paymentCharge->getPaymentDate(),
            DBField::PAYMENT_MESSAGE => $paymentCharge->getPaymentMessage(),
            DBField::TRANSACTION_ID => $paymentCharge->getTransactionId(),
            DBField::AUTHORIZATION_ID => $paymentCharge->getAuthorizationId(),
            DBField::RESPONSE => $paymentCharge->getResponse(),
            DBField::ERROR => $paymentCharge->getError(),
            DBField::IS_ACTIVE => 1
        ];

        /** @var PaymentEntity $payment */
        $payment = $this->query($request->db)->createNewEntity($request, $paymentData, false);
        $payment->setPaymentService($paymentService)
            ->setOwnerPaymentServiceToken($ownerPaymentServiceToken)
            ->setPaymentStatus($paymentStatus);

        // Insert Payment Service Token Log Entry for this payment request
        $ownersPaymentsServicesTokensLogsManager->insertOwnerPaymentServiceTokenLog(
            $request,
            $ownerPaymentServiceToken,
            $order->getPk(),
            $payment->getPk(),
            $paymentCharge->getTransactionId(),
            $paymentCharge->getIsSuccess(),
            $paymentCharge->getPaymentMessage(),
            $paymentCharge->getResponse(),
            $paymentCharge->getParams()
        );

        $order->setPayment($payment);

        return $payment;
    }

    /**
     * @param Request $request
     * @param $paymentId
     * @return PaymentEntity
     */
    public function getPaymentById(Request $request, $paymentId)
    {
        /** @var PaymentEntity $payment */
        $payment = $this->queryJoinTokensServicesStatuses($request)
            ->filter($this->filters->byPk($paymentId))
            ->get_entity($request);

        $this->postProcessPayments($request, $payment);

        return $payment;
    }

    /**
     * @param Request $request
     * @param $orderIds
     * @return PaymentEntity[]
     */
    public function getPaymentsByOrderIds(Request $request, $orderIds)
    {
        /** @var PaymentEntity[] $payments */
        $payments = $this->queryJoinTokensServicesStatuses($request)
            ->filter($this->filters->byOrderId($orderIds))
            ->get_entities($request);

        return $this->postProcessPayments($request, $payments);
    }

    /**
     * @param Request $request
     * @param PaymentEntity|PaymentEntity[] $payments
     */
    public function postProcessPayments(Request $request, $payments)
    {
        $paymentsInvoicesManager = $request->managers->paymentsInvoices();
        $paymentsFeesInvoicesManager = $request->managers->paymentsFeesInvoices();
        $ownerPaymentServiceTokensManager = $request->managers->ownersPaymentsServicesTokens();

        if ($payments) {

            if ($payments instanceof PaymentEntity)
                $payments = [$payments];

            /** @var PaymentEntity[] $payments */
            $payments = array_index($payments, $this->getPkField());
            $paymentIds = array_keys($payments);

            // Payment Invoices and Transactions
            $paymentInvoices = $paymentsInvoicesManager->getPaymentInvoicesByPaymentIds($request, $paymentIds);
            foreach ($paymentInvoices as $invoice)
                $payments[$invoice->getPaymentId()]->setPaymentInvoice($invoice);

            $ownerPaymentServiceTokenIds = array_extract(DBField::OWNER_PAYMENT_SERVICE_TOKEN_ID, $payments);
            $ownerPaymentServiceTokens = $ownerPaymentServiceTokensManager->getOwnerPaymentServiceTokenByIds($request, $ownerPaymentServiceTokenIds);

            foreach ($payments as $payment) {
                if (array_key_exists($payment->getOwnerPaymentServiceTokenId(), $ownerPaymentServiceTokens))
                    $payment->setOwnerPaymentServiceToken($ownerPaymentServiceTokens[$payment->getOwnerPaymentServiceTokenId()]);
            }

            // Payment Fee Invoices and Transactions
            $paymentIdsWithFee = [];
            foreach ($payments as $payment) {
                if ($payment->getTransactionFee() > 0.0000)
                    $paymentIdsWithFee[] = $payment->getPk();
            }
            if ($paymentIdsWithFee) {
                $paymentFeeInvoices = $paymentsFeesInvoicesManager->getPaymentFeeInvoicesByPaymentIds($request, $paymentIdsWithFee);
                foreach ($paymentFeeInvoices as $invoice)
                    $payments[$invoice->getPaymentId()]->setPaymentFeeInvoice($invoice);

            }
        }

        return $payments;
    }

}

class PaymentsStatusesManager extends BaseEntityManager {

    const STATUS_AUTHORIZED = 1;
    const STATUS_FAILED = 2;
    const STATUS_PAID = 3;
    const STATUS_REVERSED = 4;
    const STATUS_CANCELLED = 5;

    protected $entityClass = PaymentStatusEntity::class;
    protected $table = Table::PaymentStatus;
    protected $table_alias = TableAlias::PaymentStatus;

    protected $pk = DBField::PAYMENT_STATUS_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::PAYMENT_STATUS_ID,
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
     * @param Request $request
     * @param $paymentStatusId
     * @return PaymentStatusEntity
     * @throws ObjectNotFound
     */
    public function getPaymentStatusById(Request $request, $paymentStatusId)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPk($paymentStatusId))
            ->filter($this->filters->isActive())
            ->get_entity($request);
    }
}


class PaymentsFeesInvoicesManager extends BaseEntityManager {

    protected $entityClass = PaymentFeeInvoiceEntity::class;
    protected $table = Table::PaymentFeeInvoice;
    protected $table_alias = TableAlias::PaymentFeeInvoice;

    protected $pk = DBField::PAYMENT_FEE_INVOICE_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::PAYMENT_FEE_INVOICE_ID,
        DBField::ORDER_ID,
        DBField::PAYMENT_ID,
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
     * @param PaymentFeeInvoiceEntity $data
     * @param Request $request
     * @return PaymentFeeInvoiceEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::PAYMENT_FEE_INVOICE_TRANSACTIONS, []);
        return $data;
    }

    /**
     * @param Request $request
     * @param PaymentEntity $payment
     * @return PaymentFeeInvoiceEntity
     */
    public function createNewPaymentFeeInvoice(Request $request, PaymentEntity $payment)
    {
        $paymentsFeesInvoicesTransactionsManager = $request->managers->paymentsFeesInvoicesTransactions();

        $paymentFeeInvoiceData = [
            DBField::ORDER_ID => $payment->getOrderId(),
            DBField::PAYMENT_ID => $payment->getPk(),
            DBField::OWNER_TYPE_ID => $payment->getOwnerTypeId(),
            DBField::OWNER_ID => $payment->getOwnerId(),
            DBField::CURRENCY_ID => $payment->getCurrencyId(),
            DBField::COUNTRY_ID => $payment->getCountryId(),
            DBField::IS_ACTIVE => 1
        ];

        /** @var PaymentFeeInvoiceEntity $paymentFeeInvoice */
        $paymentFeeInvoice = $this->query($request->db)->createNewEntity($request, $paymentFeeInvoiceData, false);

        $paymentsFeesInvoicesTransactionsManager->createNewPaymentFeeInvoiceTransaction($request, $payment, $paymentFeeInvoice);

        $payment->setPaymentFeeInvoice($paymentFeeInvoice);

        return $paymentFeeInvoice;
    }


    /**
     * @param Request $request
     * @param $paymentIds
     * @return PaymentFeeInvoiceEntity[]
     */
    public function getPaymentFeeInvoicesByPaymentIds(Request $request, $paymentIds)
    {
        $paymentsFeesInvoicesTransactionsManager = $request->managers->paymentsFeesInvoicesTransactions();

        /** @var PaymentFeeInvoiceEntity[] $paymentFeeInvoices */
        $paymentFeeInvoices = $this->query($request->db)
            ->filter($this->filters->byPaymentId($paymentIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);

        if ($paymentFeeInvoices) {
            /** @var PaymentFeeInvoiceEntity[ $paymentFeeInvoices */
            $paymentFeeInvoices = array_index($paymentFeeInvoices, $this->getPkField());
            $paymentFeeInvoiceIds = array_keys($paymentFeeInvoices);

            $paymentFeeInvoiceTransactions = $paymentsFeesInvoicesTransactionsManager->getPaymentFeeInvoiceTransactionsByPaymentFeeInvoiceIds($request, $paymentFeeInvoiceIds);
            foreach ($paymentFeeInvoiceTransactions as $transaction)
                $paymentFeeInvoices[$transaction->getPaymentFeeInvoiceId()]->setPaymentFeeInvoiceTransaction($transaction);
        }
        return $paymentFeeInvoices;
    }
}

class PaymentsFeesInvoicesTransactionsManager extends  BaseEntityManager {

    protected $entityClass = PaymentFeeInvoiceTransactionEntity::class;
    protected $table = Table::PaymentFeeInvoiceTransaction;
    protected $table_alias = TableAlias::PaymentFeeInvoiceTransaction;

    protected $pk = DBField::PAYMENT_FEE_INVOICE_TRANSACTION_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::PAYMENT_FEE_INVOICE_TRANSACTION_ID,
        DBField::PAYMENT_FEE_INVOICE_ID,
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
     * @param PaymentEntity $payment
     * @param PaymentFeeInvoiceEntity $paymentFeeInvoice
     * @param $transactionFee
     * @return PaymentFeeInvoiceTransactionEntity
     */
    public function createNewPaymentFeeInvoiceTransaction(Request $request, PaymentEntity $payment, PaymentFeeInvoiceEntity $paymentFeeInvoice)
    {
        $paymentFeeInvoiceTransactionData = [
            DBField::PAYMENT_FEE_INVOICE_ID => $paymentFeeInvoice->getPk(),
            DBField::DEBIT_CREDIT => PaymentsInvoicesTransactionsManager::DEBIT,
            DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_TOTAL,
            DBField::NET_AMOUNT => $payment->getTransactionFee(),
            DBField::DISPLAY_NAME => $payment->getPaymentService()->getDisplayName(),
            DBField::IS_ACTIVE => 1
        ];

        /** @var PaymentFeeInvoiceTransactionEntity $paymentFeeInvoiceTransaction */
        $paymentFeeInvoiceTransaction = $this->query($request->db)->createNewEntity($request, $paymentFeeInvoiceTransactionData, false);

        $paymentFeeInvoice->setPaymentFeeInvoiceTransaction($paymentFeeInvoiceTransaction);

        return $paymentFeeInvoiceTransaction;
    }

    /**
     * @param Request $request
     * @param $paymentFeeInvoiceIds
     * @return PaymentFeeInvoiceTransactionEntity[]
     */
    public function getPaymentFeeInvoiceTransactionsByPaymentFeeInvoiceIds(Request $request, $paymentFeeInvoiceIds)
    {
        return $this->query($request->db)
            ->filter($this->filters->byPaymentFeeInvoiceId($paymentFeeInvoiceIds))
            ->filter($this->filters->isActive())
            ->get_entities($request);
    }
}

class PaymentsInvoicesManager extends BaseEntityManager {

    protected $entityClass = PaymentInvoiceEntity::class;
    protected $table = Table::PaymentInvoice;
    protected $table_alias = TableAlias::PaymentInvoice;

    protected $pk = DBField::PAYMENT_INVOICE_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::PAYMENT_INVOICE_ID,
        DBField::ORDER_ID,
        DBField::PAYMENT_ID,
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
     * @param PaymentInvoiceEntity $data
     * @param Request $request
     * @return PaymentInvoiceEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::PAYMENT_INVOICE_TRANSACTIONS, []);
        return $data;
    }

    /**
     * @param Request $request
     * @param PaymentInvoiceEntity[] $paymentInvoices
     * @return PaymentInvoiceEntity[]
     */
    protected function postProcessInvoices(Request $request, $paymentInvoices)
    {
        $paymentsInvoicesTransactionsManager = $request->managers->paymentsInvoicesTransactions();
        if ($paymentInvoices) {
            /** @var PaymentInvoiceEntity[] $paymentInvoices */
            $paymentInvoices = array_index($paymentInvoices, $this->getPkField());
            $paymentInvoiceIds = array_keys($paymentInvoices);

            $paymentInvoicesTransactions = $paymentsInvoicesTransactionsManager->getPaymentInvoiceTransactionsByPaymentInvoiceIds($request, $paymentInvoiceIds);
            foreach ($paymentInvoicesTransactions as $transaction)
                $paymentInvoices[$transaction->getPaymentInvoiceId()]->setPaymentInvoiceTransaction($transaction);
        }

        return $paymentInvoices;
    }

    /**
     * @param Request $request
     * @param PaymentEntity $payment
     * @return PaymentInvoiceEntity
     */
    public function createNewPaymentInvoice(Request $request, OrderEntity $order)
    {
        $paymentsInvoicesTransactionsManager = $request->managers->paymentsInvoicesTransactions();

        $payment = $order->getPayment();

        $paymentInvoiceData = [
            DBField::ORDER_ID => $payment->getOrderId(),
            DBField::PAYMENT_ID => $payment->getPk(),
            DBField::OWNER_TYPE_ID => $payment->getOwnerTypeId(),
            DBField::OWNER_ID => $payment->getOwnerId(),
            DBField::CURRENCY_ID => $payment->getCurrencyId(),
            DBField::COUNTRY_ID => $payment->getCountryId(),
            DBField::ACCOUNTING_STATUS_ID => AccountingStatusesManager::STATUS_NEW,
            DBField::IS_ACTIVE => 1
        ];

        /** @var PaymentInvoiceEntity $paymentInvoice */
        $paymentInvoice = $this->query($request->db)->createNewEntity($request, $paymentInvoiceData, false);

        $incentive = null;
        if ($discountIncentiveInstance = $order->getDiscountIncentiveInstance())
            $incentive = $discountIncentiveInstance->getIncentive();

        // Create Transaction Lines for each Order Item and their Quantum
        foreach ($order->getOrderItems() as $orderItem)
            $paymentsInvoicesTransactionsManager->createNewPaymentInvoiceTransactions($request, $paymentInvoice, $orderItem, $incentive);

//        throw new Exception('pause');

        $payment->setPaymentInvoice($paymentInvoice);

        return $paymentInvoice;
    }


    /**
     * @param Request $request
     * @param $paymentIds
     * @return PaymentInvoiceEntity[]
     */
    public function getPaymentInvoicesByPaymentIds(Request $request, $paymentIds)
    {
        /** @var PaymentInvoiceEntity[] $paymentInvoices */
        $paymentInvoices = $this->query($request)
            ->filter($this->filters->byPaymentId($paymentIds))
            ->get_entities($request);

        return $this->postProcessInvoices($request, $paymentInvoices);
    }
}

class PaymentsInvoicesTransactionsManager extends BaseEntityManager {

    protected $entityClass = PaymentInvoiceTransactionEntity::class;
    protected $table = Table::PaymentInvoiceTransaction;
    protected $table_alias = TableAlias::PaymentInvoiceTransaction;

    protected $pk = DBField::PAYMENT_INVOICE_TRANSACTION_ID;
    protected $foreign_managers = [
        InvoicesTransactionsTypesManager::class => DBField::INVOICE_TRANSACTION_TYPE_ID
    ];

    public static $fields = [
        DBField::PAYMENT_INVOICE_TRANSACTION_ID,
        DBField::PAYMENT_INVOICE_ID,
        DBField::INVOICE_TRANSACTION_TYPE_ID,
        DBField::DEBIT_CREDIT,
        DBField::LINE_TYPE,
        DBField::NET_AMOUNT,
        DBField::DISPLAY_NAME,
        DBField::ORDER_ITEM_QUANTUM_ID,
        DBField::IS_ACTIVE,
        DBField::UPDATER_ID,
        DBField::UPDATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATE_TIME,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    const MAPPING_BASE = 'base';
    const MAPPING_TAX = 'tax';

    const DEBIT = 'debit';
    const CREDIT = 'credit';

    const LINE_TYPE_TOTAL = 'total';
    const LINE_TYPE_DETAIL = 'detail';
    const LINE_TYPE_VAT = 'vat';

    /**
     * @param Request $request
     * @return SqlQuery
     */
    protected function queryJoinTransactionTypes(Request $request)
    {
        $invoicesTransactionsTypesManager = $request->managers->invoicesTransactionsTypes();
        return $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($invoicesTransactionsTypesManager))
            ->inner_join($invoicesTransactionsTypesManager);
    }

    /**
     * @param Request $request
     * @param PaymentInvoiceEntity $paymentInvoice
     * @param OrderItemEntity $orderItem
     * @param IncentiveEntity|null $discountIncentive
     * @return PaymentInvoiceTransactionEntity[]
     */
    public function createNewPaymentInvoiceTransactions(Request $request, PaymentInvoiceEntity $paymentInvoice, OrderItemEntity $orderItem, IncentiveEntity $discountIncentive = null)
    {
        $paymentInvoiceTransactionsData = [];
        foreach ($orderItem->getOrderItemsQuantum() as $orderItemQuantum) {

            if ($orderItemQuantum->is_active()) {
                // Base Price Component
                $paymentInvoiceTransactionsData[] = [
                    DBField::PAYMENT_INVOICE_ID => $paymentInvoice->getPk(),
                    DBField::INVOICE_TRANSACTION_TYPE_ID => $orderItem->getOrderItemType()->getInvoiceTransactionTypeId(),
                    DBField::DEBIT_CREDIT => PaymentsInvoicesTransactionsManager::CREDIT,
                    DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_DETAIL,
                    DBField::NET_AMOUNT => $orderItem->getPriceBase(),
                    DBField::DISPLAY_NAME => "{$orderItem->getOrderItemType()->getDisplayName()} / BASE / PRICE",
                    DBField::ORDER_ITEM_QUANTUM_ID => $orderItemQuantum->getPk(),
                    DBField::IS_ACTIVE => 1
                ];

                if ($discountIncentive) {
                    // Base Price Discount Component
                    $paymentInvoiceTransactionsData[] = [
                        DBField::PAYMENT_INVOICE_ID => $paymentInvoice->getPk(),
                        DBField::INVOICE_TRANSACTION_TYPE_ID => $orderItem->getOrderItemType()->getInvoiceTransactionTypeId(),
                        DBField::DEBIT_CREDIT => PaymentsInvoicesTransactionsManager::DEBIT,
                        DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_DETAIL,
                        DBField::NET_AMOUNT => $discountIncentive->calculateDiscountValue($orderItem->getPriceBase()),
                        DBField::DISPLAY_NAME => "{$orderItem->getOrderItemType()->getDisplayName()} / BASE / PRICE / DISCOUNT",
                        DBField::ORDER_ITEM_QUANTUM_ID => $orderItemQuantum->getPk(),
                        DBField::IS_ACTIVE => 1
                    ];
                }

                if ($orderItem->has_tax()) {
                    // Tax Price Component
                    $paymentInvoiceTransactionsData[] = [
                        DBField::PAYMENT_INVOICE_ID => $paymentInvoice->getPk(),
                        DBField::INVOICE_TRANSACTION_TYPE_ID => $orderItem->getOrderItemType()->getInvoiceTransactionTypeId(),
                        DBField::DEBIT_CREDIT => PaymentsInvoicesTransactionsManager::CREDIT,
                        DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_VAT,
                        DBField::NET_AMOUNT => $orderItem->getPriceTax(),
                        DBField::DISPLAY_NAME => "{$orderItem->getOrderItemType()->getDisplayName()} / BASE / TAX",
                        DBField::ORDER_ITEM_QUANTUM_ID => $orderItemQuantum->getPk(),
                        DBField::IS_ACTIVE => 1
                    ];

                    if ($discountIncentive) {
                        // Tax Price Discount Component
                        $paymentInvoiceTransactionsData[] = [
                            DBField::PAYMENT_INVOICE_ID => $paymentInvoice->getPk(),
                            DBField::INVOICE_TRANSACTION_TYPE_ID => $orderItem->getOrderItemType()->getInvoiceTransactionTypeId(),
                            DBField::DEBIT_CREDIT => PaymentsInvoicesTransactionsManager::DEBIT,
                            DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_VAT,
                            DBField::NET_AMOUNT => $discountIncentive->calculateDiscountValue($orderItem->getPriceTax()),
                            DBField::DISPLAY_NAME => "{$orderItem->getOrderItemType()->getDisplayName()} / BASE / TAX / DISCOUNT",
                            DBField::ORDER_ITEM_QUANTUM_ID => $orderItemQuantum->getPk(),
                            DBField::IS_ACTIVE => 1
                        ];
                    }
                }

                if ($orderItem->has_markup()) {
                    // Markup Price Component
                    $paymentInvoiceTransactionsData[] = [
                        DBField::PAYMENT_INVOICE_ID => $paymentInvoice->getPk(),
                        DBField::INVOICE_TRANSACTION_TYPE_ID => InvoicesTransactionsTypesManager::TYPE_MARKUP,
                        DBField::DEBIT_CREDIT => PaymentsInvoicesTransactionsManager::CREDIT,
                        DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_DETAIL,
                        DBField::NET_AMOUNT => $orderItem->getMarkupBase(),
                        DBField::DISPLAY_NAME => "{$orderItem->getOrderItemType()->getDisplayName()} / MARKUP / PRICE",
                        DBField::ORDER_ITEM_QUANTUM_ID => $orderItemQuantum->getPk(),
                        DBField::IS_ACTIVE => 1
                    ];

                    if ($discountIncentive) {
                        // Markup Price Discount Component
                        $paymentInvoiceTransactionsData[] = [
                            DBField::PAYMENT_INVOICE_ID => $paymentInvoice->getPk(),
                            DBField::INVOICE_TRANSACTION_TYPE_ID => InvoicesTransactionsTypesManager::TYPE_MARKUP,
                            DBField::DEBIT_CREDIT => PaymentsInvoicesTransactionsManager::DEBIT,
                            DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_DETAIL,
                            DBField::NET_AMOUNT => $discountIncentive->calculateDiscountValue($orderItem->getMarkupBase()),
                            DBField::DISPLAY_NAME => "{$orderItem->getOrderItemType()->getDisplayName()} / MARKUP / PRICE / DISCOUNT",
                            DBField::ORDER_ITEM_QUANTUM_ID => $orderItemQuantum->getPk(),
                            DBField::IS_ACTIVE => 1
                        ];
                    }

                    if ($orderItem->has_tax()) {
                        // Markup Tax Component
                        $paymentInvoiceTransactionsData[] = [
                            DBField::PAYMENT_INVOICE_ID => $paymentInvoice->getPk(),
                            DBField::INVOICE_TRANSACTION_TYPE_ID => InvoicesTransactionsTypesManager::TYPE_MARKUP,
                            DBField::DEBIT_CREDIT => PaymentsInvoicesTransactionsManager::CREDIT,
                            DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_VAT,
                            DBField::NET_AMOUNT => $orderItem->getMarkupTax(),
                            DBField::DISPLAY_NAME => "{$orderItem->getOrderItemType()->getDisplayName()} / MARKUP / TAX",
                            DBField::ORDER_ITEM_QUANTUM_ID => $orderItemQuantum->getPk(),
                            DBField::IS_ACTIVE => 1
                        ];

                        if ($discountIncentive) {
                            // Markup Tax Component
                            $paymentInvoiceTransactionsData[] = [
                                DBField::PAYMENT_INVOICE_ID => $paymentInvoice->getPk(),
                                DBField::INVOICE_TRANSACTION_TYPE_ID => InvoicesTransactionsTypesManager::TYPE_MARKUP,
                                DBField::DEBIT_CREDIT => PaymentsInvoicesTransactionsManager::DEBIT,
                                DBField::LINE_TYPE => PaymentsInvoicesTransactionsManager::LINE_TYPE_VAT,
                                DBField::NET_AMOUNT => $discountIncentive->calculateDiscountValue($orderItem->getMarkupTax()),
                                DBField::DISPLAY_NAME => "{$orderItem->getOrderItemType()->getDisplayName()} / MARKUP / TAX / DISCOUNT",
                                DBField::ORDER_ITEM_QUANTUM_ID => $orderItemQuantum->getPk(),
                                DBField::IS_ACTIVE => 1
                            ];
                        }
                    }
                }
            }
        }
        $paymentInvoiceTransactions = [];
        foreach ($paymentInvoiceTransactionsData as $paymentInvoiceTransactionData) {
            /** @var PaymentInvoiceTransactionEntity $paymentInvoiceTransaction */
            $paymentInvoiceTransaction = $this->query($request->db)->createNewEntity($request, $paymentInvoiceTransactionData, false);
            $paymentInvoiceTransactions[$paymentInvoiceTransaction->getPk()] = $paymentInvoiceTransaction;
            $paymentInvoice->setPaymentInvoiceTransaction($paymentInvoiceTransaction);
        }
        return $paymentInvoiceTransactions;
    }

    /**
     * @param Request $request
     * @param $paymentInvoiceIds
     * @return PaymentInvoiceTransactionEntity[]
     */
    public function getPaymentInvoiceTransactionsByPaymentInvoiceIds(Request $request, $paymentInvoiceIds)
    {
        return $this->queryJoinTransactionTypes($request)
            ->filter($this->filters->byPaymentInvoiceId($paymentInvoiceIds))
            ->get_entities($request);
    }
}

class InvoicesTransactionsTypesManager extends BaseEntityManager {

    const TYPE_SUBSCRIPTION = 1;
    const TYPE_TAX = 2;
    const TYPE_ONE_TIME = 3;
    const TYPE_DONATION = 4;
    const TYPE_TRANSLATION = 5;
    const TYPE_FLAIR = 6;
    const TYPE_MARKUP = 7;
    const TYPE_PAYOUT_FEE = 8;
    const TYPE_DISCOUNT = 9;
    const TYPE_GIFT_CARD = 10;

    protected $entityClass = InvoiceTransactionTypeEntity::class;
    protected $table = Table::InvoiceTransactionType;
    protected $table_alias = TableAlias::InvoiceTransactionType;

    protected $pk = DBField::INVOICE_TRANSACTION_TYPE_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::INVOICE_TRANSACTION_TYPE_ID,
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