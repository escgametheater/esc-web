<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 6/10/17
 * Time: 4:20 PM
 */

class OrderStatusEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

}

class OrderItemStatusEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class OrderItemTypeEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasInvoiceTransactionTypeIdField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param $orderItemTypeId
     * @return int
     * @throws Exception
     */
    public function getIncomeTypeId()
    {
        if ($this->getPk() == OrdersItemsTypesManager::TYPE_GAMEDAY)
            return IncomeTypesManager::TYPE_REVENUE;
        elseif ($this->getPk() == OrdersItemsTypesManager::TYPE_CMS_SEATS)
            return IncomeTypesManager::TYPE_REVENUE;

        throw new Exception("IncomeTypeId not defined for OrderItemType: {$this->getPk()}");
    }
}