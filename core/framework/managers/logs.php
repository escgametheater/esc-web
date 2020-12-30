<?php
/**
 * Logs Manager
 *
 * @package managers
 */

class LogsManager extends Manager
{
    protected $entityClass = LogEntity::class;
    protected $table = Table::Logs;
    protected $table_alias = TableAlias::Logs;
    protected $root = '/admin/logs/';
}

class AdminLogsManager extends Manager
{
    protected $entityClass = AdminLogEntity::class;
    protected $table = Table::AdminLog;
    protected $table_alias = TableAlias::AdminLog;
    protected $root = '/admin/admin-log/';
}
