<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 6/10/17
 * Time: 4:22 PM
 */

class IncomeEntity extends DBManagerEntity {

    use
        hasIncomeStatusIdField,
        hasIncomeTypeIdField,
        hasPayoutIdField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasDisplayNameField,
        hasNetAmountField,
        hasTaxRateField,
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @return IncomeTypeEntity
     */
    public function getIncomeType()
    {
        return $this->field(VField::INCOME_TYPE);
    }

    /**
     * @param IncomeTypeEntity $incomeType
     */
    public function setIncomeType(IncomeTypeEntity $incomeType)
    {
        $this->updateField(VField::INCOME_TYPE, $incomeType);
    }

    /**
     * @return IncomeStatusEntity
     */
    public function getIncomeStatus()
    {
        return $this->field(VField::INCOME_STATUS);
    }

    /**
     * @param IncomeStatusEntity $incomeStatus
     */
    public function setIncomeStatus(IncomeStatusEntity $incomeStatus)
    {
        $this->updateField(VField::INCOME_STATUS, $incomeStatus);
    }


    /**
     * @param $netAmount
     * @return float
     */
    private function calculateTaxAmount($netAmount)
    {
        return ($netAmount / (100+$this->getTaxRate()));
    }

    /**
     * @return float
     */
    public function getNetBaseAmount()
    {
        if (!$this->has_tax())
            return $this->getNetAmount();
        else
            return $this->getNetAmount()-$this->getNetTaxAmount();
    }

    /**
     * @return float
     */
    public function getNetTaxAmount()
    {
        if (!$this->has_tax())
            return 0.0000;
        else
            return $this->calculateTaxAmount($this->getNetAmount());
    }

    /**
     * @return bool
     */
    public function is_pending()
    {
        return $this->getIncomeStatusId() == IncomeStatusesManager::STATUS_PENDING;
    }

    /**
     * @return bool
     */
    public function is_paid()
    {
        return $this->getIncomeStatusId() == IncomeStatusesManager::STATUS_PAID;
    }

    /**
     * @return bool
     */
    public function is_cancelled()
    {
        return $this->getIncomeStatusId() == IncomeStatusesManager::STATUS_CANCELLED;
    }
}

class IncomeStatusEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class IncomeTypeEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasIsPublicField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class IncomeContentSummaryEntity extends DBManagerEntity {

    use
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasIsOpenField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}