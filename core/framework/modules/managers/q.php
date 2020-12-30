<?php
/**
 * Query shortcuts
 * builds queries using functions
 *
 * @package managers
 */

class Q
{
    /**
     * @return FalseFilter
     */
    static function False()
    {
        return new FalseFilter();
    }

    /**
     * @return TrueFilter
     */
    static function True()
    {
        return new TrueFilter();
    }

    /**
     * @param $field
     * @param $value
     * @return EqFilter
     */
    static function Eq($field, $value)
    {
        return new EqFilter($field, $value);
    }

    /**
     * @param $field
     * @param $value
     * @return BitAndFilter
     */
    static function BitAnd($field, $bitValue, $value)
    {
        return new BitAndFilter($field, $bitValue, $value);
    }

    /**
     * @param $field
     * @param $value
     * @return NotEqFilter
     */
    static function NotEq($field, $value)
    {
        return new NotEqFilter($field, $value);
    }

    /**
     * @param $cond
     * @return NotFilter
     */
    static function Not($cond)
    {
        return new NotFilter($cond);
    }

    /**
     * @param $args
     * @return AndFilter
     */
    static function And_(...$args)
    {
        return new AndFilter(...$args);
    }

    /**
     * @param $args
     * @return OrFilter
     */
    static function Or_(...$args)
    {
        return new OrFilter(...$args);
    }

    /**
     * @param $args
     * @return InFilter
     */
    static function In(...$args)
    {
        return new InFilter(...$args);
    }

    /**
     * @param $field
     * @return EqFilter
     */
    static function IsNull($field)
    {
        return new EqFilter($field, null);
    }

    /**
     * @param $args
     * @return NotInFilter
     */
    static function NotIn(...$args)
    {
        return new NotInFilter(...$args);
    }

    /**
     * @param $field
     * @param $value
     * @return StartsWithFilter
     */
    static function StartsWith($field, $value)
    {
        return new StartsWithFilter($field, $value);
    }

    /**
     * @param $field
     * @param $value
     * @return EndsWithFilter
     */
    static function EndsWith($field, $value)
    {
        return new EndsWithFilter($field, $value);
    }

    /**
     * @param $field
     * @param $value
     * @return mixed
     */
    static function Like($field, $value)
    {
        return new LikeFilter($field, $value);
    }

    /**
     * @param $field
     * @param $value
     * @return RegexpFilter
     */
    static function RegExp($field, $value)
    {
        return new RegexpFilter($field, $value);
    }

    /**
     * @param $field
     * @param $value
     * @return GtFilter
     */
    static function Gt($field, $value)
    {
        return new GtFilter($field, $value);
    }

    /**
     * @param $field
     * @param $value
     * @return GteFilter
     */
    static function Gte($field, $value)
    {
        return new GteFilter($field, $value);
    }

    /**
     * @param $field
     * @param $value
     * @return LtFilter
     */
    static function Lt($field, $value)
    {
        return new LtFilter($field, $value);
    }

    /**
     * @param $field
     * @param $value
     * @return LteFilter
     */
    static function Lte($field, $value)
    {
        return new LteFilter($field, $value);
    }

    /**
     * @param $field
     * @param string $expression_field
     * @param null $table
     * @param bool|false $distinct
     * @return CountDBField
     */
    static function Count($field, $expression_field = DBField::ID, $table = null, $distinct = false)
    {
        return new CountDBField($field, $expression_field, $table, $distinct);
    }


    /**
     * @param $field
     * @param null $table
     * @return DBField
     */
    static function DBField($field, $table = null)
    {
        return new DBField($field, $table);
    }
}
