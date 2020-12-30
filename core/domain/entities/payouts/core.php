<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 6/10/17
 * Time: 4:17 PM
 */

require "settings.php";


class PayoutEntity extends DBManagerEntity {

    use
        hasPayoutServiceIdField,
        hasPayoutStatusIdField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasPayoutServiceTokenIdField,
        hasCurrencyIdField,
        hasCountryIdField,
        hasPayoutAmountField,
        hasTransactionFeeField,
        hasPayoutDateField,
        hasPayoutMessageField,
        hasTransactionIdField,
        hasAuthorizationIdField,
        hasTransactionNumberField,
        hasResponseField,
        hasErrorField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasPayoutServiceVirtualField;

    /**
     * @param IncomeEntity $income
     * @return $this
     */
    public function setIncomeRecord(IncomeEntity $income)
    {
        $this->dataArray[VField::INCOME_RECORDS][$income->getPk()] = $income;
        return $this;
    }

    /**
     * @param $incomeId
     * @return $this
     */
    public function removeIncomeRecord($incomeId)
    {
        if (isset($this->dataArray[VField::INCOME_RECORDS][$incomeId])) {
            unset($this->dataArray[VField::INCOME_RECORDS][$incomeId]);
        }
        return $this;
    }

    /**
     * @return IncomeEntity[]
     */
    public function getIncomeRecords()
    {
        return $this->field(VField::INCOME_RECORDS);
    }

    /**
     * @param PayoutFeeInvoiceEntity $payoutFeeInvoice
     */
    public function setPayoutFeeInvoice(PayoutFeeInvoiceEntity $payoutFeeInvoice)
    {
        return $this->updateField(VField::PAYOUT_FEE_INVOICE, $payoutFeeInvoice);
    }

    /**
     * @return PayoutFeeInvoiceEntity
     */
    public function getPayoutFeeInvoice()
    {
        return $this->field(VField::PAYOUT_FEE_INVOICE);
    }

    /**
     * @param PayoutInvoiceEntity $payoutInvoice
     */
    public function setPayoutInvoice(PayoutInvoiceEntity $payoutInvoice)
    {
        $this->updateField(VField::PAYOUT_INVOICE, $payoutInvoice);
    }

    /**
     * @return PayoutInvoiceEntity
     */
    public function getPayoutInvoice()
    {
        return $this->field(VField::PAYOUT_INVOICE);
    }

    /**
     * @param PayoutServiceTokenEntity $payoutServiceToken
     */
    public function setPayoutServiceToken(PayoutServiceTokenEntity $payoutServiceToken)
    {
        $this->updateField(VField::PAYOUT_SERVICE_TOKEN, $payoutServiceToken);
    }

    /**
     * @return PayoutServiceTokenEntity
     */
    public function getPayoutServiceToken()
    {
        return $this->field(VField::PAYOUT_SERVICE_TOKEN);
    }


    /**
     * @return float
     */
    public function getTotalNetIncomeAmount()
    {
        $netAmount = 0.0000;

        foreach ($this->getIncomeRecords() as $income) {
            if (!$income->is_cancelled())
                $netAmount += $income->getNetAmount();
        }

        return $netAmount;
    }

    /**
     * @return bool
     */
    public function is_paid()
    {
        return $this->getPayoutStatusId() == PayoutsStatusesManager::STATUS_PAID;
    }

    /**
     * @return bool
     */
    public function is_pending()
    {
        return $this->getPayoutStatusId() == PayoutsStatusesManager::STATUS_PENDING;
    }

    /**
     * @return bool
     */
    public function is_failed()
    {
        return $this->getPayoutStatusId() == PayoutsStatusesManager::STATUS_FAILED;
    }

    /**
     * @return int
     */
    public function getPayoutTotal()
    {
        return $this->getPayoutAmount();
    }
}


class PayoutFeeInvoiceEntity extends DBManagerEntity {

    use
        hasPayoutIdField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasCurrencyIdField,
        hasCountryIdField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param PayoutFeeInvoiceTransactionEntity $payoutFeeInvoiceTransaction
     */
    public function setPayoutFeeInvoiceTransaction(PayoutFeeInvoiceTransactionEntity $payoutFeeInvoiceTransaction)
    {
        $this->dataArray[VField::PAYOUT_FEE_INVOICE_TRANSACTIONS][$payoutFeeInvoiceTransaction->getPk()] = $payoutFeeInvoiceTransaction;
    }

    /**
     * @return mixed|null
     */
    public function getPayoutFeeInvoiceTransactions()
    {
        return $this->field(VField::PAYOUT_FEE_INVOICE_TRANSACTIONS);
    }
}

class PayoutFeeInvoiceTransactionEntity extends DBManagerEntity {

    use
        hasPayoutFeeInvoiceIdField,
        hasDebitCreditField,
        hasLineTypeField,
        hasNetAmountField,
        hasDisplayNameField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class PayoutInvoiceEntity extends DBManagerEntity {

    use
        hasPayoutIdField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasCurrencyIdField,
        hasCountryIdField,
        hasCodeField,
        hasDim1Field,
        hasInvoiceNoField,
        hasAccountingStatusIdField,
        hasTransactionNumberField,
        hasResponseField,
        hasErrorField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param PayoutInvoiceTransactionEntity $payoutInvoiceTransaction
     */
    public function setPayoutInvoiceTransaction(PayoutInvoiceTransactionEntity $payoutInvoiceTransaction)
    {
        $this->dataArray[VField::PAYOUT_INVOICE_TRANSACTIONS][$payoutInvoiceTransaction->getPk()] = $payoutInvoiceTransaction;
    }

    /**
     * @return PayoutInvoiceTransactionEntity[]
     */
    public function getPayoutInvoiceTransactions()
    {
        return $this->field(VField::PAYOUT_INVOICE_TRANSACTIONS);
    }
}


class PayoutInvoiceTransactionEntity extends DBManagerEntity {

    use
        hasPayoutInvoiceIdField,
        hasInvoiceTransactionTypeIdField,
        hasIncomeIdField,
        hasDebitCreditField,
        hasLineTypeField,
        hasNetAmountField,
        hasDisplayNameField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class PayoutServiceTokenEntity extends DBManagerEntity {

    /** @var  PayoutsServicesTokensManager $manager */
    protected $manager;

    use
        hasPayoutServiceIdField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasTokenField,
        hasRawMetaField,
        hasResponseField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasPayoutServiceVirtualField,
        hasMetaVirtualField;

    /**
     * @param Request $request
     */
    protected function _handle_after_save(Request $request)
    {
        $payoutsServicesTokensHistoryManager = $request->managers->payoutsServicesTokensHistory();

        $payoutsServicesTokensHistoryManager->insertPayoutServiceTokenHistory($request, $this);
    }
}

class PayoutServiceTokenHistoryEntity extends DBManagerEntity {

    use
        hasPayoutServiceTokenIdField,
        hasPayoutServiceIdField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasTokenField,
        hasRawMetaField,
        hasResponseField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}