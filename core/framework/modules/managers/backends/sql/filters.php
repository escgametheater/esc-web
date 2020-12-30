<?php
/**
 * Query Filters
 *
 * @package managers
 */

abstract class SQLFilter extends DBFilter
{
    protected $table = '';

}

class FalseFilter extends SQLFilter
{
    public function __construct()
    {

    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        return $remove_values ? "BOOL" : "FALSE";
    }
}

class TrueFilter extends SQLFilter
{
    public function __construct()
    {

    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        return $remove_values ? "BOOL" : "TRUE";
    }
}

class EqFilter extends SQLFilter
{
    /**
     * Field Name
     * @var string
     */
    protected $field;

    /**
     * Field Value
     * @var string
     */
    protected $value;

    public function __construct($field, $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        if (is_array($this->value)) {
            $field = Q::In($this->field, $this->value);
            return $field->render($conn, $remove_values);
        } else {
            if (is_string($this->field))
                $left_side = Q::DBField($this->field);
            else
                $left_side = $this->field;

            if ($this->value instanceof DBField)
                $right_side = ' = '.$this->value->render($conn);
            elseif ($remove_values)
                $right_side = ' ? ';
            elseif ($this->value === null)
                $right_side = ' IS NULL ';
            else
                $right_side = ' = '.$conn->quote_value($this->value);

            return $left_side->render($conn).$right_side;
        }
    }

}

class NotEqFilter extends SQLFilter
{
    /**
     * Field Name
     * @var string
     */
    protected $field;

    /**
     * Field Value
     * @var string
     */
    protected $value;

    public function __construct($field, $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        if ($this->field instanceof DBField)
            $left_side = $this->field->render($conn);
        else
            $left_side = $conn->quote_field($this->field);

        if ($this->value instanceof DBField)
            $right_side = $this->value === null ? ' IS NOT NULL ' : ' <> '.$this->value->render($conn);
        else
            $right_side = $this->value === null ? ' IS NOT NULL ' : ' <> '.$conn->quote_value($this->value);

        return $left_side.$right_side;
    }
}

class NotFilter extends SQLFilter
{
    /**
     * Condition to negate
     * @var string
     */
    private $cond;

    public function __construct($cond)
    {
        $this->cond = $cond;
    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        return 'NOT ('.$this->cond->render($conn, $remove_values).')';
    }
}

class AndFilter extends SQLFilter
{
    private $ops;

    public function __construct()
    {
        $this->ops = func_get_args();
    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        $conds = [];
        foreach ($this->ops as $op) {
            if ($op) {
                if ($op instanceof DBFilter)
                    $conds[] = $op->render($conn, $remove_values);
                else
                    throw new Exception("Op is not instance of DBFilter");
            }
        }
        return '( '.join(' AND ', $conds).' )';
    }
}

class OrFilter extends SQLFilter
{
    private $ops;

    public function __construct()
    {
        $this->ops = func_get_args();
    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        $conds = [];
        foreach ($this->ops as $op) {
            if ($op) {
                if ($op instanceof DBFilter)
                    $conds[] = $op->render($conn, $remove_values);
                else
                    throw new Exception("Op is not instance of DBFilter");
            }
        }
        return '( '.join(' OR ', $conds).' )';
    }
}

class InFilter extends SQLFilter
{
    /**
     * Field Name
     * @var string
     */
    protected $field;

    /**
     * Field Values
     * @var array
     */
    protected $values;

    public function __construct($field, $values)
    {
        $this->field = $field;
        $this->values = $values;
    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        if (count($this->values) > 0) {
            // Check if we should render the object or SQL Quote it
            $field = $this->field instanceof DBField ? $this->field->render($conn) : $conn->quote_field($this->field);

            return $field .' IN ('.join(',', $conn->quote_values($this->values)).')';
        } else {
            return 'FALSE';
        }
    }
}

class NotInFilter extends SQLFilter
{
    /**
     * Field Name
     * @var string
     */
    protected $field;

    /**
     * Field Values
     * @var array
     */
    protected $values;

    public function __construct($field, $values)
    {
        $this->field = $field;
        $this->values = $values;
    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        if (count($this->values) > 0) {
            // Check if we should render the object or SQL Quote it
            $field = $this->field instanceof DBField ? $this->field->render($conn) : $conn->quote_field($this->field);

            return $field .' NOT IN ('.join(',', $conn->quote_values($this->values)).')';
        } else {
            return 'FALSE';
        }
    }
}

class StartsWithFilter extends SQLFilter
{
    /**
     * Field Name
     * @var string
     */
    protected $field;

    /**
     * Field Value
     * @var string
     */
    protected $value;

    public function __construct($field, $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        if ($this->field instanceof DBField)
            $left_side = $this->field->render($conn);
        else
            $left_side = $conn->quote_field($this->field);


        return $left_side.' LIKE "'.$conn->escape_string($this->value).'%"';
    }

}

class EndsWithFilter extends SQLFilter
{
    /**
     * Field Name
     * @var string
     */
    protected $field;

    /**
     * Field Value
     * @var string
     */
    protected $value;

    public function __construct($field, $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        if ($this->field instanceof DBField)
            $left_side = $this->field->render($conn);
        else
            $left_side = $conn->quote_field($this->field);


        return $left_side.' LIKE "%'.$conn->escape_string($this->value).'"';
    }

}

class LikeFilter extends SQLFilter
{
    /**
     * Field Name
     * @var string
     */
    protected $field;

    /**
     * Field Value
     * @var string
     */
    protected $value;

    public function render(MySQLBackend $conn, $remove_values = false)
    {

        if ($this->field instanceof DBField)
            $left_side = $this->field->render($conn);
        else
            $left_side = $conn->quote_field($this->field);

        return $left_side.' LIKE "%'.$conn->escape_string($this->value).'%"';;
    }

}

class FindInSetFilter extends SQLFilter
{
    /**
     * Field Name
     * @var string
     */
    protected $field;

    /**
     * Field Value
     * @var string
     */
    protected $value;

    public function __construct($field, $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function render(MySQLBackend $conn, $remove_values = false)
    {
        return 'FIND_IN_SET('.$conn->quote_value($this->value).', '.$conn->quote_field($this->field).')';
    }
}

class OpFilter extends SQLFilter
{
    /**
     * Field Name
     * @var string
     */
    protected $field;

    /**
     * Field Value
     * @var string
     */
    protected $value;

    /**
     * Operator
     * @var string
     */
    protected $op;


    public function __construct($field, $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function render(DBBackend $conn, $remove_values = false)
    {
        if ($remove_values)
            $value = '?';
        else {
            if ($this->value instanceof DBField)
                $value = $this->value->render($conn);
            else
                $value = $conn->quote_value($this->value);
        }

        if ($this->field instanceof DBField)
            $quoted_field = $this->field->render($conn);
        else
            $quoted_field = $conn->quote_field($this->field);


        return "$quoted_field {$this->op} $value";
    }
}

class RegexpFilter extends OpFilter
{
    protected $op = 'REGEXP';
}

class GtFilter extends OpFilter
{
    protected $op = '>';
}

class GteFilter extends OpFilter
{
    protected $op = '>=';
}

class LtFilter extends OpFilter
{
    protected $op = '<';
}

class LteFilter extends OpFilter
{
    protected $op = '<=';
}

class BitAndFilter extends OpFilter
{
    protected $op = '&';
    protected $bitValue;

    /**
     * BitAndFilter constructor.
     * @param $field
     * @param $bitValue
     * @param $value
     */
    public function __construct($field, $bitValue, $value)
    {
        $this->bitValue = $bitValue;
        parent::__construct($field, $value);
    }

    /**
     * @param DBBackend $conn
     * @param bool $remove_values
     * @return string
     */
    public function render(DBBackend $conn, $remove_values = false)
    {
        if ($remove_values)
            $value = '?';
        else {
            if ($this->value instanceof DBField)
                $value = $this->value->render($conn);
            else
                $value = $conn->quote_value($this->value);
        }

        if ($this->field instanceof DBField)
            $quoted_field = $this->field->render($conn);
        else
            $quoted_field = $conn->quote_field($this->field);


        return "$quoted_field {$this->op} {$conn->quote_value($this->bitValue)} = $value";
    }
}
