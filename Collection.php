<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 13:38
 */
declare(strict_types=1);

namespace Database;

use Database\Base\AbstractCollection;
use Exception;
use JetBrains\PhpStorm\Pure;

/**
 * Class Collection
 * @package Database
 * @property-read $length
 */
class Collection extends AbstractCollection
{

	/**
	 * @return array
	 */
	public function getItems(): array
	{
		// TODO: Change the autogenerated stub
		return $this->_item;
	}

	/**
	 * @param $field
	 *
	 * @return array|null
	 * @throws Exception
	 */
	public function values($field): ?array
	{
		if (empty($field) || !is_string($field)) {
			return NULL;
		}
		$_tmp = [];
		$data = $this->toArray();
		foreach ($data as $val) {
			/** @var ModelInterface $val */
			$_tmp[] = $val[$field];
		}
		return $_tmp;
	}

	/**
	 * @param string $field
	 * @return array|null
	 */
	public function keyBy(string $field): ?array
	{
		$array = $this->toArray();
		$column = array_flip(array_column($array, $field));
		foreach ($column as $key => $value) {
			$column[$key] = $array[$value];
		}

		return $column;
	}

	/**
	 * @return $this
	 */
	public function orderRand(): static
	{
		shuffle($this->_item);
		return $this;
	}

	/**
	 * @param int $start
	 * @param int $length
	 *
	 * @return array
	 */
	#[Pure] public function slice(int $start = 0, int $length = 20): array
	{
		if (empty($this->_item) || !is_array($this->_item)) {
			return [];
		}
		if (count($this->_item) < $length) {
			return $this->_item;
		} else {
			return array_slice($this->_item, $start, $length);
		}
	}

	/**
	 * @param string $field
	 * @param string $setKey
	 *
	 * @return array|null
	 */
	public function column(string $field, string $setKey = ''): ?array
	{
		$data = $this->toArray();
		if (empty($data)) {
			return [];
		}
		if (!empty($setKey) && is_string($setKey)) {
			return array_column($data, $field, $setKey);
		} else {
			return array_column($data, $field);
		}
	}

	/**
	 * @param string $field
	 *
	 * @return float|int|null
	 */
	public function sum(string $field): float|int|null
	{
		$array = $this->column($field);
		if (empty($array)) {
			return NULL;
		}
		return array_sum($array);
	}

	/**
	 * @return ModelInterface|array
	 */
	#[Pure] public function current(): ModelInterface|array
	{
		return current($this->_item);
	}

	/**
	 * @return int
	 */
	#[Pure] public function size(): int
	{
		return (int)count($this->_item);
	}

	/**
	 * @return array
	 * @throws
	 */
	public function toArray(): array
	{
		$array = [];
		/** @var Model $value */
		foreach ($this as $value) {
			if (!is_object($value)) {
				continue;
			}
			$array[] = $value->setWith($this->query->with)->toArray();
		}
		$this->_item = [];
		return $array;
	}

	/**
	 * @throws Exception
	 * 批量删除
	 */
	public function delete(): bool
	{
		$model = $this->getModel();
		if (!$model->hasPrimary()) return false;
		$ids = [];
		foreach ($this as $item) {
			$id = $item->getPrimaryValue();
			if (!empty($id)) {
				$ids[] = $id;
			}
		}
		return $model::query()->whereIn($model->getPrimary(), $ids)->delete();
	}

	/**
	 * @param array $condition
	 * @return Collection
	 * @throws
	 */
	public function filter(array $condition): Collection|static
	{
		$_filters = [];
		if (empty($condition)) {
			return $this;
		}
		foreach ($this as $value) {
			if (!$this->filterCheck($value, $condition)) {
				continue;
			}
			$_filters[] = $value;
		}
		return new Collection($this->query, $_filters, $this->model);
	}


	/**
	 * @param $value
	 * @param $condition
	 * @return bool
	 * @throws Exception
	 */
	private function filterCheck($value, $condition): bool
	{
		$_value = $value;
		if ($_value instanceof ModelInterface) {
			$_value = $_value->toArray();
		}
		$_tmp = array_intersect_key($_value, $condition);
		if (count(array_diff_assoc($_tmp, $condition)) > 0) {
			return false;
		}
		return true;
	}


	/**
	 * @param $key
	 * @param $value
	 * @return mixed
	 */
	public function exists($key, $value): mixed
	{
		foreach ($this as $item) {
			if ($item->$key === $value) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * @return bool
	 */
	#[Pure] public function isEmpty(): bool
	{
		return $this->size() < 1;
	}
}
