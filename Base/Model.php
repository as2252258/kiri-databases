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
use ReturnTypeWillChange;
use Kiri\ToArray;
use ReflectionException;
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
	protected array $_with = [];
	
	
	/**
	 * @return array
	 */
	public function rules(): array
	{
		return [];
	}
	
	
	/**
	 * @param array $data
	 * @return Model
	 */
	public function setWith(array $data): static
	{
		$this->_with = $data;
		return $this;
	}
	
	
	/**
	 * @return array
	 */
	public function getWith(): array
	{
		return $this->_with;
	}
	
	
	/**
	 * @return bool
	 */
	public function hasWith(): bool
	{
		return count($this->_with) > 0;
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
		if ($this->hasPrimary()) {
			return $this->getAttribute($this->primary);
		}
		return null;
	}
	
	/**
	 * @param int|array|string|null $param
	 * @param null $db
	 * @return Model|null
	 * @throws NotFindClassException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function findOne(int|array|string|null $param, $db = NULL): static|null
	{
		if (is_null($param)) {
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
		$columns = (new static())->getPrimary();
		if (empty($columns)) {
			$columns = static::makeNewInstance()->getColumns()->getFirstPrimary();
		}
		return static::query()->where([$columns => $param])->first();
	}
	
	
	/**
	 * @return static
	 */
	private static function makeNewInstance(): static
	{
		return new static();
	}
	
	
	/**
	 * @param $condition
	 * @return static|null
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
	 * @throws Exception
	 */
	public function getConnection(): Connection
	{
		return Kiri::service()->get($this->connection);
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
		$model = static::query();
		if (!empty($condition)) {
			$model->where($condition)->bindParams($attributes);
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
		$keys = Kiri::getDi()->get(Setter::class);
		if ($keys->has(static::class, $name)) {
			$method = $keys->get(static::class, $name);
			$value = $this->{$method}($value);
		}
		return $this->_attributes[$name] = $value;
	}
	
	/**
	 * @param $name
	 * @param $value
	 * @return mixed
	 * @throws ReflectionException
	 */
	public function setOldAttribute($name, $value): mixed
	{
		if (method_exists($this, 'set' . ucfirst($name) . 'Attribute')) {
			$value = $this->{'set' . ucfirst($name) . 'Attribute'}($value);
		}
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
		if ($this->isAutoIncrement()) {
			$lastId = $this->setPrimary((int)$lastId, $param);
		} else {
			$lastId = $this;
		}
		
		$this->setIsNowExample(false);
		
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
		if (empty($param[$primary])) {
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
		if (!$this->getIsNowExample()) {
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
		$assoc = array_diff_assoc($this->_attributes, $this->_oldAttributes);
		
		$column = $this->getColumns();
		
		$uassoc = array_intersect_assoc($this->_attributes, $this->_oldAttributes);
		foreach ($assoc as $key => $item) {
			$encode = $column->get_fields($key);
			if ($column->isString($encode) && $item === null) {
				unset($assoc[$key]);
			}
		}
		return [$assoc, $uassoc, array_keys($assoc)];
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
		$method = 'set' . ucfirst($name);
		if (method_exists($this, $method)) {
			$this->{$method}($value);
			return;
		}
		$method = $method . 'Attribute';
		if (method_exists($this, $method)) {
			$this->_attributes[$name] = $this->{$method}($value);
		} else {
			$this->_attributes[$name] = $value;
		}
	}
	
	
	/**
	 * @param $name
	 * @return mixed
	 * @throws Exception
	 */
	public function __get($name): mixed
	{
		if (isset($this->_attributes[$name])) {
			return $this->withPropertyOverride($name);
		}
		return $this->getRelateValue($name);
	}
	
	
	/**
	 * @param $name
	 * @param null $value
	 * @return mixed
	 * @throws Exception
	 */
	protected function withPropertyOverride($name, $value = null): mixed
	{
		if (is_null($value)) {
			$value = $this->_attributes[$name] ?? NULL;
		}
		$getter = Kiri::getDi()->get(Getter::class);
		if ($getter->has(static::class, $name)) {
			return $this->{$getter->get(static::class, $name)}($value);
		} else {
			return $value;
		}
	}
	
	
	/**
	 * @param $name
	 * @return bool
	 */
	protected function hasRelateMethod($name): bool
	{
		return method_exists($this, 'get' . ucfirst($name));
	}
	
	
	/**
	 * @param $name
	 * @return mixed|null
	 */
	protected function withRelate($name): mixed
	{
		$response = $this->getRelateValue($name);
		if ($response instanceof ToArray) {
			$response = $response->toArray();
		}
		return $response;
	}
	
	
	/**
	 * @param $name
	 * @return mixed
	 */
	protected function getRelateValue($name): mixed
	{
		if (!$this->hasRelateMethod($name)) {
			return null;
		}
		$response = $this->{'get' . ucfirst($name)}();
		if ($response instanceof HasBase) {
			$response = $response->get();
		}
		return $response;
	}
	
	
	/**
	 * @param $name
	 * @param $value
	 * @return mixed
	 * @throws Exception
	 */
	protected function _decode($name, $value): mixed
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
	 * @param string ...$params
	 * @return array
	 */
	public function unset(string ...$params): array
	{
		return array_diff_assoc($params, $this->_attributes);
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
