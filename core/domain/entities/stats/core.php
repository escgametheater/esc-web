<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 12/27/18
 * Time: 11:38 PM
 */

class KpiSummaryTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasSummaryFieldField,
        hasDisplayInDashboardField,
        hasIsActiveField;
}