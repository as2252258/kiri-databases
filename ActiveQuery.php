<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/4/4 0004
 * Time: 14:42
 */
declare(strict_types=1);

namespace Database;

use Database\Traits\QueryTrait;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Kiri\Abstracts\Component;

/**
 * Class ActiveQuery
 * @package Database
 */
class ActiveQuery extends Component implements ISqlBuilder
{

    use QueryTrait;

    /** @var bool */
    public bool $asArray = FALSE;

    /** @var bool */
    public bool $useCache = FALSE;

    /**
     * @var Connection|null
     */
    public ?Connection $db = NULL;


    /**
     * @var array
     * 参数绑定
     */
    public array    $attributes = [];
    protected mixed $_mock      = null;


    /**
     * Comply constructor.
     * @param $model
     * @throws
     */
    public function __construct($model)
    {
        $this->modelClass = $model;

        $this->builder = SqlBuilder::builder($this);
        parent::__construct();
    }


    /**
     * 清除不完整数据
     */
    public function clear(): void
    {
        $this->db       = NULL;
        $this->useCache = FALSE;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function addParam($key, $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }


    /**
     * @param int $size
     * @param int $page
     * @return array
     * @throws
     */
    #[ArrayShape([])]
    public function pagination(int $size = 20, int $page = 1): array
    {
        $page = max(1, $page);
        $size = max(1, $size);

        $offset = ($page - 1) * $size;

        $count = $this->count();
        $lists = $this->offset($offset)->limit($size)->get()->toArray();
        return [
            'code'    => 0,
            'message' => 'ok',
            'size'    => $size,
            'page'    => $page,
            'count'   => $count,
            'next'    => max($page + 1, 1),
            'prev'    => max($page - 1, 1),
            'param'   => $lists,
        ];
    }


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
     * @param array $values
     * @return $this
     */
    public function addParams(array $values): static
    {
        foreach ($values as $key => $val) {
            $this->addParam($key, $val);
        }
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
     * @param $sql
     * @param array $params
     * @return mixed
     * @throws
     */
    public function execute($sql, array $params = []): Command
    {
        return $this->modelClass->getConnection()->createCommand($sql, $params);
    }


    /**
     * @return ModelInterface|array|null
     * @throws Exception
     */
    public function first(): ModelInterface|null|array
    {
        $data = $this->limit(1)->execute($this->builder->one(), $this->attributes)->one();
        if (is_array($data)) {
            return $this->populate($data);
        } else {
            return NULL;
        }
    }


    /**
     * @return string
     * @throws
     */
    public function toSql(): string
    {
        return $this->builder->get();
    }


    /**
     * @return Collection
     * @throws Exception
     */
    public function get(): Collection
    {
        $data = $this->execute($this->builder->all(), $this->attributes)->all();
        if ($data !== false) {
            return new Collection($this, $data, $this->modelClass);
        } else {
            return new Collection($this, [], $this->modelClass);
        }
    }


    /**
     * @throws
     */
    public function flush(): array|bool|int|string|null
    {
        return $this->execute($this->builder->truncate())->exec();
    }


    /**
     * @param int $size
     * @param callable $callback
     * @param int $offset
     * @return Pagination
     * @throws
     */
    public function page(int $size, callable $callback, int $offset = 0): Pagination
    {
        $pagination = new Pagination($this);
        $pagination->setOffset($offset);
        $pagination->setLimit($size);
        $pagination->setCallback($callback);
        return $pagination;
    }

    /**
     * @param string $field
     * @param string $setKey
     *
     * @return array|null
     * @throws
     */
    public function column(string $field, string $setKey = ''): ?array
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
     * @throws Exception
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
        return $this->execute($this->builder->count(), $this->attributes)->one()['row_count'] ?? 0;
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
            return (bool)$this->execute($generate, $this->attributes)->exec();
        } else {
            return $generate;
        }
    }

    /**
     * @param array $data
     * @return bool
     * @throws
     */
    public function insert(array $data): bool
    {
        [$sql, $params] = $this->builder->insert($data, TRUE);

        return (bool)$this->execute($sql, $params)->exec();
    }

    /**
     * @param $filed
     *
     * @return null
     * @throws
     */
    public function value($filed)
    {
        return $this->first()[$filed] ?? NULL;
    }

    /**
     * @return bool
     * @throws
     */
    public function exists(): bool
    {
        return !empty($this->execute($this->builder->one(), $this->attributes)->fetchColumn());
    }


    /**
     * @param bool $getSql
     * @return bool|string
     * @throws Exception
     */
    public function delete(bool $getSql = FALSE): bool|string
    {
        $sql = $this->builder->delete();
        if ($getSql === FALSE) {
            return (bool)$this->execute($sql, $this->attributes)->delete();
        } else {
            return $sql;
        }
    }
}
