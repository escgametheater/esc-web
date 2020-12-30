<?php

class OrderHelper {

    /** @var  OwnerPaymentServiceTokenEntity $ownerPaymentServiceToken */
    protected $ownerPaymentServiceToken;
    /** @var IncentiveEntity[] $incentive */
    protected $incentives = [];

    protected $ownerTypeId;
    protected $ownerId;

    /**
     * @var array|OrderItemPlaceholder[]
     */
    protected $orderItemPlaceholders = [];

    /**
     * OrderHelper constructor.
     * @param UserEntity $user
     */
    public function __construct($ownerTypeId, $ownerId)
    {
        $this->ownerTypeId = $ownerTypeId;
        $this->ownerId = $ownerId;
    }

    /**
     * @return int
     */
    public function getOwnerTypeId()
    {
        return $this->ownerTypeId;
    }

    /**
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * @param $orderItemTypeId
     * @param $quantity
     * @param $contextEntityTypeId
     * @param $contextEntityId
     */
    public function addOrderItem($orderItemTypeId, $contextEntityTypeId, $contextEntityId, $displayName, $quantity, $netPrice,
                                 $taxRate = 0.0000, $netMarkup = 0.0000, $markupOwnerTypeId = null, $markupOwnerId = null)
    {
        $orderItemData = [
            DBField::ORDER_ITEM_TYPE_ID => $orderItemTypeId,
            DBField::CONTEXT_ENTITY_TYPE_ID => $contextEntityTypeId,
            DBField::CONTEXT_ENTITY_ID => $contextEntityId,
            DBField::MARKUP_OWNER_TYPE_ID => $markupOwnerTypeId,
            DBField::MARKUP_OWNER_ID => $markupOwnerId,
            DBField::DISPLAY_NAME => $displayName,
            DBField::NET_PRICE => $netPrice,
            DBField::NET_MARKUP => $netMarkup,
            DBField::TAX_RATE => $taxRate,
            DBField::QUANTITY => $quantity,
        ];

        $this->orderItemPlaceholders[] = new OrderItemPlaceholder($orderItemData);
    }

    /**
     * @return array|OrderItemPlaceholder[]
     */
    public function getOrderItemPlaceholders()
    {
        return $this->orderItemPlaceholders;
    }

    /**
     * @param IncentiveEntity $incentive
     */
    public function addIncentive(IncentiveEntity $incentive)
    {
        $this->incentives[$incentive->getPk()] = $incentive;
    }

    /**
     * @return IncentiveEntity[]
     */
    public function getIncentives()
    {
        return $this->incentives;
    }

    /**
     * @param $netPrice
     * @param float $revenueSharePercent
     * @return float|int
     */
    public function calculateMarkupFee($netPrice, $revenueSharePercent = 0.75)
    {
        return round(($netPrice * $revenueSharePercent), 2);
    }

    /**
     * @param $netPrice
     * @param float $revenueSharePercent
     * @return float|int
     */
    public function calculateBaseAmount($netPrice, $revenueSharePercent = 0.75)
    {
        return $netPrice - $this->calculateMarkupFee($netPrice, $revenueSharePercent);
    }
}

