<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:39
 */
declare(strict_types=1);

namespace Database\Base;


use Annotation\Event;
use Annotation\Inject;
use ArrayAccess;
use Closure;
use Database\ActiveQuery;
use Database\ActiveRecord;
use Database\Connection;
use Database\HasMany;
use Database\HasOne;
use Database\IOrm;
use Database\Mysql\Columns;
use Database\ObjectToArray;
use Database\Relation;
use Database\SqlBuilder;
use Database\Traits\HasBase;
use Exception;
use JetBrains\PhpStorm\Pure;
use Kiri\Abstracts\Component;
use Kiri\Abstracts\Config;
use Kiri\Abstracts\TraitApplication;
use Kiri\Application;
use Kiri\Events\EventDispatch;
use Kiri\Exception\ConfigException;
use Kiri\Exception\NotFindClassException;
use Kiri\Kiri;
use ReflectionException;
use validator\Validator;

/**
 * Class BOrm
 *
 * @package Kiri\Abstracts
 *
 * @property bool $isCreate
 * @method rules()
 * @method static tableName()
 * @property Application $container
 * @property EventDispatch $eventDispatch
 */
abstract class BaseActiveRecord extends Component implements IOrm, ArrayAccess, ObjectToArray
{


	use TraitApplication;


	const AFTER_SAVE = 'after::save';
	const BEFORE_SAVE = 'before::save';


	const GET = 'get';
	const SET = 'set';
	const RELATE = 'RELATE';

	/** @var array */
	protected array $_attributes = [];

	/** @var array */
	protected array $_oldAttributes = [];

	/** @var array */
	protected array $_relate = [];

	/** @var null|string */
	protected ?string $primary = NULL;

	/**
	 * @var array
	 */
	private array $_annotations = [];


	/**
	 * @var bool
	 */
	protected bool $isNewExample = TRUE;


	/**
	 * @var array
	 */
	protected array $actions = [];


	/** @var string */
	private static string $connection = 'db';


	/**
	 * @var Relation|null
	 */
	#[Inject(Relation::class)]
	protected ?Relation $_relation;


	/**
	 * @var array
	 */
	private array $_with = [];


	/**
	 * @return Application
	 */
	#[Pure] protected function getContainer(): Application
	{
		return Kiri::app();
	}


	/**
	 * @param string $name
	 * @param mixed $value
	 * @return mixed
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 */
	private function _setter(string $name, mixed $value): mixed
	{
		$method = di(Setter::class)->getSetter(static::class, $name);
		if (!empty($method)) {
			$value = $this->{$method}($value);
		}
		return $this->_attributes[$name] = $value;
	}


	/**
	 * @param string $name
	 * @param $value
	 * @return mixed
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 */
	private function _getter(string $name, $value): mixed
	{
		$data = di(Getter::class)->getGetter(static::class, $name);
		if (empty($data)) {
			return $this->_relater($name, $value);
		}
		return $this->{$data}($value);
	}


	/**
	 * @param string $name
	 * @param $value
	 * @return mixed
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	private function _relater(string $name, $value): mixed
	{
		$data = di(Relate::class)->getRelate(static::class, $name);
		if (!empty($data)) {
			$data = $this->{$data}();
			if ($data instanceof HasBase) {
				return $data->get();
			}
			return $data;
		}
		return $this->_decode($name, $value);
	}


	/**
	 * @return EventDispatch
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 */
	protected function getEventDispatch(): EventDispatch
	{
		return Kiri::getDi()->get(EventDispatch::class);
	}


	/**
	 * @param $data
	 * @return ActiveRecord
	 */
	public function setWith($data): static
	{
		if (empty($data)) {
			return $this;
		}
		$this->_with = $data;
		return $this;
	}


	/**
	 * @return array|null
	 */
	public function getWith(): array|null
	{
		return $this->_with;
	}


	/**
	 * object init
	 */
	public function clean()
	{
		$this->_attributes = [];
		$this->_oldAttributes = [];
	}


	/**
	 * @param Relation $relation
	 */
	public function setRelation(Relation $relation)
	{
		$this->_relation = $relation;
	}


	/**
	 * @throws Exception
	 */
	public function init()
	{
		$an = Kiri::app()->getAnnotation();
		$an->injectProperty($this);
	}


	/**
	 * @return array
	 */
	public function getActions(): array
	{
		return $this->actions;
	}


	/**
	 * @return bool
	 */
	public function getIsCreate(): bool
	{
		return $this->isNewExample === TRUE;
	}


	/**
	 * @param bool $bool
	 * @return $this
	 */
	public function setIsCreate(bool $bool = FALSE): static
	{
		$this->isNewExample = $bool;
		return $this;
	}

	/**
	 * @return mixed
	 * @throws Exception
	 * get last exception or other error
	 */
	public function getLastError(): mixed
	{
		return Kiri::app()->getLogger()->getLastError('mysql');
	}


	/**
	 * @return bool
	 * @throws Exception
	 */
	public function hasPrimary(): bool
	{
		if ($this->primary !== NULL) {
			return true;
		}
		$primary = static::getColumns()->getPrimaryKeys();
		if (!empty($primary)) {
			return $this->primary = is_array($primary) ? current($primary) : $primary;
		}
		return false;
	}


	/**
	 * @throws Exception
	 */
	public function isAutoIncrement(): bool
	{
		return $this->getAutoIncrement() !== null;
	}

	/**
	 * @throws Exception
	 */
	public function getAutoIncrement(): int|string|null
	{
		return static::getColumns()->getAutoIncrement();
	}

	/**
	 * @return null|string
	 * @throws Exception
	 */
	public function getPrimary(): ?string
	{
		if (!$this->hasPrimary()) {
			return null;
		}
		return $this->primary;
	}

	/**
	 * @return int|null
	 * @throws Exception
	 */
	public function getPrimaryValue(): ?int
	{
		if (!$this->hasPrimary()) {
			return null;
		}
		return $this->getAttribute($this->primary);
	}

	/**
	 * @param $param
	 * @param null $db
	 * @return BaseActiveRecord|null
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function findOne($param, $db = NULL): static|null
	{
		if (is_bool($param)) {
			return null;
		}
		if (is_numeric($param)) {
			$param = static::getPrimaryCondition($param);
		}
		return static::find()->where($param)->first();
	}


	/**
	 * @param $param
	 * @return array
	 * @throws Exception
	 */
	private static function getPrimaryCondition($param): array
	{
		$primary = static::getColumns()->getPrimaryKeys();
		if (empty($primary)) {
			throw new Exception('Primary key cannot be empty.');
		}
		if (is_array($primary)) {
			$primary = current($primary);
		}
		return [$primary => $param];
	}


	/**
	 * @param null $field
	 * @return ActiveRecord|null
	 * @throws Exception
	 * @throws Exception
	 */
	public static function max($field = null): ?ActiveRecord
	{
		$columns = static::getColumns();
		if (empty($field)) {
			$field = $columns->getFirstPrimary();
		}
		$columns = $columns->get_fields();
		if (!isset($columns[$field])) {
			return null;
		}
		$first = static::find()->max($field)->first();
		if (empty($first)) {
			return null;
		}
		return $first[$field];
	}


	/**
	 * @return ActiveQuery
	 */
	public static function find(): ActiveQuery
	{
		return static::query();
	}


	/**
	 * @return ActiveQuery
	 */
	public static function query(): ActiveQuery
	{
		return new ActiveQuery(get_called_class());
	}


	/**
	 * @throws ConfigException
	 */
	protected function getConnection()
	{
		return Config::get('connections.' . static::$connection, null, true);
	}


	/**
	 * @param null $condition
	 * @param array $attributes
	 *
	 * @param bool $if_condition_is_null
	 * @return bool
	 * @throws Exception
	 */
	protected static function deleteByCondition($condition = NULL, array $attributes = [], bool $if_condition_is_null = false): bool
	{
		if (empty($condition)) {
			if (!$if_condition_is_null) {
				return false;
			}
			return static::find()->delete();
		}
		$model = static::find()->ifNotWhere($if_condition_is_null)->where($condition);
		if (!empty($attributes)) {
			$model->bindParams($attributes);
		}
		return $model->delete();
	}


	/**
	 * @return array
	 * @throws Exception
	 */
	public function getAttributes(): array
	{
		return $this->toArray();
	}

	/**
	 * @return array
	 */
	public function getOldAttributes(): array
	{
		return $this->_oldAttributes;
	}

	/**
	 * @param $name
	 * @param $value
	 * @return mixed
	 */
	public function setAttribute($name, $value): mixed
	{
		return $this->_attributes[$name] = $value;
	}

	/**
	 * @param $name
	 * @param $value
	 * @return mixed
	 */
	public function setOldAttribute($name, $value): mixed
	{
		return $this->_oldAttributes[$name] = $value;
	}

	/**
	 * @param array $param
	 * @return $this
	 * @throws Exception
	 */
	public function setAttributes(array $param): static
	{
		if (empty($param)) {
			return $this;
		}
		$this->_attributes = array_merge($this->_attributes, $param);
		return $this;
	}

	/**
	 * @param $param
	 * @return $this
	 */
	public function setOldAttributes($param): static
	{
		if (empty($param) || !is_array($param)) {
			return $this;
		}
		foreach ($param as $key => $val) {
			$this->setOldAttribute($key, $val);
		}
		return $this;
	}

	/**
	 * @param $attributes
	 * @param $param
	 * @return $this|bool
	 * @throws Exception
	 */
	private function insert($param, $attributes): bool|static
	{
		[$sql, $param] = SqlBuilder::builder(static::find())->insert($param);
		$dbConnection = static::getDb()->createCommand($sql, $param);
		if (!($lastId = (int)$dbConnection->save(true, $this))) {
			throw new Exception('保存失败.');
		}
		$lastId = $this->setPrimary($lastId, $param);

		$this->refresh()->afterSave($attributes, $param);

		return $lastId;
	}


	/**
	 * @param $lastId
	 * @param $param
	 * @return static
	 * @throws Exception
	 */
	private function setPrimary($lastId, $param): static
	{
		if ($this->isAutoIncrement()) {
			$this->setAttribute($this->getAutoIncrement(), (int)$lastId);
			return $this;
		}

		if (!$this->hasPrimary()) {
			return $this;
		}

		$primary = $this->getPrimary();
		if (!isset($param[$primary]) || empty($param[$primary])) {
			$this->setAttribute($primary, (int)$lastId);
		}
		return $this->setAttributes($param);
	}


	/**
	 * @param $fields
	 * @param $condition
	 * @param $param
	 * @return $this|bool
	 * @throws Exception
	 */
	private function updateInternal($fields, $condition, $param): bool|static
	{
		if (empty($param)) {
			return true;
		}
		if ($this->hasPrimary()) {
			$condition = [$this->getPrimary() => $this->getPrimaryValue()];
		}
		$generate = SqlBuilder::builder(static::find()->where($condition))->update($param);
		if (is_bool($generate)) {
			return $generate;
		}
		$command = static::getDb()->createCommand($generate[0], $generate[1]);
		if ($command->save(false, $this)) {
			return $this->refresh()->afterSave($fields, $param);
		}
		return false;
	}

	/**
	 * @param null $data
	 * @return bool|$this
	 * @throws Exception
	 */
	public function save($data = NULL): static|bool
	{
		if (!is_null($data)) {
			$this->_attributes = merge($this->_attributes, $data);
		}
		if (!$this->validator($this->rules()) || !$this->beforeSave($this)) {
			return false;
		}
		[$change, $condition, $fields] = $this->separation();
		if (!$this->isNewExample) {
			return $this->updateInternal($fields, $condition, $change);
		}
		return $this->insert($change, $fields);
	}


	/**
	 * @param array $rule
	 * @return bool
	 * @throws Exception
	 */
	public function validator(array $rule): bool
	{
		if (empty($rule)) return true;
		$validate = $this->resolve($rule);
		if (!$validate->validation()) {
			return $this->addError($validate->getError(), 'mysql');
		} else {
			return TRUE;
		}
	}

	/**
	 * @param $rule
	 * @return Validator
	 * @throws Exception
	 */
	private function resolve($rule): Validator
	{
		$validate = Validator::getInstance();
		$validate->setParams($this->_attributes);
		$validate->setModel($this);
		foreach ($rule as $val) {
			$field = array_shift($val);
			if (empty($val)) {
				continue;
			}
			$validate->make($field, $val);
		}
		return $validate;
	}

	/**
	 * @param string $name
	 * @return null
	 * @throws Exception
	 */
	public function getAttribute(string $name)
	{
		if ($this->hasAnnotation($name)) {
			return $this->runAnnotation($name, $this->_attributes[$name]);
		}
		return $this->_attributes[$name] ?? null;
	}


	/**
	 * @param string $name
	 * @param mixed $value
	 * @param string $type
	 * @return mixed
	 */
	protected function runAnnotation(string $name, mixed $value, string $type = self::GET): mixed
	{
		return call_user_func($this->_annotations[$type][$name], $value);
	}


	/**
	 * @return array
	 * @throws Exception
	 */
	private function separation(): array
	{
		$_tmp = [];
		$condition = [];
		foreach ($this->_attributes as $key => $val) {
			$oldValue = $this->_oldAttributes[$key] ?? null;
			if ($val === $oldValue) {
				$condition[$key] = $val;
			} else {
				$_tmp[$key] = $val;
			}
		}
		return [$_tmp, $condition, array_keys($_tmp)];
	}


	/**
	 * @param $columns
	 * @param $format
	 * @param $key
	 * @param $value
	 * @return mixed
	 */
	public function toFormat($columns, $format, $key, $value): mixed
	{
		if (isset($format[$key])) {
			return $columns->encode($value, $columns->clean($format[$key]));
		}
		return $value;
	}


	/**
	 * @param $name
	 * @param $value
	 */
	public function setRelate($name, $value)
	{
		$this->_relate[$name] = $value;
	}


	/**
	 * @param $name
	 * @return bool
	 */
	public function hasRelate($name): bool
	{
		return isset($this->_relate[$name]);
	}


	/**
	 * @param array $relates
	 */
	public function setRelates(array $relates)
	{
		if (empty($relates)) {
			return;
		}
		foreach ($relates as $key => $val) {
			$this->setRelate($key, $val);
		}
	}

	/**
	 * @return array
	 */
	public function getRelates(): array
	{
		return $this->_relate;
	}


	/**
	 * @return Relation|null
	 */
	public function getRelation(): ?Relation
	{
		return $this->_relation;
	}


	/**
	 * @param $name
	 * @return array|string|null
	 * @throws Exception
	 */
	public function getRelate($name): null|array|string
	{
		return di(Relate::class)->getRelate(static::class, $name);
	}


	/**
	 * @param $attribute
	 * @return bool
	 * @throws Exception
	 */
	public function has($attribute): bool
	{
		return static::getColumns()->hasField($attribute);
	}

	/**ƒ
	 * @return string
	 * @throws Exception
	 */
	public static function getTable(): string
	{
		$tablePrefix = static::getDb()->tablePrefix;

		$table = static::tableName();
		if (empty($table)) {
			throw new Exception('You need add static method `tableName` and return table name.');
		}
		$table = trim($table, '{{%}}');
		if (!empty($tablePrefix) && !str_starts_with($table, $tablePrefix)) {
			$table = $tablePrefix . $table;
		}
		return '`' . static::getDbName() . '`.' . $table;
	}


	/**
	 * @param $attributes
	 * @param $changeAttributes
	 * @return bool
	 * @throws Exception
	 */
	#[Event(self::AFTER_SAVE)]
	public function afterSave($attributes, $changeAttributes): bool
	{
		return true;
	}


	/**
	 * @param $model
	 * @return bool
	 */
	#[Event(self::BEFORE_SAVE)]
	public function beforeSave($model): bool
	{
		return true;
	}


	private static string $ab_name = '';


	/**
	 * @return Connection
	 * @throws Exception
	 */
	public static function getDb(): Connection
	{
		return static::setDatabaseConnect('db');
	}

	/**
	 * @return static
	 */
	public function refresh(): static
	{
		$this->_oldAttributes = $this->_attributes;
		return $this;
	}

	/**
	 * @param $name
	 * @param $value
	 * @throws Exception
	 */
	public function __set($name, $value)
	{
		if (method_exists($this, 'set' . ucfirst($name))) {
			$this->{'set' . ucfirst($name)}($value);
		} else {
			$this->_setter($name, $value);
		}
	}


	/**
	 * @param $name
	 * @return mixed
	 * @throws Exception
	 */
	public function __get($name): mixed
	{
		$method = 'get' . ucfirst($name);
		if (method_exists($this, $method)) {
			return $this->{$method}();
		}
		$value = $this->_attributes[$name] ?? null;

		return $this->_getter($name, $value);
	}


	/**
	 * @param $name
	 * @param $value
	 * @return mixed
	 * @throws Exception
	 */
	private function _decode($name, $value): mixed
	{
		return static::getColumns()->_decode($name, $value);
	}


	/**
	 * @param $name
	 * @return mixed
	 */
	private function with($name): mixed
	{
		$data = $this->{$this->_relate[$name]}();
		if ($data instanceof HasBase) {
			return $data->get();
		}
		return $data;
	}


	/**
	 * @param string $type
	 * @return array
	 */
	protected function getAnnotation(string $type = self::GET): array
	{
		return $this->_annotations[$type] ?? [];
	}


	/**
	 * @param $name
	 * @param string $type
	 * @return bool
	 */
	protected function hasAnnotation($name, string $type = self::GET): bool
	{
		if (!isset($this->_annotations[$type])) {
			return false;
		}
		return isset($this->_annotations[$type][$name]);
	}


	/**
	 * @param $item
	 * @param $data
	 * @return array
	 */
	protected function resolveAttributes($item, $data): array
	{
		return call_user_func($item, $data);
	}

	/**
	 * @param $name
	 * @return bool
	 */
	public function __isset($name): bool
	{
		return isset($this->_attributes[$name]);
	}

	/**
	 * @param $call
	 * @return mixed
	 * @throws Exception
	 */
	private function resolveClass($call): mixed
	{
		if ($call instanceof HasOne) {
			return $call->get();
		} else if ($call instanceof HasMany) {
			return $call->get();
		} else {
			return $call;
		}
	}


	/**
	 * @param mixed $offset
	 * @return bool
	 * @throws Exception
	 */
	public function offsetExists(mixed $offset): bool
	{
		return isset($this->_attributes[$offset]) || isset($this->_oldAttributes[$offset]);
	}

	/**
	 * @param mixed $offset
	 * @return mixed
	 * @throws Exception
	 */
	public function offsetGet(mixed $offset): mixed
	{
		return $this->__get($offset);
	}

	/**
	 * @param mixed $offset
	 * @param mixed $value
	 * @throws Exception
	 */
	public function offsetSet(mixed $offset, mixed $value)
	{
		$this->__set($offset, $value);
	}

	/**
	 * @param mixed $offset
	 * @throws Exception
	 */
	public function offsetUnset(mixed $offset)
	{
		if (!isset($this->_attributes[$offset])
			&& !isset($this->_oldAttributes[$offset])) {
			return;
		}
		unset($this->_attributes[$offset]);
		unset($this->_oldAttributes[$offset]);
		if (isset($this->_relate)) {
			unset($this->_relate[$offset]);
		}
	}

	/**
	 * @return array
	 */
	public function unset(): array
	{
		$fields = func_get_args();
		$fields = array_shift($fields);
		if (!is_array($fields)) {
			$fields = explode(',', $fields);
		}

		$array = array_combine($fields, $fields);

		return array_diff_assoc($array, $this->_attributes);
	}


	/**
	 * @param $dbName
	 * @return mixed
	 * @throws Exception
	 */
	public static function setDatabaseConnect($dbName): Connection
	{
		return Kiri::app()->get('db')->get(static::$connection = $dbName);
	}


	/**
	 * @return string
	 * @throws ConfigException
	 */
	public static function getDbName(): string
	{
		return Config::get('databases.connections.' . static::$connection . '.database');
	}


	/**
	 * @return Columns
	 * @throws Exception
	 */
	public static function getColumns(): Columns
	{
		return static::getDb()->getSchema()
			->getColumns()
			->table(static::getTable());
	}

	/**
	 * @param array $data
	 * @return static
	 * @throws
	 */
	public static function populate(array $data): static
	{
		$model = duplicate(static::class);
		$model->_attributes = $data;
		$model->_oldAttributes = $data;
		$model->setIsCreate(false);
		return $model;
	}


	/**
	 * @param $method
	 * @param $value
	 * @return Closure
	 */
	protected function dispatcher($method, $value): Closure
	{
		return function () use ($method, $value) {
			return $this->{$method}($value);
		};
	}

}
