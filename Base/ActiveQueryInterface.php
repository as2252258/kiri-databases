<?php

namespace Database\Base;

use Closure;
use Database\Traits\QueryTrait;

interface ActiveQueryInterface
{


    /**
     * @param string $column
     * @param callable $callable
     * @return QueryTrait
     */
    public function when(string $column, callable $callable): QueryTrait;


    /**
     * @param string $whereRaw
     * @return QueryTrait
     */
    public function whereRaw(string $whereRaw): QueryTrait;


    /**
     * @param string $column
     * @param string $value
     * @return QueryTrait
     */
    public function whereLocate(string $column, string $value): QueryTrait;


    /**
     * @param string $column
     * @return QueryTrait
     */
    public function whereNull(string $column): QueryTrait;


    /**
     * @param string $column
     * @return QueryTrait
     */
    public function whereEmpty(string $column): QueryTrait;

    /**
     * @param string $column
     * @return QueryTrait
     */
    public function whereNotEmpty(string $column): QueryTrait;

    /**
     * @param string $column
     * @return QueryTrait
     */
    public function whereNotNull(string $column): QueryTrait;


    /**
     * @param string $alias
     *
     * @return QueryTrait select * from tableName as t1
     *
     * select * from tableName as t1
     */
    public function alias(string $alias = 't1'): QueryTrait;

    /**
     * @param string|Closure $tableName
     *
     * @return QueryTrait
     */
    public function from(string|Closure $tableName): QueryTrait;

    /**
     * @param string $tableName
     * @param string $alias
     * @param string|array $onCondition
     * @param array $param
     * @return QueryTrait
     */
    public function leftJoin(string $tableName, string $alias, string|array $onCondition, array $param = []): QueryTrait;

    /**
     * @param string $tableName
     * @param string $alias
     * @param string|array $onCondition
     * @param array $param
     * @return QueryTrait
     */
    public function rightJoin(string $tableName, string $alias, string|array $onCondition, array $param = []): QueryTrait;

    /**
     * @param string $tableName
     * @param string $alias
     * @param string|array $onCondition
     * @param array $param
     * @return QueryTrait
     */
    public function innerJoin(string $tableName, string $alias, string|array $onCondition, array $param = []): QueryTrait;

    /**
     * @param string $field
     *
     * @return QueryTrait
     */
    public function sum(string $field): QueryTrait;

    /**
     * @param string $field
     * @return QueryTrait
     */
    public function max(string $field): QueryTrait;

    /**
     * @param string $lngField
     * @param string $latField
     * @param int|float $lng1
     * @param int|float $lat1
     *
     * @return QueryTrait
     */
    public function distance(string $lngField, string $latField, int|float $lng1, int|float $lat1): QueryTrait;

    /**
     * @param string|array $column
     * @param string $sort
     *
     * @return QueryTrait [
     *
     * [
     *     'addTime',
     *     'descTime desc'
     * ]
     */
    public function orderBy(string|array $column, string $sort = 'DESC'): QueryTrait;

    /**
     * @param array|string $column
     *
     * @return QueryTrait
     */
    public function select(array|string $column = '*'): QueryTrait;

    /**
     * @return QueryTrait
     */
    public function orderRand(): QueryTrait;

    /**
     * @param array|Closure|string $conditionArray
     * @param string $opera
     * @param null $value
     * @return QueryTrait
     */
    public function whereOr(array|Closure|string $conditionArray = [], string $opera = '=', $value = null): QueryTrait;


    /**
     * @param string $column
     * @param string $value
     * @return QueryTrait
     */
    public function whereLike(string $column, string $value): QueryTrait;

    /**
     * @param string $column
     * @param string $value
     * @return QueryTrait
     */
    public function whereLeftLike(string $column, string $value): QueryTrait;

    /**
     * @param string $column
     * @param string $value
     * @return QueryTrait
     */
    public function whereRightLike(string $column, string $value): QueryTrait;


    /**
     * @param string $column
     * @param string $value
     * @return QueryTrait
     */
    public function whereNotLike(string $column, string $value): QueryTrait;


    /**
     * @param string $columns
     * @param array|Closure $value
     * @return QueryTrait
     */
    public function whereIn(string $columns, array|Closure $value): QueryTrait;


    /**
     * @param string $columns
     * @param array $value
     * @return QueryTrait
     */
    public function whereNotIn(string $columns, array $value): QueryTrait;

    /**
     * @param string $column
     * @param int|float $start
     * @param int|float $end
     * @return QueryTrait
     */
    public function whereBetween(string $column, int|float $start, int|float $end): QueryTrait;

    /**
     * @param string $column
     * @param int|float $start
     * @param int|float $end
     * @return QueryTrait
     */
    public function whereNotBetween(string $column, int|float $start, int|float $end): QueryTrait;

    /**
     * @param array $column
     * @return QueryTrait
     */
    public function where(array $column): QueryTrait;


    /**
     * @param string $column
     * @param string $opera
     * @param mixed $value
     * @return QueryTrait
     */
    public function whereMath(string $column, string $opera, mixed $value): QueryTrait;


    /**
     * @param Closure $closure
     * @return QueryTrait
     */
    public function whereClosure(Closure $closure): QueryTrait;


    /**
     * @param string $name
     * @param string|null $having
     *
     * @return QueryTrait
     */
    public function groupBy(string $name, string $having = NULL): QueryTrait;

    /**
     * @param int $limit
     *
     * @return QueryTrait
     */
    public function limit(int $limit = 20): QueryTrait;


    /**
     * @param int $offset
     * @return QueryTrait
     */
    public function offset(int $offset): QueryTrait;


}