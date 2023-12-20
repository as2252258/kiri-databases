<?php /** @noinspection ALL */
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
use Database\DatabasesProviders;
use Database\ModelInterface;
use Database\Relation;
use Database\SqlBuilder;
use Exception;
use Kiri;
use Kiri\Abstracts\Component;
use ReturnTypeWillChange;
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
abstract class Model extends Component implements ModelInterface, ArrayAccess, \Arrayable
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
        $this->_attributes    = [];
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
     * @throws
     * get last exception or other error
     */
    public function getLastError(): string
    {
        return $this->getLogger()->getLastError('mysql');
    }


    /**
     * @return bool
     * @throws
     */
    public function hasPrimary(): bool
    {
        return !empty($this->primary);
    }

    /**
     * @return null|string
     * @throws
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
     * @throws
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
     * @throws
     */
    public function getPrimaryValue(): ?int
    {
        if (!$this->hasPrimary()) {
            return null;
        }
        return $this->_oldAttributes[$this->getPrimary()] ?? null;
    }

    /**
     * @param int|string|array $param
     * @return Model|null
     * @throws
     */
    public static function findOne(int|string|array $param): ?static
    {
        $model = static::instance();

        $query = new ActiveQuery($model);
        $query->from($model->getTable())->alias('t1');
        if (is_numeric($param)) {
            $query->where([$model->getPrimary() => $param]);
        } else if (is_array($param)) {
            $query->where($param);
        } else {
            $query->whereRaw($param);
        }
        $data = $query->first();
        if ($data === false) {
            throw new Exception($model->getLastError());
        }
        return $data;
    }


    /**
     * @param int $param
     * @return Model|null
     * @throws
     */
    public static function primary(int $param): ?static
    {
        $model = static::instance();
        $query = new ActiveQuery($model);
        $query->from($model->getTable())->alias('t1')->where([$model->getPrimary() => $param]);
        return $query->first();
    }


    /**
     * @return bool|int
     * @throws Exception
     */
    public function optimize(): bool|int
    {
        return static::query()->execute('OPTIMIZE TABLE ' . $this->getTable());
    }


    /**
     * @return static
     */
    protected static function instance(): static
    {
        return new static();
    }


    /**
     * @param int|string|array $condition
     * @return static|null
     * @throws
     */
    public static function first(int|string|array $condition): ?static
    {
        return static::findOne($condition);
    }


    /**
     * @param string|array $condition
     * @return Collection
     * @throws
     */
    public static function all(string|array $condition): Collection
    {
        $model = new ActiveQuery(static::instance());
        $model->from($model->getTable())->alias('t1');
        if (is_array($condition)) {
            $model->where($condition);
        } else {
            $model->whereRaw($condition);
        }
        return $model->get();
    }


    /**
     * @return ActiveQuery
     * @throws
     */
    public static function query(): ActiveQuery
    {
        $model = new ActiveQuery(static::instance());
        $model->from($model->getTable())->alias('t1');
        return $model;
    }


    /**
     * @return Connection
     * @throws
     */
    public function getConnection(): Connection
    {
        return Kiri::getDi()->get(DatabasesProviders::class)->get($this->connection);
    }


    /**
     * @param array|string $condition
     * @param array $attributes
     *
     * @return bool
     */
    protected static function deleteByCondition(array|string $condition = [], array $attributes = []): bool
    {
        $model = static::query();
        $model->bindParams($attributes);
        if (is_string($condition)) {
            $model->whereRaw($condition);
        } else {
            $model->where($condition);
        }
        return $model->delete();
    }


    /**
     * @return array
     * @throws
     */
    public function getAttributes(): array
    {
        return $this->_attributes;
    }

    /**
     * @return array
     */
    public function getOldAttributes(): array
    {
        return $this->_oldAttributes;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function setAttribute(string $name, mixed $value): mixed
    {
        $method = 'set' . ucfirst($name) . 'Attribute';
        if (method_exists($this, $method)) {
            $value = $this->{$method}($value);
        }
        return $this->_attributes[$name] = $value;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function setOldAttribute(string $name, mixed $value): mixed
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
     * @throws
     */
    public function setAttributes(array $param): static
    {
        foreach ($param as $key => $attribute) {
            $this->setAttribute($key, $attribute);
        }
        return $this;
    }


    /**
     * @param array $param
     * @return $this
     */
    public function setOldAttributes(array $param): static
    {
        foreach ($param as $key => $attribute) {
            $this->setOldAttribute($key, $attribute);
        }
        return $this;
    }


    /**
     * @return $this|bool
     * @throws
     */
    private function insert(): bool|static
    {
        $sql    = SqlBuilder::builder($query = static::query())->insert($this->_attributes);
        $lastId = $this->getConnection()->createCommand($sql, $query->params)->save();
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
     * @param array $condition
     * @param array $change
     * @return $this|bool
     * @throws
     */
    protected function updateInternal(array $old, array $condition, array $change): bool|static
    {
        $query = static::query()->where($condition);
        if (count($change) < 1) {
            return true;
        }
        $generate = SqlBuilder::builder($query)->update($change);
        if ($generate === false) {
            return false;
        }
        if (!$this->getConnection()->createCommand($generate, $query->params)->save()) {
            return FALSE;
        }
        return $this->refresh()->afterSave($old, $change);
    }

    /**
     * @return bool|$this
     * @throws
     */
    public function save(): static|bool
    {
        if (!$this->validator($this->rules()) || !$this->beforeSave($this)) {
            return FALSE;
        }
        if (!$this->isNewExample) {
            return $this->updateInternal(...$this->arrayIntersect($this->_attributes));
        } else {
            return $this->insert();
        }
    }


    /**
     * @return array<array, array, array>
     * @throws
     */
    protected function arrayIntersect(array $params): array
    {
        $condition = [];
        $oldPrams  = [];
        foreach ($this->_oldAttributes as $key => $attribute) {
            if (!array_key_exists($key, $params) || $params[$key] == $attribute) {
                $condition[$key] = $attribute;
                unset($params[$key]);
            } else {
                $oldPrams[$key] = $this->_oldAttributes[$attribute];
            }
        }
        return [$oldPrams, $condition, $params];
    }


    /**
     * @return array
     */
    public function getChanges(): array
    {
        if (!$this->isNewExample) {
            return \array_intersect_assoc($this->_oldAttributes, $this->_attributes);
        }
        return $this->_attributes;
    }


    /**
     * @param array $value
     * @return $this
     */
    public function populates(array $value): static
    {
        $this->_attributes    = $value;
        $this->_oldAttributes = $value;
        $this->setIsNowExample();
        return $this;
    }


    /**
     * @param array $rule
     * @return bool
     * @throws
     */
    public function validator(array $rule): bool
    {
        if (count($rule) < 1 || $this->skipValidate) {
            return TRUE;
        }
        $validate = $this->resolve($rule);
        if (!$validate->validation($this)) {
            return \Kiri::getLogger()->failure($validate->getError() . PHP_EOL, 'mysql');
        } else {
            return TRUE;
        }
    }


    /**
     * @param array $rule
     * @return Validator
     * @throws
     */
    private function resolve(array $rule): Validator
    {
        $validate = new Validator();
        foreach ($rule as $val) {
            $field = array_shift($val);
            if (is_string($field)) {
                $validate->make($this, [$field], $val);
            } else {
                $validate->make($this, $field, $val);
            }
        }
        return $validate;
    }


    /**
     * @param string $name
     * @return null
     * @throws
     */
    public function getAttribute(string $name): mixed
    {
        return $this->_attributes[$name] ?? NULL;
    }


    /**
     * @return Relation|null
     * @throws
     */
    public function getRelation(): ?Relation
    {
        return Kiri::getDi()->get(Relation::class);
    }


    /**
     * @param string $attribute
     * @return bool
     * @throws
     */
    public function has(string $attribute): bool
    {
        return true;
    }


    /**ƒ
     * @return string
     * @throws
     */
    public function getTable(): string
    {
        $connection  = $this->getConnection();
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
     * @param array $oldAttributes
     * @param array $changeAttributes
     * @return bool
     */
    public function afterSave(array $oldAttributes, array $changeAttributes): bool
    {
        return TRUE;
    }


    /**
     * @param self $model
     * @return bool
     * @throws
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
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
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
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        $value = $this->_attributes[$name] ?? null;
        if (!$this->hasRelateMethod($name)) {
            return $this->withPropertyOverride($name, $value);
        } else {
            return $this->withRelate($name);
        }
    }


    /**
     * @param string $name
     * @param mixed|null $value
     * @return mixed
     */
    protected function withPropertyOverride(string $name, mixed $value = null): mixed
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
     * @param string $name
     * @return mixed
     */
    protected function withRelate(string $name): mixed
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
    public function __isset(string $name): bool
    {
        return isset($this->_attributes[$name]);
    }


    /**
     * @param mixed $offset
     * @return bool
     * @throws
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->_attributes[$offset]) || isset($this->_oldAttributes[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     * @throws
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws
     */
    #[ReturnTypeWillChange] public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * @param mixed $offset
     * @throws
     */
    #[ReturnTypeWillChange] public function offsetUnset(mixed $offset): void
    {
        if (!isset($this->_attributes[$offset]) && !isset($this->_oldAttributes[$offset])) {
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
     * @param array $data
     * @return static
     * @throws
     */
    public static function populate(array $data): static
    {
        $model                 = new static();
        $model->_attributes    = $data;
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


    /**
     * @param string $field
     * @return array
     */
    public function getOldAttribute(string $field): mixed
    {
        return $this->_oldAttributes[$field] ?? null;
    }

}
