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
use Database\ActiveQuery;
use Database\Collection;
use Database\Connection;
use Database\ModelInterface;
use Database\Mysql\Columns;
use Database\Relation;
use Database\SqlBuilder;
use Exception;
use Kiri;
use Kiri\Abstracts\Component;
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

    /** @var array */
    protected array $_attributes = [];


    /** @var array */
    protected array $_oldAttributes = [];


    /** @var null|string */
    protected ?string $primary = NULL;


    /**
     * @var bool
     */
    protected bool $isNewExample = TRUE;


    /**
     * @var bool
     */
    protected bool $skipValidate = false;


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
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->init();
    }


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
    public function clean(): void
    {
        $this->_attributes = [];
        $this->_oldAttributes = [];
    }


    /**
     * @throws Exception
     */
    public function init(): void
    {
        $container = Kiri::getDi();
        $container->resolveProperties($container->getReflectionClass(get_called_class()), $this);
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
        return Kiri::getLogger()->getLastError('mysql');
    }


    /**
     * @return bool
     * @throws Exception
     */
    public function hasPrimary(): bool
    {
        return $this->primary !== NULL && $this->primary !== '';
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
            return $this->getPrimaryValue() === null;
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
            return $this->getAttribute($this->getPrimary());
        }
        return null;
    }

    /**
     * @param int|string|array $param
     * @param null $db
     * @return Model|null
     * @throws Exception
     */
    public static function findOne(int|string|array $param, $db = NULL): ?static
    {
        $model = new ActiveQuery(static::makeNewInstance());
        $model->from($model->getTable())->alias('t1');
        if (is_numeric($param)) {
            $model->where([$model->modelClass->getPrimary() => $param]);
        } else {
            $model->where($param);
        }
        return $model->first();
    }


    /**
     * @param int $param
     * @param null $db
     * @return Model|null
     * @throws Exception
     */
    public static function primary(int $param, $db = NULL): ?static
    {
        $model = new ActiveQuery(static::makeNewInstance());
        $model->from($model->getTable())->alias('t1');
        $model->where([$model->modelClass->getPrimary() => $param]);
        return $model->first();
    }


    /**
     * @return static
     */
    private static function makeNewInstance(): static
    {
        return new static();
    }


    /**
     * @param int|string|array $condition
     * @return static|null
     * @throws Exception
     */
    public static function first(int|string|array $condition): ?static
    {
        return static::findOne($condition);
    }


    /**
     * @param string|array $condition
     * @return Collection
     * @throws Exception
     */
    public static function all(string|array $condition): Collection
    {
        $model = new ActiveQuery(static::makeNewInstance());
        $model->from($model->getTable())->alias('t1');
        $model->where($condition);
        return $model->get();
    }


    /**
     * @return ActiveQuery
     * @throws Exception
     */
    public static function query(): ActiveQuery
    {
        $model = new ActiveQuery(static::makeNewInstance());
        $model->from($model->getTable())->alias('t1');
        return $model;
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
     * @param array|string|null $condition
     * @param array $attributes
     *
     * @return bool
     * @throws Exception
     */
    protected static function deleteByCondition(array|string|null $condition = NULL, array $attributes = []): bool
    {
        $model = static::query()->bindParams($attributes);
        if (is_array($condition)) {
            $model->where($condition);
        } else if (is_string($condition)) {
            $model->whereRaw($condition);
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
        $method = 'set' . ucfirst($name) . 'Attribute';
        if (method_exists($this, $method)) {
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
        $method = 'set' . ucfirst($name) . 'Attribute';
        if (method_exists($this, $method)) {
            $value = $this->{$method}($value);
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
        if (count($param) < 1) {
            return $this;
        }
        foreach ($param as $key => $attribute) {
            $this->setAttribute($key, $attribute);
        }
        return $this;
    }


    /**
     * @param array $param
     * @return $this
     * @throws ReflectionException
     */
    public function setOldAttributes(array $param): static
    {
        if (count($param) < 1) {
            return $this;
        }
        foreach ($param as $key => $attribute) {
            $this->setOldAttribute($key, $attribute);
        }
        return $this;
    }


    /**
     * @return $this|bool
     * @throws Exception
     */
    private function insert(): bool|static
    {
        [$sql, $param] = SqlBuilder::builder(static::query())->insert($this->_attributes);

        $dbConnection = $this->getConnection()->createCommand($sql, $param);

        $lastId = $dbConnection->save();
        if ($lastId === false) {
            return false;
        }
        if ($this->hasPrimary()) {
            $this->_attributes[$this->getPrimary()] = $lastId;
        }
        return $this;
    }


    /**
     * @param array $old
     * @param array|string $condition
     * @param array $change
     * @return $this|bool
     * @throws Exception
     */
    protected function updateInternal(array $old, array|string $condition, array $change): bool|static
    {
        $query = static::query()->where($condition);
        $generate = SqlBuilder::builder($query)->update($change);
        if ($generate === false) {
            return false;
        }

        $command = $this->getConnection()->createCommand($generate, $query->attributes);
        if ($command->save()) {
            return $this->refresh()->afterSave($old, $change);
        } else {
            return FALSE;
        }
    }

    /**
     * @param array $data
     * @return bool|$this
     * @throws Exception
     */
    public function save(array $data = []): static|bool
    {
        if (count($data) > 0) {
            $this->_attributes = array_merge($this->_attributes, $data);
        }
        if (!$this->isNewExample) {
            if (!$this->validator($this->rules()) || !$this->beforeSave($this)) {
                return FALSE;
            }

            [$changes, $condition] = $this->diff();

            return $this->updateInternal($condition, $condition, $changes);
        } else {
            return $this->create();
        }
    }


    /**
     * @return array<array, array>
     */
    private function diff(): array
    {
        $changes = \array_diff_assoc($this->_attributes, $this->_oldAttributes);

        $condition = \array_intersect_assoc($this->_oldAttributes, $this->_attributes);

        return [$changes, $condition];
    }


    /**
     * @return $this|bool
     * @throws Exception
     */
    protected function create(): bool|static
    {
        if (!$this->validator($this->rules()) || !$this->beforeSave($this)) {
            return FALSE;
        }
        return $this->insert();
    }


    /**
     * @param $value
     * @return $this
     */
    public function populates($value): static
    {
        $this->_attributes = $value;
        $this->_oldAttributes = $value;
        $this->setIsNowExample();
        return $this;
    }


    /**
     * @param array $rule
     * @return bool
     * @throws Exception
     */
    public function validator(array $rule): bool
    {
        if (count($rule) < 1 || $this->skipValidate) {
            return TRUE;
        }
        $validate = $this->resolve($rule);
        if (!$validate->validation()) {
            return \Kiri::getLogger()->failure($validate->getError(), 'mysql');
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
        $validate = Validator::instance($this->_attributes, $this);
        foreach ($rule as $val) {
            $field = array_shift($val);

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
        return true;
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
     * @param $oldAttributes
     * @param $changeAttributes
     * @return bool
     */
    public function afterSave($oldAttributes, $changeAttributes): bool
    {
        return TRUE;
    }


    /**
     * @param self $model
     * @return bool
     * @throws Exception
     */
    public function beforeSave(self $model): bool
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
        if ($this->hasRelateMethod($name, 'set')) {
            $this->{'set' . ucfirst($name)}($value);
        } else {
            $method = 'set' . ucfirst($name) . 'Attribute';
            if (method_exists($this, $method)) {
                $value = $this->{$method} ($value);
            }
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
        $value = $this->_attributes[$name] ?? null;
        if (!$this->hasRelateMethod($name)) {
            return $this->withPropertyOverride($name, $value);
        } else {
            return $this->withRelate($name);
        }
    }


    /**
     * @param $name
     * @param null $value
     * @return mixed
     * @throws Exception
     */
    protected function withPropertyOverride($name, $value = null): mixed
    {
        $method = 'get' . ucfirst($name) . 'Attribute';
        if (method_exists($this, $method)) {
            return $this->{$method}($value);
        } else {
            return $value;
        }
    }


    /**
     * @param string $name
     * @param string $prefix
     * @return bool
     */
    protected function hasRelateMethod(string $name, string $prefix = 'get'): bool
    {
        return method_exists($this, $prefix . ucfirst($name));
    }


    /**
     * @param $name
     * @return mixed|null
     */
    protected function withRelate($name): mixed
    {
        $response = $this->{'get' . ucfirst($name)}();
        if ($response instanceof \Database\Traits\Relation) {
            $response = $response->get();
        }
        return $response;
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
        $model->setIsNowExample();
        return $model;
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
