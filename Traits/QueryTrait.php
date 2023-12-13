<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:56
 */
declare(strict_types=1);

namespace Database\Traits;


use Closure;
use Database\Base\ActiveQueryInterface;
use Database\Command;
use Database\ISqlBuilder;
use Database\ModelInterface;
use Database\Query;
use Database\SqlBuilder;
use Exception;
use Kiri;
use Kiri\Abstracts\Component;

/**
 * Trait QueryTrait
 * @package Database\Traits
 */
abstract class QueryTrait extends Component implements ActiveQueryInterface, ISqlBuilder
{
    public array         $where  = [];
    public array         $select = ['*'];
    public array         $join   = [];
    public array         $order  = [];
    public int           $offset = 0;
    public int           $limit  = 0;
    public string        $group  = '';
    public string        $from   = '';
    public string        $alias  = 't1';
    protected array      $filter = [];
    protected bool       $lock   = false;
    protected SqlBuilder $builder;
    public array         $params = [];
    private array        $_alias = ['t1'];


    /**
     * @var ModelInterface|string|null
     */
    protected ModelInterface|string|null $modelClass;


    /**
     * Comply constructor.
     * @throws
     */
    public function __construct($model = null)
    {
        if (!is_null($model)) {
            $this->modelClass = $model;
        }
        $this->builder = SqlBuilder::builder($this);
        parent::__construct();
    }


    /**
     * @param string $column
     * @param callable $callable
     * @return $this
     */
    public function when(string $column, callable $callable): static
    {
        $caseWhen = new When($column, $this);

        call_user_func($callable, $caseWhen);

        $this->where[] = $caseWhen->end();

        return $this;
    }


    /**
     * @param bool $lock
     * @return $this
     */
    public function lock(bool $lock): static
    {
        $this->lock = $lock;
        return $this;
    }


    /**
     * @param string $whereRaw
     * @return QueryTrait
     */
    public function whereRaw(string $whereRaw): static
    {
        if ($whereRaw == '') {
            return $this;
        }
        $this->where[] = $whereRaw;
        return $this;
    }

    /**
     * @return string
     * @throws
     */
    public function getTable(): string
    {
        return $this->modelClass->getTable();
    }


    /**
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function whereLocate(string $column, string $value): static
    {
        $this->where[] = 'LOCATE(' . $column . ',\'' . addslashes($value) . '\') > 0';
        return $this;
    }


    /**
     * @param string $column
     * @return $this
     */
    public function whereNull(string $column): static
    {
        $this->where[] = $column . ' IS NULL';
        return $this;
    }


    /**
     * @param string $column
     * @return $this
     */
    public function whereEmpty(string $column): static
    {
        $this->where[] = $column . ' = \'\'';
        return $this;
    }

    /**
     * @param string $column
     * @return $this
     */
    public function whereNotEmpty(string $column): static
    {
        $this->where[] = $column . ' <> \'\'';
        return $this;
    }

    /**
     * @param string $column
     * @return $this
     */
    public function whereNotNull(string $column): static
    {
        $this->where[] = $column . ' IS NOT NULL';
        return $this;
    }


    /**
     * @param string $alias
     *
     * @return $this
     *
     * select * from tableName as t1
     */
    public function alias(string $alias = 't1'): static
    {
        $this->alias  = $alias;
        $this->_alias = [$alias];
        return $this;
    }

    /**
     * @param string|Closure $tableName
     *
     * @return $this
     */
    public function from(string|Closure $tableName): static
    {
        if ($tableName instanceof Closure) {
            $tableName = call_user_func($tableName, $this->queryInstance());
        }
        $this->from = $tableName;
        return $this;
    }

    /**
     * @param string $tableName
     * @param string $alias
     * @param array $on
     * @param array $param
     * @return $this
     * $query->join([$tableName, ['userId'=>'uuvOd']], $param)
     * $query->join([$tableName, ['userId'=>'uuvOd'], $param])
     * $query->join($tableName, ['userId'=>'uuvOd',$param])
     */
    private function join(string $tableName, string $alias, array $on, array $param = []): static
    {
        if (empty($on)) {
            return $this;
        }

        $this->_alias[] = $alias;

        $join[] = $tableName . ' AS ' . $alias;
        $join[] = 'ON ' . $this->onCondition($on);
        if (empty($join)) {
            return $this;
        }

        $this->join[] = implode(' ', $join);
        if (!empty($param)) {
            $this->bindParams($param);
        }

        return $this;
    }


    /**
     * @param array $params
     * @return void
     */
    public function bindParams(array $params): void
    {
        foreach ($params as $param) {
            $this->pushParam($param);
        }
    }


    /**
     * @param array $condition
     * @return string
     */
    private function onCondition(array $condition): string
    {
        $array = [];
        foreach ($condition as $key => $item) {
            if (is_numeric($item) || !$this->isAliasField($item)) {
                $array[] = $key . '= ?';
                $this->pushParam($item);
            } else {
                $array[] = $key . '=' . $item;
            }
        }
        return implode(' AND ', $array);
    }


    /**
     * @param string $value
     * @return bool
     */
    private function isAliasField(string $value): bool
    {
        foreach ($this->_alias as $alias) {
            if (str_starts_with($value, $alias . '.')) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $tableName
     * @param string $alias
     * @param array $onCondition
     * @param array $param
     * @return $this
     * @throws Exception
     */
    public function leftJoin(string $tableName, string $alias, array $onCondition, array $param = []): static
    {
        if (class_exists($tableName)) {
            $model = Kiri::getDi()->get($tableName);
            if (!($model instanceof ModelInterface)) {
                throw new Exception('Model must implement ' . ModelInterface::class);
            }
            $tableName = $model->getTable();
        }
        return $this->join("LEFT JOIN " . $tableName, $alias, $onCondition, $param);
    }

    /**
     * @param string $tableName
     * @param string $alias
     * @param array $onCondition
     * @param array $param
     * @return $this
     * @throws Exception
     */
    public function rightJoin(string $tableName, string $alias, array $onCondition, array $param = []): static
    {
        if (class_exists($tableName)) {
            $model = Kiri::getDi()->get($tableName);
            if (!($model instanceof ModelInterface)) {
                throw new Exception('Model must implement ' . ModelInterface::class);
            }
            $tableName = $model->getTable();
        }
        return $this->join("RIGHT JOIN " . $tableName, $alias, $onCondition, $param);
    }

    /**
     * @param string $tableName
     * @param string $alias
     * @param array $onCondition
     * @param array $param
     * @return $this
     * @throws Exception
     */
    public function innerJoin(string $tableName, string $alias, array $onCondition, array $param = []): static
    {
        if (class_exists($tableName)) {
            $model = Kiri::getDi()->get($tableName);
            if (!($model instanceof ModelInterface)) {
                throw new Exception('Model must implement ' . ModelInterface::class);
            }
            $tableName = $model->getTable();
        }
        return $this->join("INNER JOIN " . $tableName, $alias, $onCondition, $param);
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function sum(string $field): static
    {
        $this->select[] = 'SUM(' . $field . ') AS ' . $field;
        return $this;
    }

    /**
     * @param string $field
     * @return $this
     */
    public function max(string $field): static
    {
        $this->select[] = 'MAX(' . $field . ') AS ' . $field;
        return $this;
    }

    /**
     * @param string $lngField
     * @param string $latField
     * @param int|float $lng1
     * @param int|float $lat1
     *
     * @return $this
     */
    public function distance(string $lngField, string $latField, int|float $lng1, int|float $lat1): static
    {
        $sql            = "ROUND(6378.138 * 2 * ASIN(SQRT(POW(SIN(($lat1 * PI() / 180 - $lat1 * PI() / 180) / 2),2) + COS($lat1 * PI() / 180) * COS($latField * PI() / 180) * POW(SIN(($lng1 * PI() / 180 - $lngField * PI() / 180) / 2),2))) * 1000) AS distance";
        $this->select[] = $sql;
        return $this;
    }

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
    public function orderBy(string|array $column, string $sort = 'DESC'): static
    {
        if (empty($column)) {
            return $this;
        }
        if (is_string($column)) {
            return $this->addOrder(...func_get_args());
        }

        foreach ($column as $val) {
            $this->addOrder($val);
        }

        return $this;
    }

    /**
     * @param string $column
     * @param string $sort
     *
     * @return $this
     *
     */
    private function addOrder(string $column, string $sort = 'DESC'): static
    {
        if (func_num_args() == 1 || str_contains($column, ' ')) {
            $this->order[] = $column;
        } else {
            $this->order[] = "$column $sort";
        }
        return $this;
    }

    /**
     * @param array $column
     *
     * @return $this
     */
    public function select(array $column = ['*']): static
    {
        $this->select = $column;
        return $this;
    }

    /**
     * @return $this
     */
    public function rand(): static
    {
        $this->order[] = 'RAND()';
        return $this;
    }

    /**
     * @param array|Closure|string $conditionArray
     * @param string $opera
     * @param null $value
     * @return QueryTrait
     * @throws
     */
    public function whereOr(array|Closure|string $conditionArray = [], string $opera = '=', $value = null): static
    {
        if ($conditionArray instanceof Closure) {
            $conditionArray = $this->makeClosureFunction($conditionArray);
        }

        if (func_num_args() > 1) {
            [$conditionArray, $opera, $value] = $this->opera(...func_get_args());

            $conditionArray = $this->sprintf($conditionArray, $value, $opera);
        }

        $this->where = ['((' . implode(' AND ', $this->where) . ') OR (' . $conditionArray . '))'];
        return $this;
    }


    /**
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function whereLike(string $column, string $value): static
    {
        $this->pushParam($value);
        $this->where[] = $column . ' LIKE \'%?%\'';
        return $this;
    }

    /**
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function whereLeftLike(string $column, string $value): static
    {
        $this->pushParam($value);
        $this->where[] = $column . ' LLike \'%?\'';
        return $this;
    }

    /**
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function whereRightLike(string $column, string $value): static
    {
        $this->pushParam($value);
        $this->where[] = $column . ' RLike \'?%\'';
        return $this;
    }


    /**
     * @param string $column
     * @param string $value
     * @return $this
     */
    public function whereNotLike(string $column, string $value): static
    {
        $this->pushParam($value);
        $this->where[] = $column . ' NOT LIKE \'%?%\'';
        return $this;
    }


    /**
     * @param string $columns
     * @param array|Closure $value
     * @return $this
     * @throws
     */
    public function whereIn(string $columns, array|Closure $value): static
    {
        if ($value instanceof Closure) {
            $value = $this->makeClosureFunction($value);
        }
        if (count($value) < 1) {
            $value = [-1];
        }
        $this->where[] = $columns . ' IN (' . implode(',', $value) . ')';
        return $this;
    }


    /**
     * @return Query
     * @throws
     */
    public function queryInstance(): Query
    {
        return new Query();
    }


    /**
     * @param string $columns
     * @param array $value
     * @return $this
     */
    public function whereNotIn(string $columns, array $value): static
    {
        if (count($value) < 1) {
            $value = [-1];
        }
        $this->where[] = $columns . ' NOT IN (' . implode(',', $value) . ')';
        return $this;
    }

    /**
     * @param string $column
     * @param int|float $start
     * @param int|float $end
     * @return $this
     */
    public function whereBetween(string $column, int|float $start, int|float $end): static
    {
        if (empty($column) || empty($start) || empty($end)) {
            return $this;
        }

        $this->pushParam($start);
        $this->pushParam($end);
        $this->where[] = $column . ' BETWEEN ? AND ?';

        return $this;
    }

    /**
     * @param string $column
     * @param int|float $start
     * @param int|float $end
     * @return $this
     */
    public function whereNotBetween(string $column, int|float $start, int|float $end): static
    {
        if (empty($column) || empty($start) || empty($end)) {
            return $this;
        }

        $this->pushParam($start);
        $this->pushParam($end);
        $this->where[] = $column . ' NOT BETWEEN ? AND ?';

        return $this;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function pushParam(mixed $value): static
    {
        $this->params[] = $value;
        return $this;
    }

    /**
     * @param array $column
     * @return $this
     */
    public function where(array $column): static
    {
        return $this->addArray($column);
    }


    /**
     * @param string $column
     * @param string $opera
     * @param mixed $value
     * @return $this
     */
    public function whereMath(string $column, string $opera, mixed $value): static
    {
        $this->pushParam($value);
        $this->where[] = $column . ' ' . $opera . ' ?';
        return $this;
    }


    /**
     * @param Closure $closure
     * @return $this
     */
    public function whereClosure(Closure $closure): static
    {
        $this->where[] = $this->makeClosureFunction($closure);
        return $this;
    }


    /**
     * @param Closure|array $closure
     * @return string
     * @throws
     */
    public function makeClosureFunction(Closure|array $closure): string
    {
        $generate = $this->queryInstance();
        if ($closure instanceof Closure) {
            call_user_func($closure, $generate);
        } else {
            $generate->addArray($closure);
        }
        return $generate->build();
    }


    /**
     * @param string $name
     * @param string|null $having
     *
     * @return $this
     */
    public function groupBy(string $name, string $having = NULL): static
    {
        $this->group = $name;
        if (empty($having)) {
            return $this;
        }
        $this->group .= ' HAVING ' . $having;
        return $this;
    }

    /**
     * @param int $limit
     *
     * @return $this
     */
    public function limit(int $limit = 20): static
    {
        $this->limit = $limit;
        return $this;
    }


    /**
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }


    /**
     * @return array
     */
    private function opera(): array
    {
        if (func_num_args() == 3) {
            [$column, $opera, $value] = func_get_args();
        } else {
            [$column, $value] = func_get_args();
        }
        if (!isset($opera)) {
            $opera = '=';
        }
        return [$column, $opera, $value];
    }


    /**
     * @param array $array
     * @return $this
     */
    private function addArray(array $array): static
    {
        foreach ($array as $key => $value) {
            if ($value === null) {
                continue;
            }
            $this->where[] = $this->sprintf($key, $value);
        }
        return $this;
    }


    /**
     * @param $column
     * @param $value
     * @param string $opera
     * @return string
     */
    private function sprintf($column, $value, string $opera = '='): string
    {
        $this->pushParam($value);
        return $column . ' ' . $opera . ' ?';
    }


    /**
     * @param string $querySql
     * @param array $params
     * @return Command
     */
    public function buildCommand(string $querySql, array $params = []): Command
    {
        $connection = $this->modelClass->getConnection();
        if (count($params) > 0) {
            $this->bindParams($params);
        }
        return $connection->createCommand($querySql, $this->params);
    }


    /**
     * @return string
     * @throws
     */
    public function build(): string
    {
        return $this->builder->get();
    }


}
