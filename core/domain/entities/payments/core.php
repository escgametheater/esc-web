<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 6/10/17
 * Time: 4:11 PM
 */

require "settings.php";

class PaymentEntity extends DBManagerEntity {

    use
        hasOrderIdField,
        hasPaymentServiceIdField,
        hasPaymentStatusIdField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasOwnerPaymentServiceTokenIdField,
        hasCurrencyIdField,
        hasCountryIdField,
        hasPaymentAmountField,
        hasTransactionFeeField,
        hasPaymentDateField,
        hasPaymentMessageField,
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

        hasPaymentServiceVirtualField,
        hasOwnerPaymentServiceTokenVirtualField,
        hasVirtualAdminCaptureUrlField;

    /**
     * @param PaymentInvoiceEntity $paymentInvoice
     */
    public function setPaymentInvoice(PaymentInvoiceEntity $paymentInvoice)
    {
        $this->updateField(VField::PAYMENT_INVOICE, $paymentInvoice);
    }

    /**
     * @return PaymentInvoiceEntity|null
     */
    public function getPaymentInvoice()
    {
        return $this->field(VField::PAYMENT_INVOICE);
    }


    /**
     * @param PaymentFeeInvoiceEntity $paymentFeeInvoice
     */
    public function setPaymentFeeInvoice(PaymentFeeInvoiceEntity $paymentFeeInvoice)
    {
        $this->updateField(VField::PAYMENT_FEE_INVOICE, $paymentFeeInvoice);
    }

    /**
     * @return PaymentFeeInvoiceEntity|null
     */
    public function getPaymentFeeInvoice()
    {
        return $this->field(VField::PAYMENT_FEE_INVOICE);
    }

    /**
     * @param PaymentStatusEntity $paymentStatus
     * @return PaymentEntity
     */
    public function setPaymentStatus(PaymentStatusEntity $paymentStatus)
    {
        return $this->updateField(VField::PAYMENT_STATUS, $paymentStatus);
    }

    /**
     * @return PaymentStatusEntity
     */
    public function getPaymentStatus()
    {
        return $this->field(VField::PAYMENT_STATUS);
    }

    /**
     * @return bool
     */
    public function is_authorized()
    {
        return $this->getPaymentStatusId() == PaymentsStatusesManager::STATUS_AUTHORIZED;
    }

    /**
     * @return bool
     */
    public function is_paid()
    {
        return $this->getPaymentStatusId() == PaymentsStatusesManager::STATUS_PAID;
    }

    /**
     * @return bool
     */
    public function is_failed()
    {
        return $this->getPaymentStatusId() == PaymentsStatusesManager::STATUS_FAILED;
    }

    /**
     * @return bool
     */
    public function is_cancelled()
    {
        return $this->getPaymentStatusId() == PaymentsStatusesManager::STATUS_CANCELLED;
    }

    /**
     * @return bool
     */
    public function is_reversed()
    {
        return $this->getPaymentStatusId() == PaymentsStatusesManager::STATUS_REVERSED;
    }

    /**
     * @return bool
     */
    public function is_nullified()
    {
        return $this->is_cancelled() || $this->is_failed() || $this->is_reversed();
    }

    /**
     * @return int
     */
    public function getTotalPaymentAmount()
    {
        return $this->getPaymentAmount()+$this->getTransactionFee();
    }

}

class PaymentFeeInvoiceEntity extends DBManagerEntity {

    use
        hasOrderIdField,
        hasPaymentIdField,
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
     * @param PaymentFeeInvoiceTransactionEntity $paymentFeeInvoiceTransaction
     */
    public function setPaymentFeeInvoiceTransaction(PaymentFeeInvoiceTransactionEntity $paymentFeeInvoiceTransaction)
    {
        $this->dataArray[VField::PAYMENT_FEE_INVOICE_TRANSACTIONS][$paymentFeeInvoiceTransaction->getPk()] = $paymentFeeInvoiceTransaction;
    }

    /**
     * @return PaymentFeeInvoiceTransactionEntity[]
     */
    public function getPaymentFeeInvoiceTransactions()
    {
        return $this->field(VField::PAYMENT_FEE_INVOICE_TRANSACTIONS);
    }
}

class PaymentFeeInvoiceTransactionEntity extends DBManagerEntity {

    use
        hasPaymentFeeInvoiceIdField,
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

class PaymentInvoiceEntity extends DBManagerEntity {

    use
        hasOrderIdField,
        hasPaymentIdField,
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
     * @param PaymentInvoiceTransactionEntity $paymentInvoiceTransaction
     */
    public function setPaymentInvoiceTransaction(PaymentInvoiceTransactionEntity $paymentInvoiceTransaction)
    {
        $this->dataArray[VField::PAYMENT_INVOICE_TRANSACTIONS][$paymentInvoiceTransaction->getPk()] = $paymentInvoiceTransaction;
    }

    /**
     * @return PaymentInvoiceTransactionEntity[]
     */
    public function getPaymentInvoiceTransactions()
    {
        return $this->field(VField::PAYMENT_INVOICE_TRANSACTIONS);
    }
}

class PaymentInvoiceTransactionEntity extends DBManagerEntity {

    use
        hasPaymentInvoiceIdField,
        hasInvoiceTransactionTypeIdField,
        hasDebitCreditField,
        hasLineTypeField,
        hasNetAmountField,
        hasDisplayNameField,
        hasOrderItemQuantumIdField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

