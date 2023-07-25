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
use Database\Traits\HasBase;
use Exception;
use Kiri;
use Kiri\Exception\NotFindClassException;
use Kiri\Error\StdoutLoggerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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
     * @return bool|static
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function findOrCreate(array $condition, array $attributes): bool|static
    {
        return Db::Transaction(function ($condition, $attributes) {
            /** @var static $select */
            $select = static::query()->where($condition)->first();
            if ($select === null) {
                $select = static::populate(array_merge($condition, $attributes))->create();
            }
            return $select;
        }, $condition, $attributes);
    }


    /**
     * @param array $condition
     * @param array $attributes
     * @return bool|static
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function createOrUpdate(array $condition, array $attributes = []): bool|static
    {
        return Db::Transaction(function ($condition, $attributes) {
            /** @var static $select */
            $select = static::query()->where($condition)->first();
            if (empty($select)) {
                $select = static::populate($condition);
            }
            return $select->save($attributes);
        }, $condition, $attributes);
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
        return $this->getConnection()->createCommand($create, $activeQuery->attributes)->exec();
    }


    /**
     * @param array $params
     * @return ModelInterface|bool
     * @throws Exception
     */
    public function update(array $params): static|bool
    {
        if (!$this->validator($this->rules()) || !$this->beforeSave($this)) {
            return FALSE;
        }

        $condition = array_diff_assoc($this->_oldAttributes, $params);

        $old = array_intersect_key($this->_oldAttributes, $params);

        return $this->updateInternal($old, $condition, $params);
    }


    /**
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public static function inserts(array $data): bool
    {
        if (empty($data)) {
            return addError('Insert data empty.', 'mysql');
        }
        return static::query()->insert($data);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function delete(): bool
    {
        if (!$this->beforeDelete()) {
            return false;
        }
        if ($this->hasPrimary()) {
            $result = static::deleteByCondition("id = :id", [":id" => $this->getPrimaryValue()]);
        } else {
            $result = static::deleteByCondition($this->_attributes);
        }
        return $this->afterDelete($result);
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
     * @throws Exception
     */
    public static function get($condition): Collection|array
    {
        return static::query()->where($condition)->get();
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
        return $query->get();
    }


    /**
     * @return array
     * @throws Exception
     */
    public function toArray(): array
    {
        $data = $this->_attributes;
        foreach ($data as $key => $datum) {
            $method = 'get' . ucfirst($key) . 'Attribute';
            if (!method_exists($this, $method)) {
                continue;
            }
            $data[$key] = $this->{$method}($datum);
        }
        return $this->withs($data);
    }


    /**
     * @param $data
     * @return array
     */
    private function withs($data): array
    {
        $with = $this->getWith();
        foreach ($with as $value) {
            $join = $this->withRelate($value);
            if ($join instanceof Kiri\ToArray) {
                $join = $join->toArray();
            }
            $data[$value] = $join;
        }
        return $data;
    }


    /**
     * @param ModelInterface|string $modelName
     * @param $foreignKey
     * @param $localKey
     * @return string
     * @throws Exception
     */
    private function _hasBase(ModelInterface|string $modelName, $foreignKey, $localKey): string
    {
        if (($value = $this->{$localKey}) === null) {
            throw new Exception("Need join table primary key.");
        }

        $relation = $this->getRelation();

        $primaryKey = $modelName . $foreignKey . $value;
        if (!$relation->hasIdentification($primaryKey)) {
            $relation->bindIdentification($primaryKey, $modelName::query()->where([$foreignKey => $value]));
        }
        return $primaryKey;
    }


    /**
     * @param ModelInterface|string $modelName
     * @param $foreignKey
     * @param $localKey
     * @return HasOne|ActiveQuery
     * @throws Exception
     */
    public function hasOne(ModelInterface|string $modelName, $foreignKey, $localKey): HasOne|ActiveQuery
    {
        return new HasOne($this->_hasBase($modelName, $foreignKey, $localKey));
    }


    /**
     * @param ModelInterface|string $modelName
     * @param $foreignKey
     * @param $localKey
     * @return ActiveQuery|HasCount
     * @throws Exception
     */
    public function hasCount(ModelInterface|string $modelName, $foreignKey, $localKey): ActiveQuery|HasCount
    {
        return new HasCount($this->_hasBase($modelName, $foreignKey, $localKey));
    }


    /**
     * @param ModelInterface|string $modelName
     * @param $foreignKey
     * @param $localKey
     * @return ActiveQuery|HasMany
     * @throws Exception
     */
    public function hasMany(ModelInterface|string $modelName, $foreignKey, $localKey): ActiveQuery|HasMany
    {
        return new HasMany($this->_hasBase($modelName, $foreignKey, $localKey));
    }

    /**
     * @param ModelInterface|string $modelName
     * @param $foreignKey
     * @param $localKey
     * @return ActiveQuery|HasMany
     * @throws Exception
     */
    public function hasIn(ModelInterface|string $modelName, $foreignKey, $localKey): ActiveQuery|HasMany
    {
        if (($value = $this->{$localKey}) === null) {
            throw new Exception("Need join table primary key.");
        }

        $relation = $this->getRelation();

        $primaryKey = $modelName . $foreignKey . json_encode($value, JSON_UNESCAPED_UNICODE);
        if (!$relation->hasIdentification($primaryKey)) {
            $relation->bindIdentification($primaryKey, $modelName::query()->whereIn($foreignKey, $value));
        }

        return new HasMany($primaryKey);
    }

    /**
     * @param bool $result
     * @return bool
     */
    public function afterDelete(bool $result): bool
    {
        return $result;
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
