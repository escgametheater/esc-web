<?php
/**
 * Created by PhpStorm.
 * User: ccarter
 * Date: 6/10/17
 * Time: 4:22 PM
 */

class AccountingStatusEntity extends DBManagerEntity {

    use
        hasNameField,
        hasDisplayNameField,
        hasIsActiveField,
        hasAuditFields,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}