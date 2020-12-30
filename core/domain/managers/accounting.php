<?php

Entities::uses('accounting');

class AccountingStatusesManager extends BaseEntityManager {

    const STATUS_NEW = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_ERROR = 3;
    const STATUS_MATCHED = 4;
    const STATUS_IGNORE = 5;
    const STATUS_ASSUME_SUCCESS = 6;
    const STATUS_STALE = 7;
    const STATUS_MANUAL = 8;
    const STATUS_EMPTY = 9;

    protected $entityClass = AccountingStatusEntity::class;
    protected $table = Table::AccountingStatus;
    protected $table_alias = TableAlias::AccountingStatus;

    protected $pk = DBField::ACCOUNTING_STATUS_ID;
    protected $foreign_managers = [];

    public static $fields = [
        DBField::ACCOUNTING_STATUS_ID,
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
}