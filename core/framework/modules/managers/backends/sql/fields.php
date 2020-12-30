<?php
/**
 * Query fields
 *
 * @package managers
 */
class DBField extends BaseDataFields
{
    /**
     * Field Name
     * @var string
     */
    protected $field;

    /**
     * Field Table
     * @var string
     */
    protected $table;

    /**
     * DBField constructor.
     * @param $field
     * @param null $table
     */
    public function __construct($field, $table = null)
    {
        $this->field = $field;
        $this->table = $table;
    }

    /**
     * @param MySQLBackend $conn
     * @return string
     */
    public function render(MySQLBackend $conn)
    {
        $field = $conn->quote_field($this->field);
        if ($this->table) {
            $table = $conn->quote_field($this->table);
            return "$table.$field";
        } else {
            return $field;
        }
    }

    /**
     * @param $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * @return bool
     */
    public function has_table()
    {
        return !empty($this->table);
    }

    /**
     * @return null|string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

}


class AliasedDBField extends DBField
{
    /**
     * Field Alias
     * @var string
     */
    private $alias;

    public function __construct($field, $alias, $table = null)
    {
        $this->alias = $alias;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        return ($this->table ? '`'.$conn->escape_string($this->table).'`.' : '')
            .'`'.$conn->escape_string($this->field).'`'
            .' as `'.$conn->escape_string($this->alias).'`';
    }
}

class CountDBField extends DBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;
    private $distinct = false;

    public function __construct($field, $expr = '*', $table = null, $distinct = false)
    {
        $this->expr = $expr;
        $this->distinct = $distinct;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        if ($this->expr == '*' || is_int($this->expr))
            $expr = new RawDBField($this->expr);
        elseif (is_string($this->expr))
            $expr = Q::DBField($this->expr, $this->table);
        elseif ($this->expr instanceof DBField)
            $expr = $this->expr;

        $this->table = null;

        if ($this->distinct)
            return 'COUNT(DISTINCT('.$expr->render($conn).')) as '.parent::render($conn);
        else
            return 'COUNT('.$expr->render($conn).') as '.parent::render($conn);
    }
}

class SumDBField extends DBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;

    public function __construct($field, $expr = '*', $table = null)
    {
        $this->expr = $expr;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        if ($this->expr == '*' || is_int($this->expr))
            $expr = new RawDBField($this->expr);
        elseif (is_string($this->expr))
            $expr = Q::DBField($this->expr, $this->table);
        elseif ($this->expr instanceof DBField)
            $expr = $this->expr;

        $this->table = null;

        return 'SUM('.$expr->render($conn).') as '.parent::render($conn);
    }
}


class AvgDBField extends DBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;

    private $decimals = 2;

    public function __construct($field, $expr = '*', $table = null, $decimals = 2)
    {
        $this->expr = $expr;
        $this->decimals = $decimals;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        if ($this->expr == '*' || is_int($this->expr))
            $expr = new RawDBField($this->expr);
        elseif (is_string($this->expr))
            $expr = Q::DBField($this->expr, $this->table);
        elseif ($this->expr instanceof DBField)
            $expr = $this->expr;

        $this->table = null;

        return 'ROUND(AVG('.$expr->render($conn)."), {$this->decimals}) as ".parent::render($conn);
    }
}

class StaticField extends RawDBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;

    /**
     * GroupOnField constructor.
     * @param $field
     * @param $value
     * @param $table
     */
    public function __construct($field, $value)
    {
        $db = DB::inst();

        $this->expr = $db->quote_value($value) ." AS ".$db->quote_field($field);
        parent::__construct($this->expr);
    }
}

class MaxDBField extends DBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;

    public function __construct($field, $expr, $table = null)
    {
        $this->expr = $expr;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        if (is_string($this->expr))
            $expr = Q::DBField($this->expr);

        return 'MAX('.$expr->render($conn).') as '.parent::render($conn);
    }
}

class DateFormatDBField extends DBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;
    private $alias;

    public function __construct($field, $table = null, $expr = '%Y-%m-%d', $alias = null)
    {
        $this->expr = $expr;
        $this->alias = $alias;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        $alias_string = $this->alias ? " AS {$this->alias}" : '';
        return 'DATE_FORMAT('.parent::render($conn).', "'.$this->expr.'")'.$alias_string;
    }
}

class YearDBField extends DBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;

    public function __construct($field, $expr, $table = null)
    {
        $this->expr = $expr;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        if (is_string($this->expr))
            $expr = Q::DBField($this->expr, $this->table);

        return 'YEAR('.$expr->render($conn).') as '.$conn->quote_field(DBField::YEAR);
    }
}


class MonthDBField extends DBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;

    public function __construct($field, $expr, $table = null)
    {
        $this->expr = $expr;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        if (is_string($this->expr))
            $expr = Q::DBField($this->expr, $this->table);

        return 'MONTH('.$expr->render($conn).') as '.$conn->quote_field(DBField::MONTH);
    }
}


class DayDBField extends DBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;

    public function __construct($field, $expr, $table = null)
    {
        $this->expr = $expr;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        if (is_string($this->expr))
            $expr = Q::DBField($this->expr);

        return 'DAY('.$expr->render($conn).') as '.parent::render($conn);
    }
}


class HourDBField extends DBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;

    public function __construct($field, $expr, $table = null)
    {
        $this->expr = $expr;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        if (is_string($this->expr))
            $expr = Q::DBField($this->expr);

        return 'DAY('.$expr->render($conn).') as '.parent::render($conn);
    }
}


class DateDBField extends DBField
{
    /**
     * Field Value
     * @var string
     */
    private $expr;

    public function __construct($field, $expr, $table = null)
    {
        $this->expr = $expr;
        parent::__construct($field, $table);
    }

    public function render(MySQLBackend $conn)
    {
        if (is_string($this->expr))
            $expr = Q::DBField($this->expr);

        return 'DATE('.$expr->render($conn).') as '.parent::render($conn);
    }
}


class RawDBField extends DBField
{
    /**
     * Field Expression
     * @var string
     */
    private $expr;

    public function __construct($expr)
    {
        $this->expr = $expr;
    }

    public function render(MySQLBackend $conn)
    {
        return $this->expr;
    }
}

class ReversedOrder extends DBField
{
    public function render(MySQLBackend $conn)
    {
        return parent::render($conn).' DESC';
    }
}
