<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 6/16/17
 * Time: 11:19 AM
 */

class OrderItemPlaceholder extends DBDataEntity {

    use
        hasOrderItemTypeIdField,
        hasContextEntityTypeIdField,
        hasContextEntityIdField,
        hasMarkupOwnerTypeIdField,
        hasMarkupOwnerIdField,
        hasDisplayNameField,
        hasQuantityField,
        hasNetPriceField,
        hasNetMarkupField,
        hasTaxRateField;
}


