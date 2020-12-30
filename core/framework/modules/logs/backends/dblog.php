<?php
/**
 * Database logging
 *
 * @package logs
 */

Modules::uses(Modules::MANAGERS);

class DBLog extends BaseLog
{
    protected function insert($type, $msg, $file = null, $line = null)
    {
        Query::start()
            ->table('logs')
            ->add([
                'type'      => $type,
                'msg'       => $msg,
                'file'      => $file,
                'line'      => $line,
                'post_date' => date(SQL_DATETIME)
        ]);
    }
}
