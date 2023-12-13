<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 14:42
 */
declare(strict_types=1);

namespace Database;

use Closure;
use Database\Traits\QueryTrait;
use Kiri\Di\Context;
use Swoole\Coroutine;

/**
 * Class ActiveQuery
 * @package Database
 */
class ActiveQuery extends QueryTrait implements ISqlBuilder
{


    public bool     $asArray = FALSE;
    protected mixed $_mock   = null;


    /**
     * @param bool $asArray
     * @return static
     */
    public function asArray(bool $asArray = true): static
    {
        $this->asArray = $asArray;
        return $this;
    }

    /**
     * @param array $methods
     * @return $this
     */
    public function with(array $methods): static
    {
        $this->modelClass->setWith($methods);
        return $this;
    }


    /**
     * @return ModelInterface|array|null|bool
     * @throws
     */
    public function first(): ModelInterface|null|array|bool
    {
        $data = $this->buildCommand($this->builder->one())->one();
        if (is_array($data)) {
            return $this->populate($data);
        }
        return null;
    }

    /**
     * @return bool|Collection
     */
    public function get(): bool|Collection
    {
        $data = $this->buildCommand($this->builder->all())->all();
        if (is_array($data)) {
            return new Collection($this, $this->modelClass, $data);
        }
        return false;
    }


    /**
     * @throws
     */
    public function flush(): bool
    {
        return (bool)$this->buildCommand($this->builder->truncate())->exec();
    }


    /**
     * @param int $size
     * @param Closure $closure
     * @return void
     */
    public function chunk(int $size, Closure $closure): void
    {
        $data = $this->offset($this->offset)->limit($size)->get();
        if (!$data || $data->isEmpty()) {
            return;
        }
        if (Context::inCoroutine()) {
            Coroutine::create(fn() => $closure($data));
        } else {
            call_user_func($closure, $data);
        }
        $this->offset += $size;
        $this->chunk($size, $closure);
    }

    /**
     * @param string $field
     * @param string|null $setKey
     *
     * @return array|null
     */
    public function column(string $field, ?string $setKey = null): ?array
    {
        return $this->get()->column($field, $setKey);
    }


    /**
     * @param mixed $value
     * @return $this
     */
    public function withMock(mixed $value): static
    {
        $this->_mock = $value;
        return $this;
    }


    /**
     * @return mixed
     */
    public function mock(): mixed
    {
        return $this->_mock;
    }


    /**
     * @param $data
     * @return ModelInterface|array
     * @throws
     */
    public function populate($data): ModelInterface|array
    {
        $model = $this->modelClass->populates($data);

        return $this->asArray ? $model->toArray() : $model;
    }


    /**
     * @return int
     * @throws
     */
    public function count(): int
    {
        return $this->buildCommand($this->builder->count())->rowCount();
    }


    /**
     * @param array $data
     * @return bool
     * @throws
     */
    public function update(array $data): bool
    {
        if (count($data) < 1) {
            return true;
        }
        $generate = $this->builder->update($data);
        if (!is_bool($generate)) {
            return (bool)$this->buildCommand($generate)->exec();
        } else {
            return $generate;
        }
    }

    /**
     * @param array $data
     * @return bool
     */
    public function insert(array $data): bool
    {
        [$sql, $params] = $this->builder->insert($data, isset($data[0]));

        return (bool)$this->buildCommand($sql, $params)->exec();
    }

    /**
     * @param $filed
     *
     * @return mixed
     * @throws
     */
    public function value($filed): mixed
    {
        return $this->first()[$filed] ?? NULL;
    }

    /**
     * @return bool
     * @throws
     */
    public function exists(): bool
    {
        return $this->buildCommand($this->builder->one())->rowCount() > 0;
    }


    /**
     * @param string $sql
     * @param array $params
     * @return int|bool
     */
    public function execute(string $sql, array $params = []): int|bool
    {
        return $this->buildCommand($sql, $params)->exec();
    }


    /**
     * @return bool
     */
    public function delete(): bool
    {
        return $this->buildCommand($this->builder->delete())->delete();
    }
}
