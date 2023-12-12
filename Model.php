<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 14:39
 */
declare(strict_types=1);

namespace Database;


use Exception;

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
     * @param int|float $value
     * @return ModelInterface|false
     * @throws
     */
    public function increment(string $column, int|float $value): bool|ModelInterface
    {
        if (!$this->mathematics([$column => $value], '+')) {
            return false;
        }
        $this->{$column} += $value;
        return $this->refresh();
    }


    /**
     * @param string $column
     * @param int|float $value
     * @return ModelInterface|false
     * @throws
     */
    public function decrement(string $column, int|float $value): bool|ModelInterface
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
     * @throws
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
     * @throws
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
     * @throws
     */
    public static function findOrCreate(array $condition, array $attributes): bool|static
    {
        return Db::Transaction(function ($condition, $attributes) {
            /** @var static $select */
            $select = static::query()->where($condition)->first();
            if ($select === null) {
                $select = static::populate(array_merge($condition, $attributes))->save();
            }
            return $select;
        }, $condition, $attributes);
    }


    /**
     * @param array $condition
     * @param array $attributes
     * @return bool|static
     * @throws
     */
    public static function createOrUpdate(array $condition, array $attributes = []): bool|static
    {
        return Db::Transaction(function ($condition, $attributes) {
            /** @var static $select */
            $select = static::query()->where($condition)->first();
            if (empty($select)) {
                $select = static::populate($condition);
            }
            $select->attributes = $attributes;
            return $select->save();
        }, $condition, $attributes);
    }


    /**
     * @param $columns
     * @param $action
     * @return array|bool|int|string|null
     * @throws
     */
    private function mathematics($columns, $action): int|bool|array|string|null
    {
        $condition = [$this->getPrimary() => $this->getPrimaryValue()];

        $activeQuery = static::query()->where($condition);
        $create      = SqlBuilder::builder($activeQuery)->mathematics($columns, $action);
        if (is_bool($create)) {
            return false;
        }
        return $this->getConnection()->createCommand($create, $activeQuery->attributes)->exec();
    }


    /**
     * @param array $params
     * @return ModelInterface|bool
     * @throws
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
     * @throws
     */
    public static function inserts(array $data): bool
    {
        if (empty($data)) {
            return trigger_print_error('Insert data empty.', 'mysql');
        }
        return static::query()->insert($data);
    }

    /**
     * @return bool
     * @throws
     */
    public function delete(): bool
    {
        if ($this->beforeDelete()) {
            if ($this->hasPrimary()) {
                $result = static::deleteByCondition("id = ?", [$this->getPrimaryValue()]);
            } else {
                $result = static::deleteByCondition($this->_attributes);
            }
            $this->optimize();
            return $this->afterDelete($result);
        }
        return false;
    }


    /**
     * @param mixed $condition
     * @param array $attributes
     *
     * @return bool
     * @throws
     */
    public static function updateAll(mixed $condition, array $attributes = []): bool
    {
        return static::query()->where($condition)->update($attributes);
    }


    /**
     * @param $condition
     * @return array|Collection
     * @throws
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
     * @throws
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
     * @throws
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
            if ($join instanceof \Arrayable) {
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
     * @throws
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
     * @throws
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
     * @throws
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
     * @throws
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
     * @throws
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
