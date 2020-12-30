<?php

Entities::uses('orders');

class OrdersManager extends BaseEntityManager {

    protected $entityClass = OrderEntity::class;
    protected $table = Table::Order;
    protected $table_alias = TableAlias::Order;

    protected $pk = DBField::ORDER_ID;
    protected $foreign_managers = [
        OrdersStatusesManager::class => DBField::ORDER_STATUS_ID
    ];

    public static $fields = [
        DBField::ORDER_ID,
        DBField::ORDER_STATUS_ID,
        DBField::OWNER_TYPE_ID,
        DBField::OWNER_ID,
        DBField::COUNTRY_ID,
        DBField::NOTE,
        DBField::ACTIVITY_ID,
        DBField::IS_ACTIVE,
        DBField::UPDATE_TIME,
        DBField::UPDATER_ID,
        DBField::CREATE_TIME,
        DBField::CREATOR_ID,
        DBField::CREATED_BY,
        DBField::MODIFIED_BY,
        DBField::DELETED_BY,
    ];

    /**
     * @param OrderEntity $data
     * @param Request $request
     * @return OrderEntity
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        $data->updateField(VField::ORDER_ITEMS, []);
        $data->updateField(VField::ADMIN_EDIT_URL, $request->getWwwUrl("/admin/orders/manage/{$data->getPk()}/"));
        $data->updateField(VField::ADMIN_CANCEL_URL, $request->getWwwUrl("/admin/orders/cancel/{$data->getPk()}/"));
        $data->updateField(VField::INCENTIVES, []);

        return $data;
    }

    /**
     * @param Request $request
     * @param $userId
     * @param null $note
     * @return OrderEntity
     */
    public function createNewOrderEntity(Request $request, $ownerId, $ownerTypeId = EntityType::USER, $activityId = null, $note = null)
    {
        $orderData = [
            DBField::ORDER_STATUS_ID => OrdersStatusesManager::STATUS_NEW,
            DBField::OWNER_TYPE_ID => $ownerTypeId,
            DBField::OWNER_ID => $ownerId,
            DBField::ACTIVITY_ID => $activityId,
            DBField::NOTE => $note,
            DBField::ACTIVITY_ID => null,
            DBField::IS_ACTIVE => 1
        ];

        return $this->query($request->db)->createNewEntity($request, $orderData, false);
    }

    /**
     * @param Request $request
     * @return SqlQuery
     */
    public function queryJoinStatuses(Request $request)
    {
        $ordersStatusesManager = $request->managers->ordersStatuses();

        return $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($ordersStatusesManager))
            ->inner_join($ordersStatusesManager)
            ->filter($this->filters->isActive());
    }

    /**
     * @param OrderEntity $order
     * @return OrderEntity
     */
    protected function postProcessOrder(Request $request, $order)
    {
        if ($order)
            $this->postProcessOrders($request, [$order]);

        return $order;
    }

    /**
     * @param Request $request
     * @param $orders OrderEntity[]
     * @return OrderEntity[]
     */
    protected function postProcessOrders(Request $request, $orders)
    {
        $ordersItemsManager = $request->managers->ordersItems();
        $paymentsManager = $request->managers->payments();
        $incentivesInstancesManager = $request->managers->incentivesInstancesManager();

        if ($orders) {

            /** @var OrderEntity[] $orders */
            $orders = array_index($orders, $this->getPkField());
            $orderIds = array_keys($orders);

            // Get OrderItems and OrderItemsQuantum
            $orderItems = $ordersItemsManager->getOrderItemsByOrderId($request, $orderIds);
            foreach ($orderItems as $orderItem)
                $orders[$orderItem->getOrderId()]->setOrderItem($orderItem);

            // Get Payments, PaymentInvoices, and PaymentFeeInvoices
            $payments = $paymentsManager->getPaymentsByOrderIds($request, $orderIds);
            foreach ($payments as $payment)
                $orders[$payment->getOrderId()]->setPayment($payment);

            $incentiveInstances = $incentivesInstancesManager->getIncentiveInstancesByContext($request, EntityType::ORDER, $orderIds);
            foreach ($incentiveInstances as $incentiveInstance)
                $orders[$incentiveInstance->getContextEntityId()]->setIncentiveInstance($incentiveInstance);

        }

        return $orders;
    }

    /**
     * @param Request $request
     * @param OrderHelper $orderHelper
     * @param null $note
     * @return OrderEntity
     */
    public function createNewOrder(Request $request, OrderHelper $orderHelper, $activityId = null, $note = null)
    {
        $incentivesInstancesManager = $request->managers->incentivesInstancesManager();
        $ordersItemsManager = $request->managers->ordersItems();

        // Create New Order Entity
        $order = $this->createNewOrderEntity($request, $orderHelper->getOwnerId(), $orderHelper->getOwnerTypeId(), $activityId, $note);

        // Create and Add Order Items and Order Items Quantum to Order
        foreach ($orderHelper->getOrderItemPlaceholders() as $orderItemPlaceholder)
            $ordersItemsManager->createNewOrderItem($request, $order, $orderItemPlaceholder);

        foreach ($orderHelper->getIncentives() as $incentive) {
            $incentiveInstance = $incentivesInstancesManager->createNewIncentiveInstance(
                $request,
                $incentive->getPk(),
                EntityType::ORDER,
                $order->getPk()
            );
            $incentiveInstance->setIncentive($incentive);
            $order->setIncentiveInstance($incentiveInstance);
        }

        return $order;
    }

    /**
     * @param Request $request
     * @param $id
     * @param null $ownerId
     * @return OrderEntity
     * @throws ObjectNotFound
     */
    public function getOrderById(Request $request, $id, $ownerId = null, $ownerTypeId = EntityType::USER)
    {
        $queryBuilder = $this->queryJoinStatuses($request)
            ->filter($this->filters->byPk($id));

        if ($ownerId)
            $queryBuilder
                ->filter($this->filters->byOwnerId($ownerId))
                ->filter($this->filters->byOwnerTypeId($ownerTypeId));

        /** @var OrderEntity $order */
        $order = $queryBuilder->get_entity($request);

        return $this->postProcessOrder($request, $order);
    }

    /**
     * @param Request $request
     * @param $ownerId
     * @param int $page
     * @param int $perPage
     * @return OrderEntity[]
     */
    public function getOrdersByOwnerId(Request $request, $ownerId, $ownerTypeId = EntityType::USER, $page = 1, $perPage = DEFAULT_PERPAGE, $descending = true)
    {
        /** @var OrderEntity[] $orders */
        $queryBuilder = $this->queryJoinStatuses($request)
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->notByOrderStatusId(OrdersStatusesManager::STATUS_FAILED))
            ->paging($page, $perPage);
        if ($descending)
            $queryBuilder->sort_desc(DBField::CREATE_TIME);
        else
            $queryBuilder->sort_asc(DBField::CREATE_TIME);

        $orders = $queryBuilder->get_entities($request);

        return $this->postProcessOrders($request, $orders);
    }

    /**
     * @param Request $request
     * @param OrderEntity $order
     */
    public function markOrderAsAccepted(Request $request, OrderEntity $order)
    {
        // Mark Order as Complete
        $order->updateField(DBField::ORDER_STATUS_ID, OrdersStatusesManager::STATUS_ACCEPTED)->saveEntityToDb($request);

        // Mark OrderItems as Complete and create income records where applicable
        foreach ($order->getOrderItems() as $orderItem) {

            // Flag OrderItem as complete
            if ($orderItem->is_new())
                $orderItem->updateField(DBField::ORDER_ITEM_STATUS_ID, OrdersItemsStatusesManager::STATUS_ACCEPTED)->saveEntityToDb($request);
        }
    }

    /**
     * @param Request $request
     * @param OrderEntity $order
     */
    public function markOrderAsFailed(Request $request, OrderEntity $order)
    {
        // Mark Order as failed
        $order->updateField(DBField::ORDER_STATUS_ID, OrdersStatusesManager::STATUS_FAILED)->saveEntityToDb($request);

        // Mark OrderItems as Complete and create income records where applicable
        foreach ($order->getOrderItems() as $orderItem) {

            // Flag OrderItem as failed
            if ($orderItem->can_complete())
                $orderItem->updateField(DBField::ORDER_ITEM_STATUS_ID, OrdersItemsStatusesManager::STATUS_FAILED)->saveEntityToDb($request);
        }

        $order->getPayment()->updateField(DBField::PAYMENT_STATUS_ID, PaymentsStatusesManager::STATUS_FAILED)->saveEntityToDb($request);
    }


    /**
     * @param Request $request
     * @param OrderEntity $order
     */
    public function markOrderAsCancelled(Request $request, OrderEntity $order)
    {
        // Mark Order as Complete
        $order->updateField(DBField::ORDER_STATUS_ID, OrdersStatusesManager::STATUS_CANCELLED)->saveEntityToDb($request);

        // Mark OrderItems as Complete and create income records where applicable
        foreach ($order->getOrderItems() as $orderItem) {
            // Flag OrderItem as cancelled
            $orderItem->updateField(DBField::ORDER_ITEM_STATUS_ID, OrdersItemsStatusesManager::STATUS_CANCELLED)->saveEntityToDb($request);
        }

        if ($payment = $order->getPayment()) {
            $payment->updateField(DBField::PAYMENT_STATUS_ID, PaymentsStatusesManager::STATUS_CANCELLED)->saveEntityToDb($request);
        }

        foreach ($order->getIncentiveInstances() as $incentive) {
            // Do Noting for now
        }
    }

    /**
     * @param Request $request
     * @param OrderEntity $order
     */
    public function markOrderAsPaid(Request $request, OrderEntity $order)
    {
        $paymentsInvoicesManager = $request->managers->paymentsInvoices();
        $paymentsFeesInvoicesManager = $request->managers->paymentsFeesInvoices();
        $incomeManager = $request->managers->income();

        // Create Invoice and Transactions for this Payment
        $paymentsInvoicesManager->createNewPaymentInvoice($request, $order);

        // If this Payment has a Transaction Fee, create invoice and transactions for it.
        if ($order->getPayment()->getTransactionFee() > 0.0000)
            $paymentsFeesInvoicesManager->createNewPaymentFeeInvoice($request, $order->getPayment());

        // Mark Order as Complete
        $order->updateField(DBField::ORDER_STATUS_ID, OrdersStatusesManager::STATUS_COMPLETED)->saveEntityToDb($request);

        // Mark OrderItems as Complete and create income records where applicable
        foreach ($order->getOrderItems() as $orderItem) {

            // Flag OrderItem as complete
            if ($orderItem->can_complete()) {
                $orderItem->updateField(DBField::ORDER_ITEM_STATUS_ID, OrdersItemsStatusesManager::STATUS_COMPLETED)->saveEntityToDb($request);

                // Create Income Records from OrderItems where applicable
                if ($orderItem->has_markup() && $orderItem->is_completed()) {
                    $incomeManager->createNewIncomeRecord(
                        $request,
                        $orderItem->getOrderItemType()->getIncomeTypeId(),
                        $orderItem->getMarkupOwnerTypeId(),
                        $orderItem->getMarkupOwnerId(),
                        $orderItem->getTotalNetMarkup(),
                        $orderItem->getTaxRate(),
                        EntityType::ORDER_ITEM,
                        $orderItem->getPk(),
                        $orderItem->getDisplayName()
                    );
                }
            }
        }
    }

    /**
     * @param Request $request
     * @param UserEntity $user
     * @return int
     */
    public function getOrderCountForOwner(Request $request, $ownerId, $ownerTypeId = EntityType::USER)
    {
        return $this->query($request->db)
            ->filter($this->filters->byOwnerTypeId($ownerTypeId))
            ->filter($this->filters->byOwnerId($ownerId))
            ->filter($this->filters->isActive())
            ->count($this->getPkField());
    }

    /**
     * @param Request $request
     * @param int $page
     * @param int $perPage
     * @param null $orderStatusId
     * @return OrderEntity[]
     */
    public function getOrders(Request $request, $page = 1, $perPage = 40, $orderStatusId = null, $organizationId = null, $reverse = true)
    {
        $queryBuilder = $this->queryJoinStatuses($request)
            ->filter($this->filters->byOrderStatusId($orderStatusId))
            ->paging($page, $perPage);

        if ($organizationId)
            $queryBuilder->filter($this->filters->byOwnerTypeId(EntityType::ORGANIZATION))
                ->filter($this->filters->byOwnerId($organizationId));

        if ($reverse)
            $queryBuilder->sort_desc($this->getPkField());

        /** @var OrderEntity[] $orders */
        $orders = $queryBuilder->get_entities($request);

        return $this->postProcessOrders($request, $orders);
    }

    /**
     * @param Request $request
     * @param null $orderStatusId
     * @return int
     */
    public function getOrdersCount(Request $request, $orderStatusId = null, $organizationId = null)
    {
        $queryBuilder = $this->query($request->db)
            ->filter($this->filters->byOrderStatusId($orderStatusId));

        if ($organizationId)
            $queryBuilder->filter($this->filters->byOwnerTypeId(EntityType::ORGANIZATION))
                ->filter($this->filters->byOwnerId($organizationId));

        return $queryBuilder->count($this->getPkField());
    }

    /**
     * @param Request $request
     * @return int[]
     */
    public function getUniqueOrganizationIdsFromOrders(Request $request)
    {
        return $this->query($request->db)
            ->filter($this->filters->byOwnerTypeId(EntityType::ORGANIZATION))
            ->get_values(DBField::OWNER_ID, true, $request);
    }

}

class OrdersStatusesManager extends BaseEntityManager {

    const STATUS_NEW = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_CANCELLED = 4;
    const STATUS_FAILED = 5;
    const STATUS_REFUNDED = 6;
    const STATUS_REVERSED = 7;

    const GNS_KEY_PREFIX = GNS_ROOT.'.orders.statuses';

    protected $entityClass = OrderStatusEntity::class;
    protected $table = Table::OrderStatus;
    protected $table_alias = TableAlias::OrderStatus;

    protected $pk = DBField::ORDER_STATUS_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::ORDER_STATUS_ID,
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
     * @return string
     */
    public function generateAllOrderStatusesCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return OrderStatusEntity[]
     */
    public function getAllOrderStatuses(Request $request)
    {
        $orderStatuses = $this->query($request->db)
            ->local_cache($this->generateAllOrderStatusesCacheKey(), ONE_WEEK)
            ->get_entities($request);

        $orderStatuses = array_index($orderStatuses, $this->getPkField());

        return $orderStatuses;
    }

    /**
     * @param Request $request
     * @param $id
     * @return array|OrderStatusEntity
     */
    public function getOrderStatusById(Request $request, $id)
    {
        foreach ($this->getAllOrderStatuses($request) as $orderStatus) {
            if ($orderStatus->getPk() == $id)
                return $orderStatus;
        }
        return [];
    }
}

class OrdersItemsManager extends BaseEntityManager {

    protected $entityClass = OrderItemEntity::class;
    protected $table = Table::OrderItem;
    protected $table_alias = TableAlias::OrderItem;

    protected $pk = DBField::ORDER_ITEM_ID;

    protected $foreign_managers = [
        OrdersItemsStatusesManager::class => DBField::ORDER_ITEM_STATUS_ID,
        OrdersItemsTypesManager::class => DBField::ORDER_ITEM_TYPE_ID
    ];

    public static $fields = [
        DBField::ORDER_ITEM_ID,
        DBField::ORDER_ID,
        DBField::ORDER_ITEM_STATUS_ID,
        DBField::ORDER_ITEM_TYPE_ID,
        DBField::CONTEXT_ENTITY_TYPE_ID,
        DBField::CONTEXT_ENTITY_ID,
        DBField::MARKUP_OWNER_TYPE_ID,
        DBField::MARKUP_OWNER_ID,
        DBField::DISPLAY_NAME,
        DBField::NET_PRICE,
        DBField::NET_MARKUP,
        DBField::TAX_RATE,
        DBField::QUANTITY,
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
     * @param OrderItemEntity $data
     * @param Request $request
     */
    public function processVFields(DBManagerEntity $data, Request $request)
    {
        if (!$data->hasField(VField::ORDER_ITEMS_QUANTUM))
            $data->updateField(VField::ORDER_ITEMS_QUANTUM, []);
    }

    /**
     * @param Request $request
     * @return SqlQuery
     */
    protected function queryJoinStatusesTypes(Request $request)
    {
        $ordersItemsStatusesManager = $request->managers->ordersItemsStatuses();
        $ordersItemsTypesManager = $request->managers->ordersItemsTypes();

        return $this->query($request->db)
            ->fields($this->selectAliasedManagerFields($ordersItemsStatusesManager, $ordersItemsTypesManager))
            ->inner_join($ordersItemsStatusesManager)
            ->inner_join($ordersItemsTypesManager)
            ->filter($this->filters->isActive());
    }

    /**
     * @param Request $request
     * @param OrderEntity $order
     * @param OrderItemPlaceholder $orderItemPlaceholder
     * @return OrderItemEntity
     */
    public function createNewOrderItem(Request $request, OrderEntity $order, OrderItemPlaceholder $orderItemPlaceholder)
    {
        $ordersItemsTypesManager = $request->managers->ordersItemsTypes();
        $ordersItemsQuantumManager = $request->managers->ordersItemsQuantum();

        $orderItemType = $ordersItemsTypesManager->getOrderItemTypeById($request, $orderItemPlaceholder->getOrderItemTypeId());

        $orderItemData = [
            DBField::ORDER_ID => $order->getPk(),
            DBField::ORDER_ITEM_STATUS_ID => OrdersItemsStatusesManager::STATUS_NEW,
            DBField::ORDER_ITEM_TYPE_ID => $orderItemPlaceholder->getOrderItemTypeId(),
            DBField::CONTEXT_ENTITY_TYPE_ID => $orderItemPlaceholder->getContextEntityTypeId(),
            DBField::CONTEXT_ENTITY_ID => $orderItemPlaceholder->getContextEntityId(),
            DBField::MARKUP_OWNER_TYPE_ID => $orderItemPlaceholder->getMarkupOwnerTypeId(),
            DBField::MARKUP_OWNER_ID => $orderItemPlaceholder->getMarkupOwnerId(),
            DBField::DISPLAY_NAME => $orderItemPlaceholder->getDisplayName(),
            DBField::NET_PRICE => $orderItemPlaceholder->getNetPrice(),
            DBField::NET_MARKUP => $orderItemPlaceholder->getNetMarkup(),
            DBField::TAX_RATE => $orderItemPlaceholder->getTaxRate(),
            DBField::QUANTITY => $orderItemPlaceholder->getQuantity(),
            DBField::IS_ACTIVE => 1
        ];

        /** @var OrderItemEntity $orderItem */
        $orderItem = $this->query($request->db)->createNewEntity($request, $orderItemData, false);

        $orderItem->setOrderItemType($orderItemType);

        for ($i = 1; $i <= $orderItemPlaceholder->getQuantity(); $i++)
            $ordersItemsQuantumManager->createNewOrderItemQuantum($request, $orderItem);

        $order->setOrderItem($orderItem);


        return $orderItem;
    }

    /**
     * @param Request $request
     * @param OrderItemEntity[] $orderItems
     * @return OrderItemEntity[]
     */
    protected function postProcessOrderItems(Request $request, $orderItems)
    {
        $ordersItemsQuantumManager = $request->managers->ordersItemsQuantum();
        $serviceAccessTokensManager = $request->managers->servicesAccessTokens();

        if ($orderItems) {
            /** @var OrderItemEntity[] $orderItems */
            $orderItems = array_index($orderItems, $this->getPkField());

            $serviceAccessTokenIds = [];
            foreach ($orderItems as $orderItem) {
                if ($orderItem->getContextEntityTypeId() == EntityType::SERVICE_ACCESS_TOKEN)
                    $serviceAccessTokenIds[] = $orderItem->getContextEntityId();
            }

            $orderItemsQuantum = $ordersItemsQuantumManager->getOrderItemsQuantumByOrderItemIds($request, array_keys($orderItems));

            foreach ($orderItemsQuantum as $orderItemQuantum)
                $orderItems[$orderItemQuantum->getOrderItemId()]->setOrderItemQuantum($orderItemQuantum);

            $serviceAccessTokens = $serviceAccessTokensManager->getServiceAccessTokensByIds($request, $serviceAccessTokenIds);
            /** @var ServiceAccessTokenEntity[] $serviceAccessTokens */
            $serviceAccessTokens = $serviceAccessTokensManager->index($serviceAccessTokens);

            foreach ($orderItems as $orderItem) {
                if ($orderItem->getContextEntityTypeId() == EntityType::SERVICE_ACCESS_TOKEN && array_key_exists($orderItem->getContextEntityId(), $serviceAccessTokens))
                    $orderItem->setContext($serviceAccessTokens[$orderItem->getContextEntityId()]);
            }
        }

        return $orderItems;
    }

    /**
     * @param Request $request
     * @param OrderEntity $order
     * @return OrderItemEntity[]
     */
    public function getOrderItemsByOrderId(Request $request, $orderId)
    {
        /** @var OrderItemEntity[] $orderItems */
        $orderItems = $this->queryJoinStatusesTypes($request)
            ->filter($this->filters->byOrderId($orderId))
            ->get_entities($request);

        return $this->postProcessOrderItems($request, $orderItems);
    }

}

class OrdersItemsQuantumManager extends BaseEntityManager {

    protected $entityClass = OrderItemQuantumEntity::class;
    protected $table = Table::OrderItemQuantum;
    protected $table_alias = TableAlias::OrderItemQuantum;

    protected $pk = DBField::ORDER_ITEM_QUANTUM_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::ORDER_ITEM_QUANTUM_ID,
        DBField::ORDER_ITEM_ID,
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
     * @param OrderItemEntity $orderItem
     * @param $price
     * @return OrderItemQuantumEntity
     */
    public function createNewOrderItemQuantum(Request $request, OrderItemEntity $orderItem)
    {
        $orderItemQuantumData = [
            DBField::ORDER_ITEM_ID => $orderItem->getPk(),
            DBField::IS_ACTIVE => 1
        ];

        /** @var OrderItemQuantumEntity $orderItemQuantum */
        $orderItemQuantum = $this->query($request->db)->createNewEntity($request, $orderItemQuantumData, false);

        $orderItem->setOrderItemQuantum($orderItemQuantum);

        return $orderItemQuantum;
    }

    /**
     * @param Request $request
     * @param $orderItemIds
     * @return OrderItemQuantumEntity[]
     */
    public function getOrderItemsQuantumByOrderItemIds(Request $request, $orderItemIds)
    {
        return $this->query($request->db)
            ->filter($this->filters->byOrderItemId($orderItemIds))
            ->get_entities($request);
    }
}

class OrdersItemsStatusesManager extends BaseEntityManager {

    const STATUS_NEW = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_CANCELLED = 4;
    const STATUS_FAILED = 5;
    const STATUS_REFUNDED = 6;

    const GNS_KEY_PREFIX = GNS_ROOT.'.order-items.statuses';

    protected $entityClass = OrderItemStatusEntity::class;
    protected $table = Table::OrderItemStatus;
    protected $table_alias = TableAlias::OrderItemStatus;

    protected $pk = DBField::ORDER_ITEM_STATUS_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::ORDER_ITEM_STATUS_ID,
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
     * @return string
     */
    public function generateAllOrderItemStatusesCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return OrderItemStatusEntity[]
     */
    public function getAllOrderItemStatuses(Request $request)
    {
        return $this->query($request->db)
            ->local_cache($this->generateAllOrderItemStatusesCacheKey(), ONE_WEEK)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $id
     * @return array|OrderItemStatusEntity
     */
    public function getOrderItemStatusById(Request $request, $id)
    {
        foreach ($this->getAllOrderItemStatuses($request) as $orderItemStatus) {
            if ($orderItemStatus->getPk() == $id)
                return $orderItemStatus;
        }
        return [];
    }
}

class OrdersItemsTypesManager extends BaseEntityManager {

    const TYPE_GAMEDAY = 1;
    const TYPE_CMS_SEATS = 2;

    const GNS_KEY_PREFIX = GNS_ROOT.'.order-items.types';

    protected $entityClass = OrderItemTypeEntity::class;
    protected $table = Table::OrderItemType;
    protected $table_alias = TableAlias::OrderItemType;

    protected $pk = DBField::ORDER_ITEM_TYPE_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::ORDER_ITEM_TYPE_ID,
        DBField::NAME,
        DBField::DISPLAY_NAME,
        DBField::INVOICE_TRANSACTION_TYPE_ID,
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
     * @return string
     */
    public function generateAllOrderItemTypesCacheKey()
    {
        return self::GNS_KEY_PREFIX.'.all';
    }

    /**
     * @param Request $request
     * @return OrderItemTypeEntity[]
     */
    public function getAllOrderItemTypes(Request $request)
    {
        return $this->query($request->db)
            ->local_cache($this->generateAllOrderItemTypesCacheKey(), ONE_WEEK)
            ->get_entities($request);
    }

    /**
     * @param Request $request
     * @param $id
     * @return array|OrderItemTypeEntity
     */
    public function getOrderItemTypeById(Request $request, $id)
    {
        foreach ($this->getAllOrderItemTypes($request) as $orderItemType) {
            if ($orderItemType->getPk() == $id)
                return $orderItemType;
        }
        return [];
    }
}