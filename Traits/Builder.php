<?php
declare(strict_types=1);


namespace Database\Traits;


use Database\ActiveQuery;
use Database\Base\ConditionClassMap;
use Database\Condition\HashCondition;
use Database\Condition\OrCondition;
use Database\Query;
use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri\Exception\NotFindClassException;
use Kiri;
use ReflectionException;


/**
 * Trait Builder
 * @package Database\Traits
 */
trait Builder
{


	/**
	 * @param $alias
	 * @return string
	 */
	private function builderAlias($alias): string
	{
		return " AS " . $alias;
	}

	/**
	 * @param $table
	 * @return string
	 * @throws Exception
	 */
	private function builderFrom($table): string
	{
		if ($table instanceof ActiveQuery) {
			$table = '(' . $table->toSql() . ')';
		}
		return " FROM " . $table;
	}

	/**
	 * @param $join
	 * @return string
	 */
	#[Pure] private function builderJoin($join): string
	{
		if (!empty($join)) {
			return ' ' . implode(' ', $join);
		}
		return '';
	}


	/**
	 * @param string $select
	 * @return string
	 */
	#[Pure] private function builderSelect(string $select = "*"): string
	{
		return "SELECT " . $select;
	}


	/**
	 * @param string $group
	 * @return string
	 */
	private function builderGroup(string $group): string
	{
		if ($group != '') {
			return '';
		}
		return ' GROUP BY ' . $group;
	}

	/**
	 * @param $order
	 * @return string
	 */
	#[Pure] private function builderOrder($order): string
	{
		if (!empty($order)) {
			return ' ORDER BY ' . implode(',', $order);
		} else {
			return '';
		}
	}

	/**
	 * @param ActiveQuery|Query $query
	 * @return string
	 */
	#[Pure] private function builderLimit(ActiveQuery|Query $query): string
	{
		if ($query->limit > 0) {
			return ' LIMIT ' . $query->offset . ',' . $query->limit;
		} else {
			return '';
		}
	}


	/**
	 * @param array $where
	 * @return string
     */
	private function where(array $where): string
	{
		if (count($where) < 1) {
			return '';
		}
        return implode(' AND ', $where);
	}


	/**
	 * @param $field
	 * @param $condition
	 * @param $_tmp
	 * @return string
	 * @throws NotFindClassException
	 * @throws ReflectionException|Exception
	 */
	private function resolveCondition($field, $condition, $_tmp): string
	{
		if (is_string($field)) {
			$this->query->bindParam(':where' . $field, $condition);
			return $field . ' = ' . ':where' . $field;
		} else if (is_string($condition)) {
			return $condition;
		} else {
			return $this->_arrayMap($condition, $_tmp);
		}
	}


	/**
	 * @param $condition
	 * @param $array
	 * @return string
	 * @throws Exception
	 */
	private function _arrayMap($condition, $array): string
	{
		if (!isset($condition[0])) {
			return implode(' AND ', $this->_hashMap($condition));
		}
		$stroppier = strtoupper($condition[0]);
		if (str_contains($stroppier, 'OR')) {
			if (!is_string($condition[2])) {
				$condition[2] = $this->_hashMap($condition[2]);
			}
			$builder = Kiri::getDi()->get(OrCondition::class);
			$builder->setValue($condition[2]);
			$builder->setColumn($condition[1]);
			$builder->oldParams = $array;
		} else if (isset(ConditionClassMap::$conditionMap[$stroppier])) {
			$defaultConfig = ConditionClassMap::$conditionMap[$stroppier];

			$class = $defaultConfig['class'];
			unset($defaultConfig['class']);

			$builder = Kiri::getDi()->make($class, [], $defaultConfig);
			$builder->setValue($condition[2]);
			$builder->setColumn($condition[1]);
		} else {
			$builder = Kiri::getDi()->get(HashCondition::class);
			$builder->setValue($condition);
		}

		$array[] = $builder->builder();

		return implode(' AND ', $array);
	}


	/**
	 * @param $condition
	 * @return array
	 */
	private function _hashMap($condition): array
	{
		$_array = [];
		foreach ($condition as $key => $value) {
			$this->query->bindParam(':hash' . $key, $value);
			$_array[] = $key . '=:hash' . $key;
		}
		return $_array;
	}


}
