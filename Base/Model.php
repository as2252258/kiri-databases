<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:39
 */
declare(strict_types=1);

namespace Database\Base;

defined('SAVE_FAIL') or define('SAVE_FAIL', 3227);
defined('FIND_OR_CREATE_MESSAGE') or define('FIND_OR_CREATE_MESSAGE', 'Create a new model, but the data cannot be empty.');


use ArrayAccess;
use Closure;
use Database\ActiveQuery;
use Database\Connection;
use Database\HasMany;
use Database\HasOne;
use Database\ModelInterface;
use Database\Mysql\Columns;
use Database\Relation;
use Database\SqlBuilder;
use Database\Traits\HasBase;
use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use Kiri\Annotation\Annotation;
use Kiri\Error\StdoutLoggerInterface;
use Kiri\Exception\NotFindClassException;
use Kiri\ToArray;
use ReflectionException;
use ReturnTypeWillChange;
use validator\Validator;

/**
 * Class BOrm
 *
 * @package Kiri\Abstracts
 *
 * @property bool $isNowExample
 * @property array $attributes
 * @property array $oldAttributes
 */
abstract class Model extends Component implements ModelInterface, ArrayAccess, ToArray
{

	const GET = 'get';


	const SET = 'set';

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


	/**
	 * @var string
	 */
	protected string $table = '';


	/**
	 * @var string
	 */
	protected string $connection = 'db';


	/**
	 * @var array
	 */
	private array $_with = [];


	/**
	 * @return array
	 */
	public function rules(): array
	{
		return [];
	}


	/**
	 * @param string $name
	 * @param mixed $value
	 * @return mixed
	 * @throws ReflectionException
	 */
	private function _setter(string $name, mixed $value): mixed
	{
		$method = di(Setter::class)->getSetter(static::class, $name);
		if (!empty($method)) {
			$value = $this->{$method}($value);
		}
		return $value;
	}


	/**
	 * @param string $name
	 * @param $value
	 * @return mixed
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
	 * @param $data
	 * @return Model
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
	 * @throws Exception
	 */
	public function init()
	{
		$an = Kiri::getDi()->get(Annotation::class);
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
	public function getIsNowExample(): bool
	{
		return $this->isNewExample === TRUE;
	}


	/**
	 * @param bool $bool
	 * @return $this
	 */
	public function setIsNowExample(bool $bool = FALSE): static
	{
		$this->isNewExample = $bool;
		return $this;
	}

	/**
	 * @return string
	 * @throws Exception
	 * get last exception or other error
	 */
	public function getLastError(): string
	{
		$logger = Kiri::getDi()->get(StdoutLoggerInterface::class);
		return $logger->getLastError('mysql');
	}


	/**
	 * @return bool
	 * @throws Exception
	 */
	public function hasPrimary(): bool
	{
		if ($this->primary !== NULL) {
			return TRUE;
		}
		$primary = $this->getColumns()->getPrimaryKeys();
		if (!empty($primary)) {
			return $this->primary = is_array($primary) ? current($primary) : $primary;
		}
		return FALSE;
	}


	/**
	 * @throws Exception
	 */
	public function isAutoIncrement(): bool
	{
		return $this->getAutoIncrement() !== NULL;
	}

	/**
	 * @throws Exception
	 */
	public function getAutoIncrement(): int|string|null
	{
		return $this->getColumns()->getAutoIncrement();
	}

	/**
	 * @return null|string
	 * @throws Exception
	 */
	public function getPrimary(): ?string
	{
		if (!$this->hasPrimary()) {
			return NULL;
		}
		return $this->primary;
	}


	/**
	 * @return bool
	 * @throws Exception
	 */
	public function hasPrimaryValue(): bool
	{
		if ($this->hasPrimary()) {
			return !empty($this->{$this->getPrimary()});
		}
		return false;
	}


	/**
	 * @return int|null
	 * @throws Exception
	 */
	public function getPrimaryValue(): ?int
	{
		if (!$this->hasPrimary()) {
			return NULL;
		}
		return $this->getAttribute($this->primary);
	}

	/**
	 * @param $param
	 * @param null $db
	 * @return Model|null
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function findOne($param, $db = NULL): static|null
	{
		if (is_bool($param)) {
			return NULL;
		}
		if (is_numeric($param)) {
			$param = static::getPrimaryCondition($param);
		}
		return static::query()->where($param)->first();
	}


	/**
	 * @param $param
	 * @return array
	 * @throws Exception
	 */
	private static function getPrimaryCondition($param): array
	{
		$primary = static::makeNewInstance()->getColumns()->getPrimaryKeys();
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
	 * @return ModelInterface|null
	 * @throws Exception
	 * @throws Exception
	 */
	public static function max($field = NULL): ?ModelInterface
	{
		$columns = static::makeNewInstance()->getColumns();
		if (empty($field)) {
			$field = $columns->getFirstPrimary();
		}
		$columns = $columns->get_fields();
		if (!isset($columns[$field])) {
			return NULL;
		}
		$first = static::query()->max($field)->first();
		if (empty($first)) {
			return NULL;
		}
		return $first[$field];
	}


	/**
	 * @param string|int $param
	 * @return Model|null
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function find(string|int $param): ?static
	{
		$columns = duplicate(static::class)->getPrimary();
		if (empty($columns)) {
			$columns = static::makeNewInstance()->getColumns()->getFirstPrimary();
		}
		return static::query()->where([$columns => $param])->first();
	}


	/**
	 * @return static
	 * @throws ReflectionException
	 */
	private static function makeNewInstance(): static
	{
		return duplicate(static::class);
	}


	/**
	 * @param $condition
	 * @return static|null
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function first($condition): ?static
	{
		return static::query()->where($condition)->first();
	}


	/**
	 * @return ActiveQuery
	 */
	public static function query(): ActiveQuery
	{
		return new ActiveQuery(new static());
	}


	/**
	 * @return Connection
	 */
	public function getConnection(): Connection
	{
		return Kiri::app()->get($this->connection);
	}


	/**
	 * @param null $condition
	 * @param array $attributes
	 *
	 * @param bool $if_condition_is_null
	 * @return bool
	 * @throws Exception
	 */
	protected static function deleteByCondition($condition = NULL, array $attributes = [], bool $if_condition_is_null = FALSE): bool
	{
		if (empty($condition)) {
			if (!$if_condition_is_null) {
				return FALSE;
			}
			return (bool)static::query()->delete();
		}
		$model = static::query()->ifNotWhere($if_condition_is_null)->where($condition);
		if (!empty($attributes)) {
			$model->bindParams($attributes);
		}
		return (bool)$model->delete();
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
	 * @throws ReflectionException
	 */
	public function setAttribute($name, $value): mixed
	{
		return $this->_attributes[$name] = $this->_setter($name, $value);
	}

	/**
	 * @param $name
	 * @param $value
	 * @return mixed
	 * @throws ReflectionException
	 */
	public function setOldAttribute($name, $value): mixed
	{
		return $this->_oldAttributes[$name] = $this->_setter($name, $value);
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
		foreach ($param as $key => $attribute) {
			$this->setAttribute($key, $attribute);
		}
		return $this;
	}


	/**
	 * @param $param
	 * @return $this
	 * @throws ReflectionException
	 */
	public function setOldAttributes($param): static
	{
		if (empty($param) || !is_array($param)) {
			return $this;
		}
		foreach ($param as $key => $attribute) {
			$this->setOldAttribute($key, $attribute);
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
		[$sql, $param] = SqlBuilder::builder(static::query())->insert($param);
		$dbConnection = $this->getConnection()->createCommand($sql, $param);

		$lastId = $dbConnection->save();

		$lastId = $this->setPrimary((int)$lastId, $param);

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
		return $this;
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
			return TRUE;
		}
		if ($this->hasPrimary()) {
			$condition = [$this->getPrimary() => $this->getPrimaryValue()];
		}
		$generate = SqlBuilder::builder(static::query()->where($condition))->update($param);
		if (is_bool($generate)) {
			return $generate;
		}
		$command = $this->getConnection()->createCommand($generate[0], $generate[1]);
		if ($command->save()) {
			return $this->refresh()->afterSave($fields, $param);
		}
		return FALSE;
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
			return FALSE;
		}
		[$change, $condition, $fields] = $this->separation();
		if (!empty($this->_oldAttributes)) {
			return $this->updateInternal($fields, $condition, $change);
		} else {
			return $this->insert($change, $fields);
		}
	}


	/**
	 * @param $value
	 * @return $this
	 */
	public function populates($value): static
	{
		$this->_attributes = $value;
		$this->_oldAttributes = $value;
		$this->setIsNowExample(FALSE);
		return $this;
	}


	/**
	 * @param array|null $rule
	 * @return bool
	 * @throws Exception
	 */
	public function validator(?array $rule): bool
	{
		if (empty($rule)) return TRUE;
		$validate = $this->resolve($rule);
		if (!$validate->validation()) {
			return $this->logger->addError($validate->getError(), 'mysql');
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
		return $this->_attributes[$name] ?? NULL;
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
			$oldValue = $this->_oldAttributes[$key] ?? NULL;
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
		return Kiri::getDi()->get(Relation::class);
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
		return static::makeNewInstance()->getColumns()->hasField($attribute);
	}

	/**ƒ
	 * @return string
	 * @throws Exception
	 */
	public function getTable(): string
	{
		$connection = static::getConnection();

		$tablePrefix = $connection->tablePrefix;
		if (empty($this->table)) {
			throw new Exception('You need add static method `tableName` and return table name.');
		}
		$table = trim($this->table, '{%}');
		if (!empty($tablePrefix) && !str_starts_with($table, $tablePrefix)) {
			$table = $tablePrefix . $table;
		}
		return '`' . $connection->database . '`.' . $table;
	}


	/**
	 * @param $attributes
	 * @param $changeAttributes
	 * @return bool
	 * @throws Exception
	 */
	public function afterSave($attributes, $changeAttributes): bool
	{
		return TRUE;
	}


	/**
	 * @param $model
	 * @return bool
	 */
	public function beforeSave($model): bool
	{
		return TRUE;
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
	public function __set($name, $value): void
	{
		if (method_exists($this, 'set' . ucfirst($name))) {
			$this->{'set' . ucfirst($name)}($value);
		} else {
			$this->_attributes[$name] = $this->_setter($name, $value);
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
		$value = $this->_attributes[$name] ?? NULL;

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
		return $this->getColumns()->_decode($name, $value);
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
			return FALSE;
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
	#[ReturnTypeWillChange] public function offsetSet(mixed $offset, mixed $value)
	{
		$this->__set($offset, $value);
	}

	/**
	 * @param mixed $offset
	 * @throws Exception
	 */
	#[ReturnTypeWillChange] public function offsetUnset(mixed $offset)
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
	 * @return Columns
	 * @throws Exception
	 */
	public function getColumns(): Columns
	{
		return $this->getConnection()->getSchema()->getColumns()
			->table($this->getTable());
	}

	/**
	 * @param array $data
	 * @return static
	 * @throws
	 */
	public static function populate(array $data): static
	{
		$model = new static();
		$model->_attributes = $data;
		$model->_oldAttributes = $data;
		$model->setIsNowExample(FALSE);
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


	/**
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	public static function __callStatic(string $name, array $arguments)
	{
		return (new static())->{$name}(...$arguments);
	}

}
