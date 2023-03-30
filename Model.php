<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:39
 */
declare(strict_types=1);

namespace Database;


use Database\Base\Getter;
use Exception;
use Kiri;
use Kiri\Exception\NotFindClassException;
use Kiri\Error\StdoutLoggerInterface;
use ReflectionException;

defined('SAVE_FAIL') or define('SAVE_FAIL', 3227);
defined('FIND_OR_CREATE_MESSAGE') or define('FIND_OR_CREATE_MESSAGE', 'Create a new model, but the data cannot be empty.');

/**
 * Class Orm
 * @package Database
 *
 * @property $attributes
 * @property-read $oldAttributes
 */
class Model extends Base\Model
{


	/**
	 * @param string $column
	 * @param int $value
	 * @return ModelInterface|false
	 * @throws Exception
	 */
	public function increment(string $column, int $value): bool|ModelInterface
	{
		if (!$this->mathematics([$column => $value], '+')) {
			return false;
		}
		$this->{$column} += $value;
		return $this->refresh();
	}


	/**
	 * @param string $column
	 * @param int $value
	 * @return ModelInterface|false
	 * @throws Exception
	 */
	public function decrement(string $column, int $value): bool|ModelInterface
	{
		if (!$this->mathematics([$column => $value], '-')) {
			return false;
		}
		$this->{$column} -= $value;
		return $this->refresh();
	}


	/**
	 * @param array $columns
	 * @return ModelInterface|false
	 * @throws Exception
	 */
	public function increments(array $columns): bool|static
	{
		if (!$this->mathematics($columns, '+')) {
			return false;
		}
		foreach ($columns as $key => $attribute) {
			$this->$key += $attribute;
		}
		return $this;
	}


	/**
	 * @param array $columns
	 * @return ModelInterface|false
	 * @throws Exception
	 */
	public function decrements(array $columns): bool|static
	{
		if (!$this->mathematics($columns, '-')) {
			return false;
		}
		foreach ($columns as $key => $attribute) {
			$this->$key -= $attribute;
		}
		return $this;
	}

	/**
	 * @param array $condition
	 * @param array $attributes
	 * @return bool|ModelInterface
	 * @throws ReflectionException
	 * @throws NotFindClassException
	 * @throws Exception
	 */
	public static function findOrCreate(array $condition, array $attributes): bool|static
	{
		$logger = Kiri::getDi()->get(StdoutLoggerInterface::class);
		if (empty($attributes)) {
			return $logger->addError(FIND_OR_CREATE_MESSAGE, 'mysql');
		}
		
		/** @var static $select */
		$select = static::query()->where($condition)->first();
		if (!empty($select)) {
			return $select;
		}

		$select = new static();
		$select->setAttributes($condition);
		$select->setAttributes($attributes);
		if (!$select->save()) {
			throw new Exception($select->getLastError());
		}
		return $select;
	}


	/**
	 * @param array $condition
	 * @param array $attributes
	 * @return bool|static
	 * @throws Exception
	 */
	public static function createOrUpdate(array $condition, array $attributes = []): bool|static
	{
		$logger = Kiri::getDi()->get(StdoutLoggerInterface::class);
		if (empty($attributes)) {
			return $logger->addError(FIND_OR_CREATE_MESSAGE, 'mysql');
		}
		/** @var static $select */
		$select = static::query()->where($condition)->first();
		if (empty($select)) {
			$select = new static();
			$select->setAttributes($condition);
		}
		$select->setAttributes($attributes);
		if (!$select->save()) {
			throw new Exception($select->getLastError());
		}
		return $select;
	}


	/**
	 * @param $columns
	 * @param $action
	 * @return array|bool|int|string|null
	 * @throws Exception
	 */
	private function mathematics($columns, $action): int|bool|array|string|null
	{
		$condition = [$this->getPrimary() => $this->getPrimaryValue()];

		$activeQuery = static::query()->where($condition);
		$create = SqlBuilder::builder($activeQuery)->mathematics($columns, $action);
		if (is_bool($create)) {
			return false;
		}
		return $this->getConnection()->createCommand($create[0], $create[1])->exec();
	}


	/**
	 * @param array $fields
	 * @return ModelInterface|bool
	 * @throws Exception
	 */
	public function update(array $fields): static|bool
	{
		return $this->save($fields);
	}


	/**
	 * @param array $data
	 * @return bool
	 * @throws Exception
	 */
	public static function inserts(array $data): bool
	{
		if (empty($data)) {
			return error('Insert data empty.', 'mysql');
		}
		return static::query()->insert($data);
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function delete(): bool
	{
		$primary = $this->getPrimary();
		if (empty($primary) || !$this->hasPrimaryValue()) {
			return $this->logger->addError("Only primary key operations are supported.", 'mysql');
		}
		if (!$this->beforeDelete()) {
			return false;
		}
		$result = static::deleteByCondition([$primary => $this->getPrimaryValue()]);

		$this->afterDelete($result);

		return $result;
	}


	/**
	 * @param mixed $condition
	 * @param array $attributes
	 *
	 * @return bool
	 * @throws Exception
	 */
	public static function updateAll(mixed $condition, array $attributes = []): bool
	{
		$condition = static::query()->where($condition);
		return $condition->update($attributes);
	}


	/**
	 * @param $condition
	 * @return array|Collection
	 */
	public static function get($condition): Collection|array
	{
		return static::query()->where($condition)->all();
	}


	/**
	 * @param       $condition
	 * @param array $attributes
	 *
	 * @return array|Collection
	 * @throws Exception
	 */
	public static function findAll($condition, array $attributes = []): array|Collection
	{
		$query = static::query()->where($condition);
		if (!empty($attributes)) {
			$query->bindParams($attributes);
		}
		return $query->all();
	}


	/**
	 * @return array
	 * @throws Exception
	 */
	public function toArray(): array
	{
		$data = $this->_attributes;
		$keys = Kiri::getDi()->get(Getter::class);
		foreach ($keys->getAll(static::class) as $key => $datum) {
			$data[$key] = $this->{$datum}($data[$key]);
		}
		return $this->withRelates($data);
	}

	/**
	 * @param $relates
	 * @return array
	 * @throws Exception
	 */
	private function withRelates($relates): array
	{
		if (empty($with = $this->getWith())) {
			return $relates;
		}
		foreach ($with as $val) {
			$relates[$val] = $this->withRelate($val);
		}
		return $relates;
	}


	/**
	 * @param string $modelName
	 * @param $foreignKey
	 * @param $localKey
	 * @return HasOne|ActiveQuery
	 * @throws Exception
	 */
	public function hasOne(string $modelName, $foreignKey, $localKey): HasOne|ActiveQuery
	{
		if (($value = $this->{$localKey}) === null) {
			throw new Exception("Need join table primary key.");
		}

		$relation = $this->getRelation();

		return new HasOne($modelName, $foreignKey, $value, $relation);
	}


	/**
	 * @param $modelName
	 * @param $foreignKey
	 * @param $localKey
	 * @return ActiveQuery|HasCount
	 * @throws Exception
	 */
	public function hasCount($modelName, $foreignKey, $localKey): ActiveQuery|HasCount
	{
		if (($value = $this->{$localKey}) === null) {
			throw new Exception("Need join table primary key.");
		}

		$relation = $this->getRelation();

		return new HasCount($modelName, $foreignKey, $value, $relation);
	}


	/**
	 * @param $modelName
	 * @param $foreignKey
	 * @param $localKey
	 * @return ActiveQuery|HasMany
	 * @throws Exception
	 */
	public function hasMany($modelName, $foreignKey, $localKey): ActiveQuery|HasMany
	{
		if (($value = $this->{$localKey}) === null) {
			throw new Exception("Need join table primary key.");
		}

		$relation = $this->getRelation();

		return new HasMany($modelName, $foreignKey, $value, $relation);
	}

	/**
	 * @param $modelName
	 * @param $foreignKey
	 * @param $localKey
	 * @return ActiveQuery|HasMany
	 * @throws Exception
	 */
	public function hasIn($modelName, $foreignKey, $localKey): ActiveQuery|HasMany
	{
		if (($value = $this->{$localKey}) === null) {
			throw new Exception("Need join table primary key.");
		}

		$relation = $this->getRelation();

		return new HasMany($modelName, $foreignKey, $value, $relation);
	}

	/**
	 * @param bool $result
	 * @return void
	 */
	public function afterDelete(bool $result): void
	{
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function beforeDelete(): bool
	{
		return TRUE;
	}
}
