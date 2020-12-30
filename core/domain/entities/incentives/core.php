<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 7/11/19
 * Time: 5:04 PM
 */

class IncentiveEntity extends DBManagerEntity
{
    protected $appliedDiscount = 0;

    use
        hasIncentiveTypeIdField,
        hasTokenField,
        hasMaxAmountField,
        hasDiscountPercentageField,
        hasStartTimeField,
        hasEndTimeField,
        hasOriginalUsesField,
        hasRemainingUsesField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatorIdField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualCreatorUserField;

    /**
     * @param IncentiveTypeEntity $incentiveType
     */
    public function setIncentiveType(IncentiveTypeEntity $incentiveType)
    {
        $this->dataArray[VField::INCENTIVE_TYPE] = $incentiveType;
    }

    /**
     * @return IncentiveTypeEntity
     */
    public function getIncentiveType()
    {
        return $this->getVField(VField::INCENTIVE_TYPE);
    }


    /**
     * @param $netPrice
     * @return float|int|null
     */
    public function calculateDiscountValue($netPrice)
    {
        $discountedValue = 0;

        if (!$this->getMaxAmount() || ($this->getMaxAmount() && $this->getMaxAmount() < $this->appliedDiscount)) {
            if ($this->getDiscountPercentage()) {
                $discount = (100-$this->getDiscountPercentage())/100;
                $discountedValue = $netPrice-round($netPrice * $discount, 2);
            }

            if ($this->getMaxAmount() && $discountedValue) {
                if ($discountedValue > $this->getMaxAmount())
                    $discountedValue = $this->getMaxAmount();
            }

            $this->appliedDiscount = $this->appliedDiscount + $discountedValue;
        }

        return $discountedValue;
    }
}

class IncentiveTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatorIdField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @return bool
     */
    public function is_type_credit()
    {
        return $this->getPk() == IncentivesTypesManager::TYPE_STORE_CREDIT;
    }

    /**
     * @return bool
     */
    public function is_type_discount()
    {
        return $this->getPk() == IncentivesTypesManager::TYPE_MANUAL_DISCOUNT;
    }
}

class IncentiveInstanceEntity extends DBManagerEntity
{
    use
        hasIncentiveIdField,
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasAmountField,
        hasIsActiveField,
        hasCreateTimeField,
        hasCreatorIdField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualIncentiveField;

}