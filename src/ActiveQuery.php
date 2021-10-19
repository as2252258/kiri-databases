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
use Kiri\Abstracts\Component;

/**
 * Class ActiveQuery
 * @package Database
 */
class ActiveQuery extends Component implements ISqlBuilder
{

	use QueryTrait;

	/** @var array */
	public array $with = [];

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
	public array $attributes = [];


	/**
	 * Comply constructor.
	 * @param $model
	 * @param array $config
	 * @throws
	 */
	public function __construct($model, array $config = [])
	{
		$this->modelClass = $model;

		$this->builder = SqlBuilder::builder($this);
		parent::__construct($config);
	}


	/**
	 * 清除不完整数据
	 */
	public function clear()
	{
		$this->db = null;
		$this->useCache = false;
		$this->with = [];
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
	 * @param $name
	 * @return $this
	 */
	public function with($name): static
	{
		if (empty($name)) {
			return $this;
		}
		if (is_string($name)) {
			$name = explode(',', $name);
		}
		foreach ($name as $val) {
			array_push($this->with, $val);
		}
		return $this;
	}


	/**
	 * @param $sql
	 * @param array $params
	 * @return mixed
	 * @throws Exception
	 */
	public function execute($sql, array $params = []): Command
	{
		return $this->modelClass->getConnection()->createCommand($sql, $params);
	}


	/**
	 * @return ModelInterface|null
	 * @throws Exception
	 */
	public function first(): ModelInterface|null
	{
		$data = $this->execute($this->builder->one())->one();
		if (empty($data)) {
			return NULL;
		}
		return $this->populate($data);
	}


	/**
	 * @return string
	 * @throws Exception
	 */
	public function toSql(): string
	{
		return $this->builder->get();
	}


	/**
	 * @return array|Collection
	 */
	public function get(): Collection|array
	{
		return $this->all();
	}


	/**
	 * @throws Exception
	 */
	public function flush(): array|bool|int|string|null
	{
		return $this->execute($this->builder->truncate())->exec();
	}


	/**
	 * @param int $size
	 * @param callable $callback
	 * @return Pagination
	 * @throws Exception
	 */
	public function page(int $size, callable $callback): Pagination
	{
		$pagination = new Pagination($this);
		$pagination->setOffset(0);
		$pagination->setLimit($size);
		$pagination->setCallback($callback);
		return $pagination;
	}

	/**
	 * @param string $field
	 * @param string $setKey
	 *
	 * @return array|null
	 * @throws Exception
	 */
	public function column(string $field, string $setKey = ''): ?array
	{
		return $this->all()->column($field, $setKey);
	}

	/**
	 * @return array|Collection
	 * @throws
	 */
	public function all(): Collection|array
	{
		$data = $this->execute($this->builder->all())->all();
		if (!empty($this->with)){
			$this->getWith($this->modelClass);
		}
		$collect = new Collection($this, $data, $this->modelClass);
		if ($this->asArray) {
			return $collect->toArray();
		}
		return $collect;
	}

	/**
	 * @param $data
	 * @return ModelInterface
	 * @throws Exception
	 */
	public function populate($data): ModelInterface
	{
		return $this->getWith($this->modelClass::populate($data));
	}


	/**
	 * @param ModelInterface $model
	 * @return ModelInterface
	 */
	public function getWith(ModelInterface $model): ModelInterface
	{
		if (empty($this->with) || !is_array($this->with)) {
			return $model;
		}
		return $model->setWith($this->with);
	}

	/**
	 * @return int
	 * @throws Exception
	 */
	public function count(): int
	{
		$this->select = ['COUNT(*)'];
		$data = $this->execute($this->builder->count())->one();
		if ($data && is_array($data)) {
			return (int)array_shift($data);
		}
		return 0;
	}


	/**
	 * @param array $data
	 * @return array|Command|bool|int|string
	 * @throws Exception
	 */
	public function batchUpdate(array $data): Command|array|bool|int|string
	{
		$generate = $this->builder->update($data);
		if (is_bool($generate)) {
			return $generate;
		}
		return $this->execute(...$generate)->exec();
	}

	/**
	 * @param array $data
	 * @return bool
	 * @throws Exception
	 */
	public function batchInsert(array $data): bool
	{
		[$sql, $params] = $this->builder->insert($data, true);


		return $this->execute($sql, $params)->exec(null, true);
	}

	/**
	 * @param $filed
	 *
	 * @return null
	 * @throws Exception
	 */
	public function value($filed)
	{
		return $this->first()[$filed] ?? null;
	}

	/**
	 * @return bool
	 * @throws Exception
	 */
	public function exists(): bool
	{
		return !empty($this->execute($this->builder->one())->fetchColumn());
	}


	/**
	 * @param bool $getSql
	 * @return string|bool
	 * @throws Exception
	 */
	public function delete(bool $getSql = false): string|bool
	{
		$sql = $this->builder->delete();
		if ($getSql === false) {
			return $this->execute($sql)->delete();
		}
		return $sql;
	}
}
