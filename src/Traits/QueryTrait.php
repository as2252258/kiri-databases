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
use Database\ActiveQuery;
use Database\Condition\MathematicsCondition;
use Database\ModelInterface;
use Database\Query;
use Database\SqlBuilder;
use Exception;
use Kiri\Exception\NotFindClassException;
use Kiri\Kiri;
use ReflectionException;

/**
 * Trait QueryTrait
 * @package Database\Traits
 */
trait QueryTrait
{
	public array $where = [];
	public array $select = [];
	public array $join = [];
	public array $order = [];
	public ?int $offset = NULL;
	public ?int $limit = NULL;
	public string $group = '';
	public string|Closure|ActiveQuery|null $from = '';
	public string $alias = 't1';
	public array $filter = [];


	public bool $lock = false;

	public bool $ifNotWhere = false;


	private SqlBuilder $builder;

	/**
	 * @var ModelInterface|string|null
	 */
	public ModelInterface|string|null $modelClass;

	/**
	 * clear
	 */
	public function clear()
	{
		$this->where = [];
		$this->select = [];
		$this->join = [];
		$this->order = [];
		$this->offset = NULL;
		$this->limit = NULL;
		$this->group = '';
		$this->from = '';
		$this->alias = 't1';
		$this->filter = [];
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
		if (empty($whereRaw)) {
			return $this;
		}
		$this->where[] = $whereRaw;
		return $this;
	}


	/**
	 * @param string|array|Closure $condition
	 * @param string|array|Closure $condition1
	 * @param string|array|Closure $condition2
	 * @return $this
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 */
	public function whereIf(string|array|Closure $condition, string|array|Closure $condition1, string|array|Closure $condition2): static
	{
		if (!is_string($condition)) {
			$condition = $this->makeClosureFunction($condition);
		}

		if (!is_string($condition1)) {
			$condition1 = $this->makeClosureFunction($condition1);
		}

		if (!is_string($condition2)) {
			$condition2 = $this->makeClosureFunction($condition2);
		}

		$this->where[] = 'IF(' . $condition . ', ' . $condition1 . ', ' . $condition2 . ')';
		return $this;
	}


	/**
	 * @param $bool
	 * @return $this
	 */
	public function ifNotWhere($bool): static
	{
		$this->ifNotWhere = $bool;
		return $this;
	}

	/**
	 * @return string
	 * @throws Exception
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
	 * @param array|Closure|string $columns
	 * @return $this
	 */
	public function filter(array|Closure|string $columns): static
	{
		if (!$columns) {
			return $this;
		}
		if (is_callable($columns, TRUE)) {
			return call_user_func($columns, $this);
		}
		if (is_string($columns)) {
			$columns = explode(',', $columns);
		}
		if (!is_array($columns)) {
			return $this;
		}
		$this->filter = $columns;
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
		$this->alias = $alias;
		return $this;
	}

	/**
	 * @param string|Closure $tableName
	 *
	 * @return $this
	 */
	public function from(string|Closure $tableName): static
	{
		$this->from = $tableName;
		return $this;
	}

	/**
	 * @param string $tableName
	 * @param string $alias
	 * @param null $on
	 * @param array|null $param
	 * @return $this
	 * $query->join([$tableName, ['userId'=>'uuvOd']], $param)
	 * $query->join([$tableName, ['userId'=>'uuvOd'], $param])
	 * $query->join($tableName, ['userId'=>'uuvOd',$param])
	 */
	private function join(string $tableName, string $alias, $on = NULL, array $param = NULL): static
	{
		if (empty($on)) {
			return $this;
		}
		$join[] = $tableName . ' AS ' . $alias;
		$join[] = 'ON ' . $this->onCondition($alias, $on);
		if (empty($join)) {
			return $this;
		}

		$this->join[] = implode(' ', $join);
		if (!empty($param)) {
			$this->addParams($param);
		}

		return $this;
	}

	/**
	 * @param $alias
	 * @param $on
	 * @return string
	 */
	private function onCondition($alias, $on): string
	{
		$array = [];
		foreach ($on as $key => $item) {
			if (!str_contains($item, '.')) {
				$this->addParam($key, $item);
			} else {
				$explode = explode('.', $item);
				if (isset($explode[1]) && ($explode[0] == $alias || $this->alias == $explode[0])) {
					$array[] = $key . '=' . $item;
				} else {
					$this->addParam($key, $item);
				}
			}
		}
		return implode(' AND ', $array);
	}

	/**
	 * @param string $tableName
	 * @param string $alias
	 * @param $onCondition
	 * @param null $param
	 * @return $this
	 * @throws Exception
	 */
	public function leftJoin(string $tableName, string $alias, $onCondition, $param = NULL): static
	{
		if (class_exists($tableName)) {
			$model = Kiri::getDi()->get($tableName);
			if (!($model instanceof ModelInterface)) {
				throw new Exception('Model must implement ' . ModelInterface::class);
			}
			$tableName = $model->getTable();
		}
		return $this->join(...["LEFT JOIN " . $tableName, $alias, $onCondition, $param]);
	}

	/**
	 * @param $tableName
	 * @param $alias
	 * @param $onCondition
	 * @param null $param
	 * @return $this
	 * @throws Exception
	 */
	public function rightJoin($tableName, $alias, $onCondition, $param = NULL): static
	{
		if (class_exists($tableName)) {
			$model = Kiri::getDi()->get($tableName);
			if (!($model instanceof ModelInterface)) {
				throw new Exception('Model must implement ' . ModelInterface::class);
			}
			$tableName = $model->getTable();
		}
		return $this->join(...["RIGHT JOIN " . $tableName, $alias, $onCondition, $param]);
	}

	/**
	 * @param $tableName
	 * @param $alias
	 * @param $onCondition
	 * @param null $param
	 * @return $this
	 * @throws Exception
	 */
	public function innerJoin($tableName, $alias, $onCondition, $param = NULL): static
	{
		if (class_exists($tableName)) {
			$model = Kiri::getDi()->get($tableName);
			if (!($model instanceof ModelInterface)) {
				throw new Exception('Model must implement ' . ModelInterface::class);
			}
			$tableName = $model->getTable();
		}
		return $this->join(...["INNER JOIN " . $tableName, $alias, $onCondition, $param]);
	}

	/**
	 * @param $array
	 *
	 * @return string
	 */
	private function toString($array): string
	{
		$tmp = [];
		if (!is_array($array)) {
			return $array;
		}
		foreach ($array as $key => $val) {
			if (is_array($val)) {
				$tmp[] = $this->toString($array);
			} else {
				$tmp[] = $key . '=:' . $key;
				$this->attributes[':' . $key] = $val;
			}
		}
		return implode(' AND ', $tmp);
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
	 * @param int $lng1
	 * @param int $lat1
	 *
	 * @return $this
	 */
	public function distance(string $lngField, string $latField, int $lng1, int $lat1): static
	{
		$sql = "ROUND(6378.138 * 2 * ASIN(SQRT(POW(SIN(($lat1 * PI() / 180 - $lat1 * PI() / 180) / 2),2) + COS($lat1 * PI() / 180) * COS($latField * PI() / 180) * POW(SIN(($lng1 * PI() / 180 - $lngField * PI() / 180) / 2),2))) * 1000) AS distance";
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

		foreach ($column as $key => $val) {
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
		$column = trim($column);

		if (func_num_args() == 1 || str_contains($column, ' ')) {
			$this->order[] = $column;
		} else {
			$this->order[] = "$column $sort";
		}
		return $this;
	}

	/**
	 * @param array|string $column
	 *
	 * @return $this
	 */
	public function select(array|string $column = '*'): static
	{
		if ($column == '*') {
			$this->select = $column;
		} else {
			if (!is_array($column)) {
				$column = explode(',', $column);
			}
			foreach ($column as $val) {
				$this->select[] = $val;
			}
		}
		return $this;
	}

	/**
	 * @return $this
	 */
	public function orderRand(): static
	{
		$this->order[] = 'RAND()';
		return $this;
	}

	/**
	 * @param array|Closure|string $conditionArray
	 * @param string $opera
	 * @param null $value
	 * @return QueryTrait
	 * @throws NotFindClassException
	 * @throws ReflectionException
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
	 * @param string $columns
	 * @param string|int|bool|null $value
	 *
	 * @param string $opera
	 * @return QueryTrait
	 */
	public function whereAnd(string $columns, string $opera = '=', string|int|null|bool $value = NULL): static
	{
		[$columns, $opera, $value] = $this->opera(...func_get_args());

		$this->where[] = $this->sprintf($columns, $value, $opera);
		return $this;
	}


	/**
	 * @param string $columns
	 * @param string $value
	 * @return $this
	 * @throws Exception
	 */
	public function whereLike(string $columns, string $value): static
	{
		if (empty($columns) || (empty($value) && $value != 0)) {
			return $this;
		}

		if (is_array($columns)) {
			$columns = 'CONCAT(' . implode(',^,', $columns) . ')';
		}

		$this->where[] = $columns . ' LIKE \'%' . addslashes($value) . '%\'';

		return $this;
	}

	/**
	 * @param string $columns
	 * @param string $value
	 * @return $this
	 * @throws Exception
	 */
	public function whereLeftLike(string $columns, string $value): static
	{
		if (empty($columns) || (empty($value) && $value != 0)) {
			return $this;
		}

		if (is_array($columns)) {
			$columns = 'CONCAT(' . implode(',^,', $columns) . ')';
		}

		$this->where[] = $columns . ' LLike \'%' . addslashes($value) . '\'';

		return $this;
	}

	/**
	 * @param string $columns
	 * @param string $value
	 * @return $this
	 * @throws Exception
	 */
	public function whereRightLike(string $columns, string $value): static
	{
		if (empty($columns) || (empty($value) && $value != 0)) {
			return $this;
		}

		if (is_array($columns)) {
			$columns = 'CONCAT(' . implode(',^,', $columns) . ')';
		}

		$this->where[] = $columns . ' RLike \'' . addslashes($value) . '%\'';

		return $this;
	}


	/**
	 * @param string $columns
	 * @param string $value
	 * @return $this
	 * @throws Exception
	 */
	public function whereNotLike(string $columns, string $value): static
	{
		if (empty($columns) || (empty($value) && $value != 0)) {
			return $this;
		}

		if (is_array($columns)) {
			$columns = 'CONCAT(' . implode(',^,', $columns) . ')';
		}

		$this->where[] = $columns . ' NOT LIKE \'%' . addslashes($value) . '%\'';

		return $this;
	}

	/**
	 * @param string $column
	 * @param int $value
	 * @return $this
	 * @throws Exception
	 * @see MathematicsCondition
	 */
	public function whereEq(string $column, int $value): static
	{
		$this->where[] = ['EQ', $column, $value];

		return $this;
	}


	/**
	 * @param string $column
	 * @param int $value
	 * @return $this
	 * @throws Exception
	 * @see MathematicsCondition
	 */
	public function whereNeq(string $column, int $value): static
	{
		$this->where[] = ['NEQ', $column, $value];

		return $this;
	}


	/**
	 * @param string $column
	 * @param int $value
	 * @return $this
	 * @throws Exception
	 * @see MathematicsCondition
	 */
	public function whereGt(string $column, int $value): static
	{
		$this->where[] = ['GT', $column, $value];

		return $this;
	}

	/**
	 * @param string $column
	 * @param int $value
	 * @return $this
	 * @throws Exception
	 * @see MathematicsCondition
	 */
	public function whereEgt(string $column, int $value): static
	{
		$this->where[] = ['EGT', $column, $value];

		return $this;
	}


	/**
	 * @param string $column
	 * @param int $value
	 * @return $this
	 * @throws Exception
	 * @see MathematicsCondition
	 */
	public function whereLt(string $column, int $value): static
	{
		$this->where[] = ['LT', $column, $value];

		return $this;
	}

	/**
	 * @param string $column
	 * @param int $value
	 * @return $this
	 * @throws Exception
	 * @see MathematicsCondition
	 */
	public function whereElt(string $column, int $value): static
	{
		$this->where[] = ['ELT', $column, $value];

		return $this;
	}

	/**
	 * @param string $columns
	 * @param array|Closure $value
	 * @return $this
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 */
	public function whereIn(string $columns, array|Closure $value): static
	{
		if ($value instanceof Closure) {
			$value = $this->makeClosureFunction($value);
		}
		if (empty($value)) {
			$value = [-1];
		}
		$this->where[] = ['IN', $columns, $value];
		return $this;
	}


	/**
	 * @param $value
	 * @return ActiveQuery
	 * @throws Exception
	 */
	public function makeNewQuery($value): ActiveQuery
	{
		$activeQuery = new ActiveQuery($this->modelClass);
		call_user_func($value, $activeQuery);
		if (empty($activeQuery->from)) {
			$activeQuery->from($activeQuery->modelClass->getTable());
		}
		return $activeQuery;
	}


	/**
	 * @return Query
	 * @throws ReflectionException
	 * @throws NotFindClassException
	 */
	public function makeNewSqlGenerate(): Query
	{
		return Kiri::createObject(['class' => Query::class]);
	}


	/**
	 * @param string $columns
	 * @param array $value
	 * @return $this
	 */
	public function whereNotIn(string $columns, array $value): static
	{
		if (empty($value) || !is_array($value)) {
			$value = [-1];
		}
		$this->where[] = ['NOT IN', $columns, $value];
		return $this;
	}

	/**
	 * @param string $column
	 * @param int $start
	 * @param int $end
	 * @return $this
	 */
	public function whereBetween(string $column, int $start, int $end): static
	{
		if (empty($column) || empty($start) || empty($end)) {
			return $this;
		}

		$this->where[] = $column . ' BETWEEN ' . $start . ' AND ' . $end;

		return $this;
	}

	/**
	 * @param string $column
	 * @param int $start
	 * @param int $end
	 * @return $this
	 */
	public function whereNotBetween(string $column, int $start, int $end): static
	{
		if (empty($column) || empty($start) || empty($end)) {
			return $this;
		}

		$this->where[] = $column . 'NOT BETWEEN' . $start . ' AND ' . $end;

		return $this;
	}

	/**
	 * @param array $params
	 *
	 * @return $this
	 */
	public function bindParams(array $params = []): static
	{
		if (empty($params)) {
			return $this;
		}
		$this->attributes = $params;
		return $this;
	}

	/**
	 * @param Closure|array|string $column
	 * @param string $opera
	 * @param null $value
	 * @return $this
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 */
	public function where(Closure|array|string $column, string $opera = '=', $value = null): static
	{
		if (is_array($column)) {
			return $this->addArray($column);
		}
		if ($column instanceof Closure) {
			$this->where[] = $this->makeClosureFunction($column);
			return $this;
		}
		if (is_string($column)) {
			$this->where[] = $column;
		} else {
			[$column, $opera, $value] = $this->opera(...func_get_args());
			$this->where[] = "$column $opera $value";
		}
		return $this;
	}


	/**
	 * @param Closure|array $closure
	 * @return string
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public function makeClosureFunction(Closure|array $closure): string
	{
		$generate = $this->makeNewSqlGenerate();
		if ($closure instanceof Closure) {
			call_user_func($closure, $generate);
		} else {
			$generate->where($closure);
		}
		return $generate->getSql();
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
	 * @param int $offset
	 * @param int $limit
	 *
	 * @return $this
	 */
	public function limit(int $offset, int $limit = 20): static
	{
		$this->offset = $offset;
		$this->limit = $limit;
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
			if (is_null($value)) continue;
			if (is_numeric($key)) {
				[$column, $opera, $value] = $this->opera(...$value);

				$this->where[] = $this->sprintf($column, $value, $opera);
			} else {
				$this->where[] = $this->sprintf($key, $value);
			}
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
		if (is_string($value)) {
			$value = trim($value, '\'"');
		}
		return "$column $opera '$value'";
	}


	/**
	 * @return $this
	 */
	public function oneLimit(): static
	{
		$this->limit = 1;
		return $this;
	}

}
