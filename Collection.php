<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 13:38
 */
declare(strict_types=1);

namespace Database;

use Database\Base\AbstractCollection;
use Exception;
use JetBrains\PhpStorm\Pure;

/**
 * Class Collection
 * @package Database
 * @property-read $length
 */
class Collection extends AbstractCollection
{


    /**
     * @param string $field
     *
     * @return array|null
     */
    public function values(string $field): ?array
    {
        return array_values($this->column($field));
    }


    /**
     * @param array $attributes
     * @return bool
     * @throws
     */
    public function update(array $attributes): bool
    {
        if ($this->isEmpty()) {
            return $this->getLogger()->failure('No data by update', 'mysql');
        }
        return $this->batch()->update($attributes);
    }

    /**
     * @param string $field
     * @return array|null
     */
    public function keyBy(string $field): ?array
    {
        $array  = $this->toArray();
        $column = array_flip(array_column($array, $field));
        foreach ($column as $key => $value) {
            $column[$key] = $array[$value];
        }
        return $column;
    }


    /**
     * @param int $start
     * @param int $length
     *
     * @return array
     */
    #[Pure] public function slice(int $start = 0, int $length = 20): array
    {
        if (empty($this->getItems())) {
            return [];
        }
        if (\count($this->getItems()) < $length) {
            return $this->getItems();
        } else {
            return array_slice($this->getItems(), $start, $length);
        }
    }

    /**
     * @param string $field
     * @param string $setKey
     *
     * @return array|null
     */
    public function column(string $field, string $setKey = ''): ?array
    {
        $data = $this->toArray();
        if (empty($data)) {
            return [];
        }
        if (!empty($setKey) && is_string($setKey)) {
            return array_column($data, $field, $setKey);
        } else {
            return array_column($data, $field);
        }
    }

    /**
     * @param string $field
     *
     * @return float|int|null
     */
    public function sum(string $field): float|int|null
    {
        $array = $this->column($field);
        if (empty($array)) {
            return NULL;
        }
        return array_sum($array);
    }

    /**
     * @return ModelInterface|array
     */
    #[Pure] public function current(): ModelInterface|array
    {
        return current($this->getItems());
    }

    /**
     * @return int
     */
    #[Pure] public function size(): int
    {
        return count($this->getItems());
    }

    /**
     * @return array
     * @throws
     */
    public function toArray(): array
    {
        $array = [];
        /** @var Model $value */
        foreach ($this as $value) {
            $array[] = $value->toArray();
        }
        return $array;
    }

    /**
     * @throws
     * 批量删除
     */
    public function delete(): bool
    {
        $model = $this->getModel();
        if ($this->isEmpty()) {
            return $this->getLogger()->failure('No data by delete', 'mysql');
        }
        if (!$model->hasPrimary()) {
            throw new Exception('Must set primary key. if you want to delete data');
        }
        return $this->batch()->delete();
    }


    /**
     * @return ActiveQuery
     * @throws Exception
     */
    private function batch(): ActiveQuery
    {
        return $this->makeNewQuery()->whereIn($this->getModel()->getPrimary(),
            $this->column($this->getModel()->getPrimary()));
    }

    /**
     * @param array $condition
     * @return Collection
     * @throws
     */
    public function filter(array $condition): Collection|static
    {
        $_filters = [];
        if (empty($condition)) {
            return $this;
        }
        foreach ($this as $value) {
            if (!$this->filterCheck($value, $condition)) {
                continue;
            }
            $_filters[] = $value;
        }
        return new Collection($this->query, $this->model, $_filters);
    }


    /**
     * @param array|ModelInterface $value
     * @param array $condition
     * @return bool
     */
    private function filterCheck(array|ModelInterface $value, array $condition): bool
    {
        $_value = $value;
        if ($_value instanceof ModelInterface) {
            $_value = $_value->toArray();
        }
        $_tmp = array_intersect_key($_value, $condition);
        if (count(array_diff_assoc($_tmp, $condition)) > 0) {
            return false;
        }
        return true;
    }


    /**
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function exists(string $key, mixed $value): mixed
    {
        foreach ($this as $item) {
            if ($item->$key === $value) {
                return $item;
            }
        }
        return null;
    }

    /**
     * @return bool
     */
    #[Pure] public function isEmpty(): bool
    {
        return $this->size() < 1;
    }
}
