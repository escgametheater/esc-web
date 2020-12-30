<?php
/**
 * Created by PhpStorm.
 * User: christophercarter
 * Date: 2/25/19
 * Time: 11:42 AM
 */


class GameDataEntity extends DBManagerEntity
{
    use
        hasGameIdField,
        hasKeyField,
        hasStartTimeField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualGameDataSheetsField;
}

class GameDataSheetEntity extends DBManagerEntity
{
    use
        hasGameDataIdField,
        hasGameDataSheetModTypeIdField,
        hasGameDataSheetColumnIdField,
        hasNameField,
        hasCanModField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualGameDataSheetColumnsField,
        hasVirtualGameDataSheetRowsField;
}

class GameDataSheetColumnEntity extends DBManagerEntity
{
    use
        hasGameDataSheetIdField,
        hasNameField,
        hasDisplayOrderField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class GameDataSheetModTypeEntity extends DBManagerEntity
{
    use
        hasDisplayNameField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField;
}

class GameDataSheetRowEntity extends DBManagerEntity
{
    use
        hasGameDataSheetIdField,
        hasDisplayOrderField,
        hasValueField,
        hasIndexKeyField,
        hasIsActiveField,
        hasCreatedByField,
        hasModifiedByField,
        hasDeletedByField,

        hasVirtualProcessedValuesField;
}