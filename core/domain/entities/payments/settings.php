<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 6/10/17
 * Time: 4:11 PM
 */

class PaymentStatusEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class PaymentServiceEntity extends DBManagerEntity {

    /** @var  PaymentsServicesManager */
    protected $manager;

    protected $paymentServiceHandler;

    use
        hasNameField,
        hasDisplayNameField,
        hasIsPublicField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @return PaymentServiceInterface
     */
    public function getPaymentServiceHandler()
    {
        if (!$this->paymentServiceHandler)
            $this->paymentServiceHandler = $this->manager->getPaymentServiceHandlerForPaymentService($this);

        return $this->paymentServiceHandler;
    }

    /**
     * @return bool
     */
    public function is_type_internal()
    {
        return $this->getPk() == PaymentsServicesManager::SERVICE_INTERNAL;
    }
}

class InvoiceTransactionTypeEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class OwnerPaymentServiceEntity extends DBManagerEntity {

    use
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasPaymentServiceIdField,
        hasPaymentServiceCustomerKeyField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,
        hasPaymentServiceVirtualField;

    public function setOwnerPaymentServiceToken(OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken)
    {
        $this->dataArray[VField::OWNER_PAYMENT_SERVICE_TOKENS][$ownerPaymentServiceToken->getPk()] = $ownerPaymentServiceToken;
    }

    /**
     * @return OwnerPaymentServiceTokenEntity[]
     */
    public function getOwnerPaymentServiceTokens()
    {
        return $this->field(VField::OWNER_PAYMENT_SERVICE_TOKENS);
    }

    /**
     * @param $ownerPaymentServiceTokenId
     * @return OwnerPaymentServiceTokenEntity|array
     */
    public function getOwnerPaymentServiceTokenById($ownerPaymentServiceTokenId)
    {
        return isset($this->dataArray[VField::OWNER_PAYMENT_SERVICE_TOKENS][$ownerPaymentServiceTokenId]) ? $this->dataArray[VField::OWNER_PAYMENT_SERVICE_TOKENS][$ownerPaymentServiceTokenId] : [];
    }
}

class OwnerPaymentServiceTokenEntity extends DBManagerEntity {

    use
        hasOwnerIdField,
        hasOwnerTypeIdField,
        hasOwnerPaymentServiceIdField,
        hasIsActiveField,
        hasIsPrimaryField,
        hasTokenField,
        hasFingerprintField,
        hasClientSecretField,
        hasTypeField,
        hasRawMetaField,
        hasResponseField,
        hasAddressIdField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasMetaVirtualField;

    /**
     * @param OwnerPaymentServiceEntity $ownerPaymentService
     */
    public function setOwnerPaymentService(OwnerPaymentServiceEntity $ownerPaymentService)
    {
        $this->updateField(VField::OWNER_PAYMENT_SERVICE, $ownerPaymentService);
    }

    /**
     * @return OwnerPaymentServiceEntity
     */
    public function getOwnerPaymentService()
    {
        return $this->field(VField::OWNER_PAYMENT_SERVICE);
    }
}

class OwnerPaymentServiceTokenLogEntity extends DBManagerEntity {

    use
        hasOrderIdField,
        hasOwnerPaymentServiceTokenIdField,
        hasPaymentIdField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasTransactionIdField,
        hasIsSuccessfulField,
        hasDirectionField,
        hasContentField,
        hasResponseField,
        hasParamsField,
        hasCreateTimeField,
        hasCreatorIdField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}