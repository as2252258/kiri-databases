<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:39
 */
declare(strict_types=1);

namespace Database;


use Database\Base\BaseActiveRecord;
use Database\Traits\HasBase;
use Exception;
use ReflectionException;
use Kiri\Exception\NotFindClassException;
use Kiri\Kiri;

defined('SAVE_FAIL') or define('SAVE_FAIL', 3227);
defined('FIND_OR_CREATE_MESSAGE') or define('FIND_OR_CREATE_MESSAGE', 'Create a new model, but the data cannot be empty.');

/**
 * Class Orm
 * @package Database
 *
 * @property $attributes
 * @property-read $oldAttributes
 * @method beforeSearch($model)
 */
class ActiveRecord extends BaseActiveRecord
{


	/**
	 * @return array
	 */
	public function rules(): array
	{
		return [];
	}

	/**
	 * @param string $column
	 * @param int $value
	 * @return ActiveRecord|false
	 * @throws Exception
	 */
	public function increment(string $column, int $value): bool|ActiveRecord
	{
		if (!$this->mathematics([$column => $value], '+', null)) {
			return false;
		}
		$this->{$column} += $value;
		return $this->refresh();
	}


	/**
	 * @param string $column
	 * @param int $value
	 * @return ActiveRecord|false
	 * @throws Exception
	 */
	public function decrement(string $column, int $value): bool|ActiveRecord
	{
		if (!$this->mathematics([$column => $value], '-', null)) {
			return false;
		}
		$this->{$column} -= $value;
		return $this->refresh();
	}


	/**
	 * @param array $columns
	 * @return ActiveRecord|false
	 * @throws Exception
	 */
	public function increments(array $columns): bool|static
	{
		if (!$this->mathematics($columns, '+', null)) {
			return false;
		}
		foreach ($columns as $key => $attribute) {
			$this->$key += $attribute;
		}
		return $this;
	}


	/**
	 * @param array $columns
	 * @return ActiveRecord|false
	 * @throws Exception
	 */
	public function decrements(array $columns): bool|static
	{
		if (!$this->mathematics($columns, '-', null)) {
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
	 * @return bool|ActiveRecord
	 * @throws ReflectionException
	 * @throws NotFindClassException
	 * @throws Exception
	 */
	public static function findOrCreate(array $condition, array $attributes = []): bool|static
	{
		$logger = Kiri::app()->getLogger();

		/** @var static $select */
		$select = static::find()->where($condition)->first();
		if (!empty($select)) {
			return $select;
		}
		if (empty($attributes)) {
			return $logger->addError(FIND_OR_CREATE_MESSAGE, 'mysql');
		}
		$select = duplicate(static::class);
		$select->attributes = $attributes;
		if (!$select->save()) {
			return $logger->addError($select->getLastError(), 'mysql');
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
		$logger = Kiri::app()->getLogger();
		if (empty($attributes)) {
			return $logger->addError(FIND_OR_CREATE_MESSAGE, 'mysql');
		}
		/** @var static $select */
		$select = static::find()->where($condition)->first();
		if (empty($select)) {
			$select = duplicate(static::class);
		}
		$select->attributes = $attributes;
		if (!$select->save()) {
			return $logger->addError($select->getLastError(), 'mysql');
		}
		return $select;
	}


	/**
	 * @param $action
	 * @param $columns
	 * @param null|array $condition
	 * @return array|bool|int|string|null
	 * @throws Exception
	 */
	private function mathematics($columns, $action, ?array $condition): int|bool|array|string|null
	{
		if (empty($condition)) {
			$condition = [$this->getPrimary() => $this->getPrimaryValue()];
		}

		$activeQuery = static::find()->where($condition);
		$create = SqlBuilder::builder($activeQuery)->mathematics($columns, $action);
		if (is_bool($create)) {
			return false;
		}
		return static::getDb()->createCommand($create[0], $create[1])->exec();
	}


	/**
	 * @param array $fields
	 * @return ActiveRecord|bool
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
		/** @var static $class */
		$class = Kiri::createObject(['class' => static::class]);
		if (empty($data)) {
			return $class->addError('Insert data empty.', 'mysql');
		}
		return $class::find()->batchInsert($data);
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function delete(): bool
	{
		$conditions = $this->_oldAttributes;
		if (empty($conditions)) {
			return $this->addError("Delete condition do not empty.", 'mysql');
		}
		$primary = $this->getPrimary();

		if (!empty($primary)) {
			$conditions = [$primary => $this->getAttribute($primary)];
		}
		return static::deleteByCondition($conditions);
	}


	/**
	 * @param mixed $condition
	 * @param array $attributes
	 *
	 * @return bool
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function updateAll(mixed $condition, array $attributes = []): bool
	{
		$condition = static::find()->where($condition);
		return $condition->batchUpdate($attributes);
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
		$query = static::find()->where($condition);
		if (!empty($attributes)) {
			$query->bindParams($attributes);
		}
		return $query->all();
	}

	/**
	 * @param $method
	 * @return mixed
	 * @throws Exception
	 */
	private function resolveObject($method): mixed
	{
		$resolve = $this->{$this->getRelate($method)}();
		if ($resolve instanceof HasBase) {
			$resolve = $resolve->get();
		}
		if ($resolve instanceof Collection) {
			return $resolve->toArray();
		} else if ($resolve instanceof ActiveRecord) {
			return $resolve->toArray();
		} else if (is_object($resolve)) {
			return get_object_vars($resolve);
		} else {
			return $resolve;
		}
	}


	/**
	 * @return array
	 * @throws Exception
	 */
	public function toArray(): array
	{
		$data = $this->_attributes;

		$lists = Kiri::getAnnotation()->getGets(static::class);
		foreach ($lists as $key => $item) {
			$data[$key] = $this->{$item}($data[$key] ?? null);
		}
		return array_merge($data, $this->runRelate());
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	private function runRelate(): array
	{
		$relates = [];
		if (empty($with = $this->getWith())) {
			return $relates;
		}
		foreach ($with as $val) {
			$relates[$val] = $this->resolveObject($val);
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
		if (($value = $this->getAttribute($localKey)) === null) {
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
		if (($value = $this->getAttribute($localKey)) === null) {
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
		if (($value = $this->getAttribute($localKey)) === null) {
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
		if (($value = $this->getAttribute($localKey)) === null) {
			throw new Exception("Need join table primary key.");
		}

		$relation = $this->getRelation();

		return new HasMany($modelName, $foreignKey, $value, $relation);
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function afterDelete(): bool
	{
		if (!$this->hasPrimary()) {
			return TRUE;
		}
		$value = $this->getPrimaryValue();
		if (empty($value)) {
			return TRUE;
		}
		return TRUE;
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function beforeDelete(): bool
	{
		if (!$this->hasPrimary()) {
			return TRUE;
		}
		$value = $this->getPrimaryValue();
		if (empty($value)) {
			return TRUE;
		}
		return TRUE;
	}
}
