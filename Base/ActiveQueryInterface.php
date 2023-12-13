<?php

namespace Database\Base;

use Closure;

interface ActiveQueryInterface
{


    /**
     * @param string $column
     * @param callable $callable
     * @return $this
     */
    public function when(string $column, callable $callable): static;


    /**
     * @param string $whereRaw
     * @return $this
     */
    public function whereRaw(string $whereRaw): static;


    /**
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function whereLocate(string $column, string $value): static;


    /**
     * @param string $column
     * @return $this
     */
    public function whereNull(string $column): static;


    /**
     * @param string $column
     * @return $this
     */
    public function whereEmpty(string $column): static;

    /**
     * @param string $column
     * @return $this
     */
    public function whereNotEmpty(string $column): static;

    /**
     * @param string $column
     * @return $this
     */
    public function whereNotNull(string $column): static;


    /**
     * @param string $alias
     *
     * @return $this
     *
     * select * from tableName as t1
     */
    public function alias(string $alias = 't1'): static;

    /**
     * @param string|Closure $tableName
     *
     * @return $this
     */
    public function from(string|Closure $tableName): static;

    /**
     * @param string $tableName
     * @param string $alias
     * @param $onCondition
     * @param null $param
     * @return $this
     * @throws
     */
    public function leftJoin(string $tableName, string $alias, $onCondition, $param = NULL): static;

    /**
     * @param $tableName
     * @param $alias
     * @param $onCondition
     * @param null $param
     * @return $this
     * @throws
     */
    public function rightJoin($tableName, $alias, $onCondition, $param = NULL): static;

    /**
     * @param $tableName
     * @param $alias
     * @param $onCondition
     * @param null $param
     * @return $this
     * @throws
     */
    public function innerJoin($tableName, $alias, $onCondition, $param = NULL): static;

    /**
     * @param string $field
     *
     * @return $this
     */
    public function sum(string $field): static;

    /**
     * @param string $field
     * @return $this
     */
    public function max(string $field): static;

    /**
     * @param string $lngField
     * @param string $latField
     * @param int $lng1
     * @param int $lat1
     *
     * @return $this
     */
    public function distance(string $lngField, string $latField, int $lng1, int $lat1): static;

    /**
     * @param string|array $column
     * @param string $sort
     *
     * @return $this
     *
     * [
     *     'addTime',
     *     'descTime desc'
     * ]
     */
    public function orderBy(string|array $column, string $sort = 'DESC'): static;

    /**
     * @param array|string $column
     *
     * @return $this
     */
    public function select(array|string $column = '*'): static;

    /**
     * @return $this
     */
    public function orderRand(): static;

    /**
     * @param array|Closure|string $conditionArray
     * @param string $opera
     * @param null $value
     * @return $this
     */
    public function whereOr(array|Closure|string $conditionArray = [], string $opera = '=', $value = null): static;


    /**
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function whereLike(string $column, string $value): static;

    /**
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function whereLeftLike(string $column, string $value): static;

    /**
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function whereRightLike(string $column, string $value): static;


    /**
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function whereNotLike(string $column, string $value): static;


    /**
     * @param string $columns
     * @param array|Closure $value
     * @return $this
     * @throws
     */
    public function whereIn(string $columns, array|Closure $value): static;


    /**
     * @param string $columns
     * @param array $value
     * @return $this
     */
    public function whereNotIn(string $columns, array $value): static;

    /**
     * @param string $column
     * @param int $start
     * @param int $end
     * @return $this
     */
    public function whereBetween(string $column, int $start, int $end): static;

    /**
     * @param string $column
     * @param int $start
     * @param int $end
     * @return $this
     */
    public function whereNotBetween(string $column, int $start, int $end): static;

    /**
     * @param array $column
     * @return $this
     */
    public function where(array $column): static;


    /**
     * @param string $column
     * @param string $opera
     * @param mixed $value
     * @return $this
     */
    public function whereMath(string $column, string $opera, mixed $value): static;


    /**
     * @param Closure $closure
     * @return $this
     */
    public function whereClosure(Closure $closure): static;


    /**
     * @param string $name
     * @param string|null $having
     *
     * @return $this
     */
    public function groupBy(string $name, string $having = NULL): static;

    /**
     * @param int $limit
     *
     * @return $this
     */
    public function limit(int $limit = 20): static;


    /**
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): static;


}