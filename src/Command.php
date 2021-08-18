<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 15:23
 */
declare(strict_types=1);

namespace Database;


use Exception;
use Kiri\Abstracts\Component;
use Kiri\Core\Json;
use PDOStatement;

/**
 * Class Command
 * @package Database
 */
class Command extends Component
{
	const ROW_COUNT = 'ROW_COUNT';
	const FETCH = 'FETCH';
	const FETCH_ALL = 'FETCH_ALL';
	const EXECUTE = 'EXECUTE';
	const FETCH_COLUMN = 'FETCH_COLUMN';

	/** @var Connection */
	public Connection $db;

	/** @var ?string */
	public ?string $sql = '';

	/** @var array */
	public array $params = [];

	/** @var string */
	public string $dbname = '';

	/** @var PDOStatement|null */
	private ?PDOStatement $prepare = null;


	/**
	 * @return array|bool|int|string|PDOStatement|null
	 * @throws Exception
	 */
	public function incrOrDecr(): array|bool|int|string|PDOStatement|null
	{
		return $this->execute(static::EXECUTE);
	}

	/**
	 * @param bool $isInsert
	 * @param mixed $hasAutoIncrement
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function save(bool $isInsert = TRUE, mixed $hasAutoIncrement = null): int|bool|array|string|null
	{
		return $this->execute(static::EXECUTE, $isInsert, $hasAutoIncrement);
	}


	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function all(): int|bool|array|string|null
	{
		return $this->execute(static::FETCH_ALL);
	}

	/**
	 * @return array|bool|int|string|null
	 * @throws Exception
	 */
	public function one(): null|array|bool|int|string
	{
		return $this->execute(static::FETCH);
	}

	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function fetchColumn(): int|bool|array|string|null
	{
		return $this->execute(static::FETCH_COLUMN);
	}

	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function rowCount(): int|bool|array|string|null
	{
		return $this->execute(static::ROW_COUNT);
	}

	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function flush(): int|bool|array|string|null
	{
		return $this->execute(static::EXECUTE);
	}

	/**
	 * @param $type
	 * @param null $isInsert
	 * @param bool|null $hasAutoIncrement
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	private function execute($type, $isInsert = null, mixed $hasAutoIncrement = null): int|bool|array|string|null
	{
		try {
			$time = microtime(true);
			if ($type === static::EXECUTE) {
				$result = $this->insert_or_change($isInsert, $hasAutoIncrement);
			} else {
				$result = $this->search($type);
			}
			if (microtime(true) - $time >= 0.02) {
				$this->warning('Mysql:' . Json::encode([$this->sql, $this->params]) . (microtime(true) - $time));
			}
		} catch (\Throwable $exception) {
			$result = $this->addError($this->sql . '. error: ' . $exception->getMessage(), 'mysql');
		} finally {
			$this->db->release();
			return $result;
		}
	}


	/**
	 * @param $type
	 * @return array|int
	 * @throws Exception
	 */
	private function search($type): array|int
	{
		$pdo = $this->db->getConnect($this->sql);
		if ($type === static::FETCH_COLUMN) {
			$data = $pdo->fetchColumn($this->sql, $this->params);
		} else if ($type === static::ROW_COUNT) {
			$data = $pdo->count($this->sql, $this->params);
		} else if ($type === static::FETCH_ALL) {
			$data = $pdo->fetchAll($this->sql, $this->params);
		} else {
			$data = $pdo->fetch($this->sql, $this->params);
		}
		return $data;
	}


	/**
	 * @param $isInsert
	 * @param $hasAutoIncrement
	 * @return bool|int
	 * @throws Exception
	 */
	private function insert_or_change($isInsert, $hasAutoIncrement): bool|int
	{
		$pdo = $this->db->getConnect($this->sql);
		$result = $pdo->execute($this->sql, $isInsert, $this->params);
		if ($hasAutoIncrement && $result == 0) {
			return false;
		}
		return $result;
	}

	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function delete(): int|bool|array|string|null
	{
		return $this->execute(static::EXECUTE);
	}

	/**
	 * @param null $scope
	 * @param bool $insert
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function exec($scope = null, bool $insert = false): int|bool|array|string|null
	{
		return $this->execute(static::EXECUTE, $insert, $scope);
	}

	/**
	 * @param array $data
	 * @return $this
	 */
	public function bindValues(array $data = []): static
	{
		if (!is_array($this->params)) {
			$this->params = [];
		}
		if (!empty($data)) {
			$this->params = array_merge($this->params, $data);
		}
		return $this;
	}

	/**
	 * @param $sql
	 * @return $this
	 * @throws Exception
	 */
	public function setSql($sql): static
	{
		$this->sql = $sql;
		return $this;
	}

}
