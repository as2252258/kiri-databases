<?php
/**
 * Created by PhpStorm.
 * User: whwyy
 * Date: 2018/3/30 0030
 * Time: 15:23
 */
declare(strict_types=1);

namespace Database;


use Database\Mysql\PDO;
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
	const ROW_COUNT = 'count';
	const FETCH = 'fetch';
	const FETCH_ALL = 'fetchAll';
	const EXECUTE = 'execute';
	const FETCH_COLUMN = 'fetchColumn';

	/** @var Connection */
	public Connection $db;

	/** @var ?string */
	public ?string $sql = '';

	/** @var array */
	public array $params = [];

	/** @var string */
	public string $dbname = '';


	/**
	 * @return array|bool|int|string|PDOStatement|null
	 * @throws Exception
	 */
	public function incrOrDecr(): array|bool|int|string|PDOStatement|null
	{
		return $this->execute(static::EXECUTE);
	}

	/**
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function save(): int|bool|array|string|null
	{
		return $this->execute(static::EXECUTE);
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
	 * @param string $type
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	private function execute(string $type): int|bool|array|string|null
	{
		$pdo = $this->db->getConnect($this->sql);
		$time = microtime(true);
		if ($type !== static::EXECUTE) {
			$result = $this->search($type, $pdo);
		} else {
			$result = $this->_execute($pdo);
		}
		return $this->_timeout_log($time, $result);
	}


	/**
	 * @param float $time
	 * @param mixed $result
	 * @return mixed
	 * @throws Exception
	 */
	private function _timeout_log(float $time, mixed $result): mixed
	{
		if (microtime(true) - $time >= 0.02) {
			$this->warning('Mysql:' . Json::encode([$this->sql, $this->params]) . (microtime(true) - $time));
		}
		return $result;
	}


	/**
	 * @param PDO $pdo
	 * @return bool|int
	 * @throws Exception
	 */
	private function _execute(PDO $pdo): bool|int
	{
		try {
			$result = $pdo->execute($this->sql, $this->params);
		} catch (\Throwable $exception) {
			$result = $this->addError($this->sql . '. error: ' . $exception->getMessage(), 'mysql');
		} finally {
			$this->db->release();
			return $result;
		}
	}


	/**
	 * @param string $type
	 * @param PDO $pdo
	 * @return array|int|bool|null
	 * @throws Exception
	 */
	private function search(string $type, PDO $pdo): array|int|bool|null
	{
		try {
			$data = $pdo->{$type}($this->sql, $this->params);
		} catch (\Throwable $throwable) {
			$data = $this->addError($this->sql . '. error: ' . $throwable->getMessage(), 'mysql');
		} finally {
			$this->db->releaseSlaveConnect($pdo);
			return $data;
		}
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
	 * @return int|bool|array|string|null
	 * @throws Exception
	 */
	public function exec(): int|bool|array|string|null
	{
		return $this->execute(static::EXECUTE);
	}

	/**
	 * @param array $data
	 * @return $this
	 */
	public function bindValues(array $data = []): static
	{
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
