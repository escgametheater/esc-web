<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 6/15/18
 * Time: 12:21 PM
 */

class SmsEntity extends DBManagerEntity {

    use
        hasSmsTypeIdField,
        //hasIsSentField,
        hasScheduleTimeField,
        hasUserIdField,
        hasToNumberField,
        hasFromNumberField,
        hasBodyField,
        hasCreateTimeField,
        hasSentTimeField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class SmsTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}