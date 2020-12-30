<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 6/10/17
 * Time: 4:19 PM
 */

require "settings.php";
require "placeholders.php";

class OrderEntity extends DBManagerEntity
{
    use
        hasOrderStatusIdField,
        hasOwnerTypeIdField,
        hasOwnerIdField,
        hasCountryIdField,
        hasNoteField,
        hasActivityIdField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param OrderItemEntity $orderItem
     */
    public function setOrderItem(OrderItemEntity $orderItem)
    {
        $this->dataArray[VField::ORDER_ITEMS][$orderItem->getPk()] = $orderItem;
    }

    /**
     * @return OrderItemEntity[]
     */
    public function getOrderItems()
    {
        return $this->dataArray[VField::ORDER_ITEMS];
    }

    /**
     * @param $id
     * @return OrderItemEntity
     */
    public function getOrderItemById($id)
    {
        return $this->dataArray[VField::ORDER_ITEMS][$id];
    }

    /**
     * @param PaymentEntity $payment
     */
    public function setPayment(PaymentEntity $payment)
    {
        $this->updateField(VField::PAYMENT, $payment);
    }

    /**
     * @return PaymentEntity
     */
    public function getPayment()
    {
        return $this->field(VField::PAYMENT);
    }

    /**
     * @return float
     */
    public function getSubTotalAmountAsFloat()
    {
        $subTotal = 0.00;

        foreach ($this->getOrderItems() as $orderItem) {
            if (!$orderItem->is_nullified()) {
                foreach ($orderItem->getOrderItemsQuantum() as $orderItemQuantum) {
                    if ($orderItemQuantum->is_active())
                        $subTotal = $subTotal + $orderItem->getPriceBase() + $orderItem->getMarkupBase();
                }
            }
        }

        return $subTotal;
    }

    /**
     * @return float
     */
    public function getSubTotalEscAmountAsFloat()
    {
        $subTotalGcAmount = 0.00;

        foreach ($this->getOrderItems() as $orderItem) {
            if (!$orderItem->is_nullified()) {
                foreach ($orderItem->getOrderItemsQuantum() as $orderItemQuantum) {
                    if ($orderItemQuantum->is_active())
                        $subTotalGcAmount = $subTotalGcAmount + $orderItem->getPriceBase();
                }
            }
        }

        return $subTotalGcAmount;
    }

    /**
     * @return float
     */
    public function getSubTotalMarkupAmountAsFloat()
    {
        $subTotalMarkupAmount = 0.00;

        foreach ($this->getOrderItems() as $orderItem) {
            if (!$orderItem->is_nullified()) {
                foreach ($orderItem->getOrderItemsQuantum() as $orderItemQuantum) {
                    if ($orderItemQuantum->is_active())
                        $subTotalMarkupAmount = $subTotalMarkupAmount + $orderItem->getMarkupBase();
                }
            }
        }

        return $subTotalMarkupAmount;
    }

    /**
     * @return float
     */
    public function getTaxAmountAsFloat()
    {
        $tax = 0.00;

        foreach ($this->getOrderItems() as $orderItem) {
            if (!$orderItem->is_nullified()) {
                foreach ($orderItem->getOrderItemsQuantum() as $orderItemQuantum) {
                    if ($orderItemQuantum->is_active()) {
                        $netTax = $orderItem->getPriceTax() + $orderItem->getMarkupTax();

                        $tax = $tax + $netTax;
                    }

                }
            }
        }

        return $tax;
    }

    /**
     * @return float
     */
    public function getTotalAmountAsFloat()
    {
        $total = $this->getSubTotalAmountAsFloat() + $this->getTaxAmountAsFloat();
        return (float) $total;
    }

    /**
     * @return float
     */
    public function getNetTotalAmountAsFloat()
    {
        return $this->getTotalAmountAsFloat() - $this->getDiscountAmountAsFloat();
    }

    /**
     * @return int
     */
    public function getTotalAmountAsInt()
    {
        $total = round($this->getTotalAmountAsFloat()*100);
        return (int)$total;
    }

    /**
     * @return float|int
     */
    public function getTotalAmountDueAsFloat()
    {
        $totalAmountDue = $this->getTotalAmountAsFloat() - $this->getDiscountAmountAsFloat();

        if ($this->is_failed() || $this->is_cancelled())
            return 0.00;

        if ($payment = $this->getPayment()) {

            if ($payment->is_paid())
                $totalAmountDue -= $payment->getTotalPaymentAmount();
        }

        return $totalAmountDue;
    }

    /**
     * @return int
     */
    public function getTotalAmountDueAsInt()
    {
        $total = round($this->getTotalAmountDueAsFloat()*100);
        return (int)$total;
    }


    /**
     * @return bool
     */
    public function is_cancelled()
    {
        return $this->getOrderStatusId() == OrdersStatusesManager::STATUS_CANCELLED;
    }

    /**
     * @return bool
     */
    public function is_refunded()
    {
        return $this->getOrderStatusId() == OrdersStatusesManager::STATUS_REFUNDED;
    }

    /**
     * @return bool
     */
    public function is_failed()
    {
        return $this->getOrderStatusId() == OrdersStatusesManager::STATUS_FAILED;
    }

    /**
     * @return bool
     */
    public function is_accepted()
    {
        return $this->getOrderStatusId() == OrdersStatusesManager::STATUS_ACCEPTED;
    }

    /**
     * @return bool
     */
    public function is_completed()
    {
        return $this->getOrderStatusId() == OrdersStatusesManager::STATUS_COMPLETED;
    }

    /**
     * @return bool
     */
    public function is_new()
    {
        return $this->getOrderStatusId() == OrdersStatusesManager::STATUS_NEW;
    }

    /**
     * @return bool
     */
    public function can_cancel()
    {
        return $this->getOrderStatusId() == OrdersStatusesManager::STATUS_NEW || $this->getOrderStatusId() == OrdersStatusesManager::STATUS_ACCEPTED;
    }

    /**
     * @return bool
     */
    public function has_multiple_order_item_quantity()
    {
        foreach ($this->getOrderItems() as $orderItem) {
            if ($orderItem->getQuantity() > 1)
                return true;
        }
        return false;
    }


    /**
     * @return float|null
     */
    public function getDiscountAmountAsFloat()
    {
        $totalDiscount = 0.00;

        if (!$this->is_cancelled()) {
            if ($discountIncentiveInstance = $this->getDiscountIncentiveInstance()) {

                $discountIncentive = $discountIncentiveInstance->getIncentive();

                foreach ($this->getOrderItems() as $orderItem) {
                    foreach ($orderItem->getOrderItemsQuantum() as $orderItemQuantum) {
                        if ($orderItemQuantum->is_active()) {
                            $netPrice = $discountIncentive->calculateDiscountValue($orderItem->getNetPrice());
                            $totalDiscount =  $totalDiscount + $netPrice;
                        }
                    }
                    $totalDiscount = $totalDiscount + $discountIncentive->calculateDiscountValue($orderItem->getNetTaxAmount());
                }

                if ($discountIncentive->getMaxAmount() && $totalDiscount > $discountIncentive->getMaxAmount())
                    $totalDiscount = $discountIncentive->getMaxAmount();
            }
        }

        return $totalDiscount;
    }

    /**
     * @return IncentiveInstanceEntity[]
     */
    public function getIncentiveInstances()
    {
        return $this->field(VField::INCENTIVES);
    }

    /**
     * @return array|IncentiveInstanceEntity
     */
    public function getDiscountIncentiveInstance()
    {
        foreach ($this->getIncentiveInstances() as $incentive) {
            if ($incentive->getIncentive()->getIncentiveType()->is_type_discount()) {
                return $incentive;
            }
        }

        return [];
    }

    /**
     * @param IncentiveInstanceEntity $incentiveInstance
     * @return IncentiveInstanceEntity
     */
    public function setIncentiveInstance(IncentiveInstanceEntity $incentiveInstance)
    {
        return $this->dataArray[VField::INCENTIVES][$incentiveInstance->getPk()] = $incentiveInstance;
    }
}

class OrderItemEntity extends DBManagerEntity {

    use
        hasOrderIdField,
        hasOrderItemStatusIdField,
        hasOrderItemTypeIdField,
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasMarkupOwnerTypeIdField,
        hasMarkupOwnerIdField,
        hasDisplayNameField,
        hasNetPriceField,
        hasNetMarkupField,
        hasTaxRateField,
        hasQuantityField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;

    /**
     * @param ServiceAccessTokenEntity|DBManagerEntity $contextEntity
     */
    public function setContext(DBManagerEntity $contextEntity)
    {
        $this->dataArray[VField::CONTEXT] = $contextEntity;
    }

    /**
     * @return ServiceAccessTokenEntity|null
     */
    public function getContext()
    {
        return $this->field(VField::CONTEXT);
    }

    /**
     * @param OrderItemTypeEntity $orderItemType
     */
    public function setOrderItemType(OrderItemTypeEntity $orderItemType)
    {
        return $this->updateField(VField::ORDER_ITEM_TYPE, $orderItemType);
    }

    /**
     * @return OrderItemTypeEntity
     */
    public function getOrderItemType()
    {
        return $this->field(VField::ORDER_ITEM_TYPE);
    }

    /**
     * @param OrderItemQuantumEntity $orderItemQuantum
     */
    public function setOrderItemQuantum(OrderItemQuantumEntity $orderItemQuantum)
    {
        $this->dataArray[VField::ORDER_ITEMS_QUANTUM][$orderItemQuantum->getPk()] = $orderItemQuantum;
    }

    /**
     * @return OrderItemQuantumEntity[]
     */
    public function getOrderItemsQuantum()
    {
        return $this->dataArray[VField::ORDER_ITEMS_QUANTUM];
    }

    /**
     * @param $orderItemQuantumId
     * @return OrderItemQuantumEntity
     */
    public function getOrderItemQuantumById($orderItemQuantumId)
    {
        return $this->dataArray[VField::ORDER_ITEMS_QUANTUM][$orderItemQuantumId];
    }

    /**
     * @param $netAmount
     * @return float
     */
    public function calculateTaxAmount($netAmount)
    {
        return round(($netAmount / 100) * $this->getTaxRate(), 2);
    }

    /**
     * @return float
     */
    public function getPriceBase()
    {
        return $this->getNetPrice();

        if (!$this->has_tax())
            return $this->getNetPrice();
        else
            return $this->getNetPrice()-$this->getPriceTax();
    }

    /**
     * @return float
     */
    public function getPriceTax()
    {
        if (!$this->has_tax())
            return 0.0000;
        else
            return $this->calculateTaxAmount($this->getNetPrice());
    }

    /**
     * @return float
     */
    public function getMarkupBase()
    {
        return $this->getNetMarkup();

        if (!$this->has_tax())
            return $this->getNetMarkup();
        else
            return $this->getNetMarkup()-$this->getMarkupTax();
    }

    /**
     * @return float
     */
    public function getMarkupTax()
    {
        if (!$this->has_tax())
            return 0.0000;
        else
            return $this->calculateTaxAmount($this->getNetMarkup());
    }

    /**
     * @return bool
     */
    public function has_markup()
    {
        return $this->getNetMarkup() > 0.0000 && $this->getMarkupOwnerId();
    }

    /**
     * @return bool
     */
    public function is_cancelled()
    {
        return $this->getOrderItemStatusId() == OrdersItemsStatusesManager::STATUS_CANCELLED;
    }

    /**
     * @return bool
     */
    public function is_completed()
    {
        return $this->getOrderItemStatusId() == OrdersItemsStatusesManager::STATUS_COMPLETED;
    }

    /**
     * @return bool
     */
    public function is_new()
    {
        return $this->getOrderItemStatusId() == OrdersItemsStatusesManager::STATUS_NEW;
    }

    /**
     * @return bool
     */
    public function is_accepted()
    {
        return $this->getOrderItemStatusId() == OrdersItemsStatusesManager::STATUS_ACCEPTED;
    }

    /**
     * @return bool
     */
    public function is_failed()
    {
        return $this->getOrderItemStatusId() == OrdersItemsStatusesManager::STATUS_FAILED;
    }

    /**
     * @return bool
     */
    public function is_refunded()
    {
        return $this->getOrderItemStatusId() == OrdersItemsStatusesManager::STATUS_REFUNDED;
    }

    /**
     * @return bool
     */
    public function is_nullified()
    {
        return $this->is_cancelled() || $this->is_failed();
    }



    /**
     * @return bool
     */
    public function can_complete()
    {
        return $this->is_new() || $this->is_accepted();
    }


    /**
     * @return float
     */
    public function getTotalNetMarkup()
    {
        $markup = 0.0000;
        foreach ($this->getOrderItemsQuantum() as $orderItemQuantum) {
            if ($orderItemQuantum->is_active())
                $markup += $this->getNetMarkup();
        }
        return $markup;
    }

    /**
     * @return float
     */
    public function getNetAmount()
    {
        return $this->getPriceBase() + $this->getPriceTax() + $this->getMarkupBase() + $this->getMarkupTax();
    }

    /**
     * @return float
     */
    public function getTotalNetAmount()
    {
        $totalNetPrice = 0.00;

        foreach ($this->getOrderItemsQuantum() as $orderItemQuantum) {
            if ($orderItemQuantum->is_active())
                $totalNetPrice = $totalNetPrice + ($this->getNetPrice() + $this->getNetMarkup());
        }

        return $totalNetPrice;
    }

    /**
     * @return float
     */
    public function getNetTaxAmount()
    {
        $totalTaxAmount = 0.00;
        if ($this->has_tax()) {
            foreach ($this->getOrderItemsQuantum() as $orderItemQuantum) {
                if ($orderItemQuantum->is_active())
                    $totalTaxAmount = $totalTaxAmount + ($this->getPriceTax() + $this->getMarkupTax());
            }
        }

        return $totalTaxAmount;
    }

    /**
     * @return float
     */
    public function getTotalAmount()
    {
        return $this->getTotalNetAmount() + $this->getNetTaxAmount();
    }

    /**
     * @return float
     */
    public function getSubTotalPrice()
    {
        $totalPrice = 0.00;
        foreach ($this->getOrderItemsQuantum() as $orderItemQuantum) {
            if ($orderItemQuantum->is_active())
                $totalPrice = $totalPrice + ($this->getPriceBase() + $this->getMarkupBase());
        }
        return $totalPrice;
    }

}

class OrderItemQuantumEntity extends DBManagerEntity {

    use
        hasOrderItemIdField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}