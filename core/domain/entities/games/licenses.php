<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/25/19
 * Time: 11:46 AM
 */


class GameLicenseEntity extends DBManagerEntity {

    use
        hasGameIdField,
        hasUserIdField,
        hasUpdateChannelField,
        hasStartTimeField,
        hasEndTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}